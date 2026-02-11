<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../integrations/aloha_helpers.php';
require_once __DIR__ . '/job_lib.php';

function jq_process_jobs(PDO $pdo, int $limit = 10, string $owner = 'worker'): array
{
    $processed = 0;
    $succeeded = 0;
    $failed = 0;

    $listStmt = $pdo->prepare(
        'SELECT id, restaurant_id, job_type, payload_json, attempts, max_attempts
         FROM job_queue
         WHERE status="queued" AND run_after <= NOW()
         ORDER BY priority ASC, id ASC
         LIMIT :limit_rows'
    );
    $listStmt->bindValue(':limit_rows', max(1, $limit), PDO::PARAM_INT);
    $listStmt->execute();
    $jobs = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        $claim = $pdo->prepare(
            'UPDATE job_queue
             SET status="running", started_at=NOW(), attempts=attempts+1, last_error=NULL
             WHERE id=:id AND status="queued" AND run_after <= NOW()'
        );
        $claim->execute([':id' => $jobId]);
        if ($claim->rowCount() === 0) {
            continue;
        }

        $processed++;
        $payload = json_decode((string)$job['payload_json'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            jq_log($pdo, $jobId, 'info', 'Job started: ' . (string)$job['job_type']);
            if ((string)$job['job_type'] === 'aloha_process_batch') {
                $restaurantId = (int)($payload['restaurant_id'] ?? $job['restaurant_id'] ?? 0);
                $batchId = (int)($payload['batch_id'] ?? 0);
                if ($restaurantId <= 0 || $batchId <= 0) {
                    throw new RuntimeException('Invalid aloha_process_batch payload.');
                }
                $batchLock = 'aloha_batch_' . $batchId;
                if (!jq_acquire_lock($pdo, $batchLock, 600, $owner)) {
                    throw new RuntimeException('Batch is already being processed.');
                }
                try {
                    $summary = schedule_aloha_process_batch_job($pdo, $restaurantId, $batchId);
                } finally {
                    jq_release_lock($pdo, $batchLock);
                }
                jq_log($pdo, $jobId, 'info', 'Aloha batch processed. Imported=' . (int)($summary['rows_imported'] ?? 0) . ' Skipped=' . (int)($summary['rows_skipped'] ?? 0));
            } else {
                throw new RuntimeException('Unsupported job type: ' . (string)$job['job_type']);
            }

            $done = $pdo->prepare('UPDATE job_queue SET status="succeeded", finished_at=NOW() WHERE id=:id');
            $done->execute([':id' => $jobId]);
            $succeeded++;
        } catch (Throwable $e) {
            $attemptNow = ((int)$job['attempts']) + 1;
            $maxAttempts = (int)$job['max_attempts'];
            $err = mb_substr($e->getMessage(), 0, 2000);
            jq_log($pdo, $jobId, 'error', $err);

            if ($attemptNow < $maxAttempts) {
                $delayMinutes = max(1, $attemptNow * $attemptNow);
                $retry = $pdo->prepare(
                    'UPDATE job_queue
                     SET status="queued", finished_at=NOW(), last_error=:last_error,
                         run_after=DATE_ADD(NOW(), INTERVAL :delay_minutes MINUTE)
                     WHERE id=:id'
                );
                $retry->bindValue(':last_error', $err);
                $retry->bindValue(':delay_minutes', $delayMinutes, PDO::PARAM_INT);
                $retry->bindValue(':id', $jobId, PDO::PARAM_INT);
                $retry->execute();
            } else {
                $markFailed = $pdo->prepare('UPDATE job_queue SET status="failed", finished_at=NOW(), last_error=:last_error WHERE id=:id');
                $markFailed->execute([':last_error' => $err, ':id' => $jobId]);
                $failed++;
            }
        }
    }

    return ['processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed];
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        fwrite(STDERR, "Database unavailable.\n");
        exit(1);
    }

    $limit = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 10;
    $owner = 'cli-' . getmypid();
    if (!jq_acquire_lock($pdo, 'job_worker_global', 240, $owner)) {
        fwrite(STDOUT, "Worker already running.\n");
        exit(0);
    }

    try {
        $result = jq_process_jobs($pdo, $limit, $owner);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    } finally {
        jq_release_lock($pdo, 'job_worker_global');
    }
}