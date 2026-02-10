<?php
declare(strict_types=1);

function schedule_aloha_upload_dir(): string
{
    $dir = __DIR__ . '/../storage/imports';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $denyFile = $dir . '/.htaccess';
    if (!is_file($denyFile)) {
        @file_put_contents($denyFile, "Deny from all\n");
    }

    return $dir;
}

function schedule_aloha_normalize_headers(array $headers): array
{
    $clean = [];
    foreach ($headers as $header) {
        $value = is_string($header) ? trim($header) : '';
        if (strpos($value, "\xEF\xBB\xBF") === 0) {
            $value = substr($value, 3);
        }
        $clean[] = $value;
    }
    return $clean;
}

function schedule_aloha_parse_datetime(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y H:i:s', 'm/d/Y H:i', 'n/j/Y g:i A', 'n/j/Y g:iA', DateTimeInterface::ATOM];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $ts = strtotime($value);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

function schedule_aloha_parse_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    return $ts === false ? null : date('Y-m-d', $ts);
}

function schedule_aloha_parse_decimal(?string $value): ?float
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $clean = str_replace([',', '$'], '', $value);
    return is_numeric($clean) ? (float)$clean : null;
}

function schedule_aloha_mapping_requirements(string $importType): array
{
    if ($importType === 'employees') {
        return ['external_employee_id'];
    }
    if ($importType === 'labor') {
        return ['external_employee_id', 'punch_in_dt'];
    }
    return ['business_date', 'gross_sales'];
}

function schedule_aloha_validate_mapping(string $importType, array $mapping): ?string
{
    foreach (schedule_aloha_mapping_requirements($importType) as $requiredField) {
        if (!isset($mapping[$requiredField]) || trim((string)$mapping[$requiredField]) === '') {
            return 'Missing required mapping: ' . $requiredField;
        }
    }

    if ($importType === 'employees') {
        $hasDisplay = isset($mapping['display_name']) && trim((string)$mapping['display_name']) !== '';
        $hasSplit = isset($mapping['first_name'], $mapping['last_name'])
            && trim((string)$mapping['first_name']) !== ''
            && trim((string)$mapping['last_name']) !== '';
        if (!$hasDisplay && !$hasSplit) {
            return 'Employees mapping needs display_name or both first_name and last_name.';
        }
    }

    return null;
}

function schedule_aloha_batch_file_path(int $batchId): string
{
    return schedule_aloha_upload_dir() . '/aloha_batch_' . $batchId . '.csv';
}

function schedule_handle_aloha_api(string $action, int $resId, int $userId): void
{
    schedule_require_manager_api();
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        schedule_json_error('Database unavailable.', 500);
    }

    if ($action === 'aloha_enable') {
        $credentials = json_encode(['mode' => 'csv_import'], JSON_UNESCAPED_UNICODE);
        if (!is_string($credentials)) {
            $credentials = '{"mode":"csv_import"}';
        }
        schedule_execute(
            'INSERT INTO pos_connections (restaurant_id, provider, status, credentials_json, updated_at)
             VALUES (:restaurant_id, "aloha", "enabled", :credentials_json, NOW())
             ON DUPLICATE KEY UPDATE status="enabled", credentials_json=VALUES(credentials_json), updated_at=NOW()',
            [':restaurant_id' => $resId, ':credentials_json' => $credentials]
        );
        schedule_json_success(['message' => 'Aloha enabled.']);
    }

    if ($action === 'aloha_upload_csv') {
        $importType = (string)($_POST['import_type'] ?? '');
        if (!in_array($importType, ['employees', 'labor', 'sales'], true)) {
            schedule_json_error('Invalid import type.', 422);
        }
        if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
            schedule_json_error('CSV file is required.', 422);
        }

        $file = $_FILES['csv_file'];
        $name = (string)($file['name'] ?? '');
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $error = (int)($file['error'] ?? UPLOAD_ERR_OK);

        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            schedule_json_error('Upload failed.', 422);
        }
        if (strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            schedule_json_error('Only .csv files are allowed.', 422);
        }
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            schedule_json_error('CSV file must be between 1 byte and 5MB.', 422);
        }

        $handle = fopen($tmp, 'rb');
        if (!is_resource($handle)) {
            schedule_json_error('Could not read uploaded file.', 422);
        }
        $headers = fgetcsv($handle);
        fclose($handle);
        if (!is_array($headers) || $headers === []) {
            schedule_json_error('CSV appears empty.', 422);
        }
        $headers = schedule_aloha_normalize_headers($headers);

        $metaJson = json_encode(['headers' => $headers], JSON_UNESCAPED_UNICODE);
        if (!is_string($metaJson)) {
            $metaJson = '{"headers":[]}';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO aloha_import_batches (restaurant_id, provider, import_type, original_filename, status, mapping_json, created_by, created_at)
             VALUES (:restaurant_id, "aloha", :import_type, :original_filename, "uploaded", :mapping_json, :created_by, NOW())'
        );
        if (!$stmt || !$stmt->execute([
            ':restaurant_id' => $resId,
            ':import_type' => $importType,
            ':original_filename' => $name,
            ':mapping_json' => $metaJson,
            ':created_by' => $userId,
        ])) {
            schedule_json_error('Could not create import batch.', 500);
        }

        $batchId = (int)$pdo->lastInsertId();
        $path = schedule_aloha_batch_file_path($batchId);
        if (!move_uploaded_file($tmp, $path)) {
            schedule_execute('UPDATE aloha_import_batches SET status="failed", error_text=:error_text WHERE restaurant_id=:restaurant_id AND id=:id', [
                ':error_text' => 'Could not store uploaded file.',
                ':restaurant_id' => $resId,
                ':id' => $batchId,
            ]);
            schedule_json_error('Could not store uploaded file.', 500);
        }

        schedule_json_success(['batch_id' => $batchId, 'headers' => $headers]);
    }

    if ($action === 'aloha_save_mapping') {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            schedule_json_error('Invalid batch.', 422);
        }
        $batch = schedule_fetch_one('SELECT id, import_type, mapping_json FROM aloha_import_batches WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $batchId]);
        if ($batch === null) {
            schedule_json_error('Batch not found.', 404);
        }

        $mapping = $_POST['mapping'] ?? [];
        if (!is_array($mapping)) {
            schedule_json_error('Invalid mapping payload.', 422);
        }
        $mapping = array_map(static fn($v): string => trim((string)$v, " \t\n\r\0\x0B"), $mapping);

        $err = schedule_aloha_validate_mapping((string)$batch['import_type'], $mapping);
        if ($err !== null) {
            schedule_json_error($err, 422);
        }

        $existing = json_decode((string)($batch['mapping_json'] ?? '{}'), true);
        if (!is_array($existing)) {
            $existing = [];
        }
        $payload = ['headers' => $existing['headers'] ?? [], 'mapping' => $mapping];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            schedule_json_error('Could not encode mapping.', 500);
        }

        schedule_execute('UPDATE aloha_import_batches SET mapping_json=:mapping_json, status="mapped" WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':mapping_json' => $json,
            ':restaurant_id' => $resId,
            ':id' => $batchId,
        ]);
        schedule_json_success(['message' => 'Mapping saved.']);
    }

    if ($action === 'aloha_process_batch') {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            schedule_json_error('Invalid batch.', 422);
        }
        $batch = schedule_fetch_one('SELECT * FROM aloha_import_batches WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $batchId]);
        if ($batch === null) {
            schedule_json_error('Batch not found.', 404);
        }

        $meta = json_decode((string)($batch['mapping_json'] ?? '{}'), true);
        if (!is_array($meta) || !isset($meta['mapping']) || !is_array($meta['mapping'])) {
            schedule_json_error('Save a field mapping before processing.', 422);
        }
        $mapping = $meta['mapping'];

        $path = schedule_aloha_batch_file_path($batchId);
        if (!is_file($path)) {
            schedule_json_error('CSV file is missing from storage.', 422);
        }

        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            schedule_json_error('Could not open CSV file.', 500);
        }
        $headers = fgetcsv($handle);
        if (!is_array($headers)) {
            fclose($handle);
            schedule_json_error('CSV has no header row.', 422);
        }
        $headers = schedule_aloha_normalize_headers($headers);

        $total = 0;
        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $assoc = [];
            foreach ($headers as $idx => $header) {
                $assoc[$header] = trim((string)($row[$idx] ?? ''));
            }

            $pick = static function (string $field) use ($mapping, $assoc): string {
                $header = trim((string)($mapping[$field] ?? ''));
                return $header !== '' ? trim((string)($assoc[$header] ?? '')) : '';
            };

            $importType = (string)$batch['import_type'];
            $ok = false;
            if ($importType === 'employees') {
                $externalId = $pick('external_employee_id');
                $displayName = $pick('display_name');
                $firstName = $pick('first_name');
                $lastName = $pick('last_name');
                if ($displayName === '') {
                    $displayName = trim($firstName . ' ' . $lastName);
                }
                if ($externalId === '' || $displayName === '') {
                    $skipped++;
                    $errors[] = 'Row ' . ($total + 1) . ': missing required employee fields.';
                    continue;
                }
                $isActive = $pick('is_active');
                $isActiveFlag = in_array(strtolower($isActive), ['0', 'false', 'no', 'n', 'inactive'], true) ? 0 : 1;
                $stmt = $pdo->prepare('INSERT INTO aloha_employees_stage (restaurant_id, batch_id, external_employee_id, first_name, last_name, display_name, email, is_active, raw_json)
                    VALUES (:restaurant_id, :batch_id, :external_employee_id, :first_name, :last_name, :display_name, :email, :is_active, :raw_json)');
                $ok = $stmt && $stmt->execute([
                    ':restaurant_id' => $resId,
                    ':batch_id' => $batchId,
                    ':external_employee_id' => $externalId,
                    ':first_name' => $firstName !== '' ? $firstName : null,
                    ':last_name' => $lastName !== '' ? $lastName : null,
                    ':display_name' => $displayName,
                    ':email' => ($pick('email') !== '' ? $pick('email') : null),
                    ':is_active' => $isActiveFlag,
                    ':raw_json' => json_encode($assoc, JSON_UNESCAPED_UNICODE),
                ]);
            } elseif ($importType === 'labor') {
                $externalId = $pick('external_employee_id');
                $punchIn = schedule_aloha_parse_datetime($pick('punch_in_dt'));
                $punchOut = schedule_aloha_parse_datetime($pick('punch_out_dt'));
                if ($externalId === '' || $punchIn === null) {
                    $skipped++;
                    $errors[] = 'Row ' . ($total + 1) . ': missing employee id or punch_in.';
                    continue;
                }
                $stmt = $pdo->prepare('INSERT INTO aloha_labor_punches_stage (restaurant_id, batch_id, external_employee_id, punch_in_dt, punch_out_dt, job_code, location_code, raw_json)
                    VALUES (:restaurant_id, :batch_id, :external_employee_id, :punch_in_dt, :punch_out_dt, :job_code, :location_code, :raw_json)');
                $ok = $stmt && $stmt->execute([
                    ':restaurant_id' => $resId,
                    ':batch_id' => $batchId,
                    ':external_employee_id' => $externalId,
                    ':punch_in_dt' => $punchIn,
                    ':punch_out_dt' => $punchOut,
                    ':job_code' => ($pick('job_code') !== '' ? $pick('job_code') : null),
                    ':location_code' => ($pick('location_code') !== '' ? $pick('location_code') : null),
                    ':raw_json' => json_encode($assoc, JSON_UNESCAPED_UNICODE),
                ]);
            } else {
                $businessDate = schedule_aloha_parse_date($pick('business_date'));
                $grossSales = schedule_aloha_parse_decimal($pick('gross_sales'));
                $netSales = schedule_aloha_parse_decimal($pick('net_sales'));
                $orders = $pick('orders_count');
                if ($businessDate === null || $grossSales === null) {
                    $skipped++;
                    $errors[] = 'Row ' . ($total + 1) . ': missing business_date or gross_sales.';
                    continue;
                }
                $stmt = $pdo->prepare('INSERT INTO aloha_sales_daily_stage (restaurant_id, batch_id, business_date, gross_sales, net_sales, orders_count, raw_json)
                    VALUES (:restaurant_id, :batch_id, :business_date, :gross_sales, :net_sales, :orders_count, :raw_json)
                    ON DUPLICATE KEY UPDATE batch_id=VALUES(batch_id), gross_sales=VALUES(gross_sales), net_sales=VALUES(net_sales), orders_count=VALUES(orders_count), raw_json=VALUES(raw_json)');
                $ok = $stmt && $stmt->execute([
                    ':restaurant_id' => $resId,
                    ':batch_id' => $batchId,
                    ':business_date' => $businessDate,
                    ':gross_sales' => $grossSales,
                    ':net_sales' => $netSales,
                    ':orders_count' => ($orders !== '' && is_numeric($orders) ? (int)$orders : null),
                    ':raw_json' => json_encode($assoc, JSON_UNESCAPED_UNICODE),
                ]);
            }

            if ($ok) {
                $imported++;
            } else {
                $skipped++;
                $errors[] = 'Row ' . ($total + 1) . ': database insert failed.';
            }
        }
        fclose($handle);

        $summary = [
            'rows_total' => $total,
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'errors_count' => count($errors),
            'top_errors' => array_values(array_slice($errors, 0, 5)),
        ];

        $summaryJson = json_encode(['summary' => $summary], JSON_UNESCAPED_UNICODE);
        if (!is_string($summaryJson)) {
            $summaryJson = '{"summary":{}}';
        }

        schedule_execute('UPDATE aloha_import_batches SET status="processed", processed_at=NOW(), error_text=:error_text WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':error_text' => $summaryJson,
            ':restaurant_id' => $resId,
            ':id' => $batchId,
        ]);

        schedule_json_success(['summary' => $summary]);
    }

    if ($action === 'aloha_list_batches') {
        $rows = schedule_fetch_all(
            'SELECT id, import_type, original_filename, status, created_at, processed_at, error_text
             FROM aloha_import_batches WHERE restaurant_id=:restaurant_id ORDER BY id DESC LIMIT 25',
            [':restaurant_id' => $resId]
        );
        schedule_json_success(['batches' => $rows]);
    }

    if ($action === 'aloha_list_jobcodes') {
        $rows = schedule_fetch_all(
            'SELECT DISTINCT job_code FROM aloha_labor_punches_stage
             WHERE restaurant_id=:restaurant_id AND job_code IS NOT NULL AND job_code != ""
             ORDER BY job_code ASC',
            [':restaurant_id' => $resId]
        );
        schedule_json_success(['job_codes' => array_map(static fn(array $r): string => (string)$r['job_code'], $rows)]);
    }

    if ($action === 'aloha_save_pos_mapping') {
        $type = (string)($_POST['mapping_type'] ?? '');
        if (!in_array($type, ['employee', 'role'], true)) {
            schedule_json_error('Invalid mapping type.', 422);
        }

        $externalId = trim((string)($_POST['external_id'] ?? ''));
        if ($externalId === '') {
            schedule_json_error('external_id is required.', 422);
        }

        $internalId = trim((string)($_POST['internal_id'] ?? ''));
        if ($internalId === '') {
            schedule_json_error('internal_id is required.', 422);
        }

        schedule_execute(
            'INSERT INTO pos_mappings (restaurant_id, provider, external_id, internal_id, type, updated_at)
             VALUES (:restaurant_id, "aloha", :external_id, :internal_id, :type, NOW())
             ON DUPLICATE KEY UPDATE internal_id=VALUES(internal_id), updated_at=NOW()',
            [
                ':restaurant_id' => $resId,
                ':external_id' => $externalId,
                ':internal_id' => $internalId,
                ':type' => $type,
            ]
        );

        schedule_json_success(['message' => 'Mapping saved.']);
    }

    schedule_json_error('Unknown Aloha action.', 422);
}