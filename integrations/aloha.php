<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../pos/AlohaAdapter.php';

schedule_require_auth();
if (!schedule_is_manager()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$resId = schedule_restaurant_id();
if ($resId === null) {
    header('Location: /login.php');
    exit;
}

$adapter = new AlohaAdapter();
$connection = $adapter->getConnection($resId);
$isConnected = $connection !== null && (($connection['status'] ?? '') === 'enabled' || ($connection['status'] ?? '') === 'active');

$batchId = (int)($_GET['batch_id'] ?? 0);
$selectedBatch = null;
$headers = [];
$mapping = [];
if ($batchId > 0) {
    $selectedBatch = schedule_fetch_one('SELECT * FROM aloha_import_batches WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $batchId]);
    if ($selectedBatch !== null) {
        $meta = json_decode((string)($selectedBatch['mapping_json'] ?? '{}'), true);
        if (is_array($meta)) {
            $headers = isset($meta['headers']) && is_array($meta['headers']) ? $meta['headers'] : [];
            $mapping = isset($meta['mapping']) && is_array($meta['mapping']) ? $meta['mapping'] : [];
        }
    }
}

$batches = schedule_fetch_all('SELECT id, import_type, original_filename, status, created_at, processed_at, error_text FROM aloha_import_batches WHERE restaurant_id=:restaurant_id ORDER BY id DESC LIMIT 15', [':restaurant_id' => $resId]);

$employeeStage = schedule_fetch_all(
    'SELECT s.external_employee_id, MAX(s.display_name) AS display_name, MAX(s.email) AS email, MAX(s.is_active) AS is_active
     FROM aloha_employees_stage s WHERE s.restaurant_id=:restaurant_id GROUP BY s.external_employee_id ORDER BY display_name ASC LIMIT 100',
    [':restaurant_id' => $resId]
);
$employeeMappings = schedule_fetch_all('SELECT external_id, internal_id FROM pos_mappings WHERE restaurant_id=:restaurant_id AND provider="aloha" AND type="employee"', [':restaurant_id' => $resId]);
$employeeMap = [];
foreach ($employeeMappings as $row) {
    $employeeMap[(string)$row['external_id']] = (string)$row['internal_id'];
}

$staffOptions = schedule_fetch_all(
    'SELECT ur.user_id AS id, u.name FROM user_restaurants ur
     INNER JOIN users u ON u.id=ur.user_id
     WHERE ur.restaurant_id=:restaurant_id AND ur.is_active=1
     ORDER BY u.name ASC',
    [':restaurant_id' => $resId]
);

$jobCodes = schedule_fetch_all('SELECT DISTINCT job_code FROM aloha_labor_punches_stage WHERE restaurant_id=:restaurant_id AND job_code IS NOT NULL AND job_code != "" ORDER BY job_code ASC', [':restaurant_id' => $resId]);
$roleOptions = schedule_fetch_all('SELECT id, name FROM roles WHERE restaurant_id=:restaurant_id AND is_active=1 ORDER BY sort_order ASC, name ASC', [':restaurant_id' => $resId]);
$roleMappings = schedule_fetch_all('SELECT external_id, internal_id FROM pos_mappings WHERE restaurant_id=:restaurant_id AND provider="aloha" AND type="role"', [':restaurant_id' => $resId]);
$roleMap = [];
foreach ($roleMappings as $row) {
    $roleMap[(string)$row['external_id']] = (string)$row['internal_id'];
}

$mappingFields = [
    'employees' => [
        'external_employee_id' => 'External Employee ID *',
        'display_name' => 'Display Name',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'is_active' => 'Is Active',
    ],
    'labor' => [
        'external_employee_id' => 'External Employee ID *',
        'punch_in_dt' => 'Punch In Date/Time *',
        'punch_out_dt' => 'Punch Out Date/Time',
        'job_code' => 'Job Code',
        'location_code' => 'Location Code',
    ],
    'sales' => [
        'business_date' => 'Business Date *',
        'gross_sales' => 'Gross Sales *',
        'net_sales' => 'Net Sales',
        'orders_count' => 'Orders Count',
    ],
];

schedule_page_start('Aloha Integration', 'integrations');
?>
<section>
    <article class="card">
        <h2>Aloha Connection</h2>
        <p>Status: <strong><?= $isConnected ? 'Connected' : 'Not Connected' ?></strong></p>
        <p>v1 uses secure CSV import mode for Aloha exports.</p>
        <form class="api-form" method="post" action="/integrations/api.php" data-success="Aloha enabled.">
            <input type="hidden" name="action" value="aloha_enable">
            <button class="button" type="submit">Enable Aloha</button>
        </form>
    </article>

    <article class="card">
        <h2>Import Data</h2>
        <div class="card">
            <h3>1) Import Employees CSV</h3>
            <form class="api-form" method="post" action="/integrations/api.php" enctype="multipart/form-data" data-success="Employees CSV uploaded.">
                <input type="hidden" name="action" value="aloha_upload_csv">
                <input type="hidden" name="import_type" value="employees">
                <input type="file" name="csv_file" accept=".csv" required>
                <button class="button" type="submit">Upload Employees CSV</button>
            </form>
        </div>
        <div class="card">
            <h3>2) Import Labor Punches CSV</h3>
            <form class="api-form" method="post" action="/integrations/api.php" enctype="multipart/form-data" data-success="Labor CSV uploaded.">
                <input type="hidden" name="action" value="aloha_upload_csv">
                <input type="hidden" name="import_type" value="labor">
                <input type="file" name="csv_file" accept=".csv" required>
                <button class="button" type="submit">Upload Labor CSV</button>
            </form>
        </div>
        <div class="card">
            <h3>3) Import Sales Daily CSV</h3>
            <form class="api-form" method="post" action="/integrations/api.php" enctype="multipart/form-data" data-success="Sales CSV uploaded.">
                <input type="hidden" name="action" value="aloha_upload_csv">
                <input type="hidden" name="import_type" value="sales">
                <input type="file" name="csv_file" accept=".csv" required>
                <button class="button" type="submit">Upload Sales CSV</button>
            </form>
        </div>
    </article>

    <?php if ($selectedBatch !== null): ?>
    <article class="card">
        <h2>Map CSV Fields (Batch #<?= (int)$selectedBatch['id'] ?>, <?= htmlspecialchars((string)$selectedBatch['import_type'], ENT_QUOTES, 'UTF-8') ?>)</h2>
        <form class="api-form" method="post" action="/integrations/api.php" data-success="Mapping saved.">
            <input type="hidden" name="action" value="aloha_save_mapping">
            <input type="hidden" name="batch_id" value="<?= (int)$selectedBatch['id'] ?>">
            <?php foreach ($mappingFields[(string)$selectedBatch['import_type']] ?? [] as $fieldKey => $label): ?>
                <label><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    <select name="mapping[<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>]">
                        <option value="">-- not mapped --</option>
                        <?php foreach ($headers as $header): ?>
                            <option value="<?= htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') ?>" <?= (($mapping[$fieldKey] ?? '') === $header) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <button class="button" type="submit">Save Mapping</button>
        </form>
        <form class="api-form" method="post" action="/integrations/api.php" data-success="Import queued—check Recent Imports in a moment.">
            <input type="hidden" name="action" value="aloha_queue_process_batch">
            <input type="hidden" name="batch_id" value="<?= (int)$selectedBatch['id'] ?>">
            <button class="button" type="submit">Queue Batch Processing</button>
        </form>
    </article>
    <?php endif; ?>

    <article class="card">
        <h2>Recent Imports</h2>
        <p><a class="button" href="/integrations/jobs.php">Open Job Queue</a></p>
        <form class="api-form" method="post" action="/jobs/run_once.php" data-success="Run once complete.">
            <button class="button" type="submit">Run Queue Now</button>
        </form>
        <?php if ($batches === []): ?>
            <p>No imports yet.</p>
        <?php else: ?>
            <?php foreach ($batches as $batch): ?>
                <?php $info = json_decode((string)($batch['error_text'] ?? ''), true); $summary = is_array($info) ? ($info['summary'] ?? null) : null; ?>
                <div class="card">
                    <strong>#<?= (int)$batch['id'] ?> <?= htmlspecialchars((string)$batch['import_type'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <p><?= htmlspecialchars((string)$batch['original_filename'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars((string)$batch['status'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (is_array($summary)): ?>
                        <p>Total <?= (int)($summary['rows_total'] ?? 0) ?> • Imported <?= (int)($summary['rows_imported'] ?? 0) ?> • Skipped <?= (int)($summary['rows_skipped'] ?? 0) ?></p>
                    <?php endif; ?>
                    <a class="button" href="/integrations/aloha.php?batch_id=<?= (int)$batch['id'] ?>">Map / Process</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2>Map Employees</h2>
        <?php if ($employeeStage === []): ?>
            <p>No employee stage data yet.</p>
        <?php else: ?>
            <?php foreach ($employeeStage as $emp): $external = (string)$emp['external_employee_id']; ?>
                <form class="api-form card" method="post" action="/integrations/api.php" data-success="Employee mapping saved.">
                    <input type="hidden" name="action" value="aloha_save_pos_mapping">
                    <input type="hidden" name="mapping_type" value="employee">
                    <input type="hidden" name="external_id" value="<?= htmlspecialchars($external, ENT_QUOTES, 'UTF-8') ?>">
                    <p><strong><?= htmlspecialchars((string)$emp['display_name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($external, ENT_QUOTES, 'UTF-8') ?>)</p>
                    <label>Link to staff member
                        <select name="internal_id" required>
                            <option value="">Select staff</option>
                            <?php foreach ($staffOptions as $staff): ?>
                                <option value="<?= (int)$staff['id'] ?>" <?= (($employeeMap[$external] ?? '') === (string)$staff['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$staff['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button" type="submit">Save Employee Mapping</button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2>Map Job Codes to Roles</h2>
        <?php if ($jobCodes === []): ?>
            <p>No labor job codes imported yet.</p>
        <?php else: ?>
            <?php foreach ($jobCodes as $row): $job = (string)$row['job_code']; ?>
                <form class="api-form card" method="post" action="/integrations/api.php" data-success="Role mapping saved.">
                    <input type="hidden" name="action" value="aloha_save_pos_mapping">
                    <input type="hidden" name="mapping_type" value="role">
                    <input type="hidden" name="external_id" value="<?= htmlspecialchars($job, ENT_QUOTES, 'UTF-8') ?>">
                    <p><strong><?= htmlspecialchars($job, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <label>Role
                        <select name="internal_id" required>
                            <option value="">Select role</option>
                            <?php foreach ($roleOptions as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= (($roleMap[$job] ?? '') === (string)$role['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button" type="submit">Save Job Mapping</button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>
</section>
<?php schedule_page_end();