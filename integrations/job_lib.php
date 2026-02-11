<?php
declare(strict_types=1);

function jq_enqueue(PDO $pdo, string $jobType, array $payload, int $priority = 100, ?int $restaurantId = null, ?int $createdBy = null, int $delaySeconds = 0): int
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson)) {
        $payloadJson = '{}';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO job_queue (restaurant_id, job_type, payload_json, status, priority, run_after, max_attempts, created_by, created_at)
         VALUES (:restaurant_id, :job_type, :payload_json, "queued", :priority, DATE_ADD(NOW(), INTERVAL :delay_seconds SECOND), 5, :created_by, NOW())'
    );
    $stmt->execute([
        ':restaurant_id' => $restaurantId,
        ':job_type' => $jobType,
        ':payload_json' => $payloadJson,
        ':priority' => $priority,
        ':delay_seconds' => max(0, $delaySeconds),
        ':created_by' => $createdBy,
    ]);

    return (int)$pdo->lastInsertId();
}

function jq_log(PDO $pdo, int $jobId, string $level, string $message): void
{
    $safeLevel = in_array($level, ['info', 'warn', 'error'], true) ? $level : 'info';
    $stmt = $pdo->prepare('INSERT INTO job_logs (job_id, log_level, message, created_at) VALUES (:job_id, :log_level, :message, NOW())');
    $stmt->execute([
        ':job_id' => $jobId,
        ':log_level' => $safeLevel,
        ':message' => $message,
    ]);
}

function jq_acquire_lock(PDO $pdo, string $lockKey, int $ttlSeconds = 240, string $owner = 'worker'): bool
{
    $stmt = $pdo->prepare(
        'INSERT INTO job_locks (lock_key, locked_at, expires_at, owner)
         VALUES (:lock_key, NOW(), DATE_ADD(NOW(), INTERVAL :ttl_seconds SECOND), :owner)
         ON DUPLICATE KEY UPDATE
           locked_at = IF(expires_at <= NOW(), VALUES(locked_at), locked_at),
           expires_at = IF(expires_at <= NOW(), VALUES(expires_at), expires_at),
           owner = IF(expires_at <= NOW(), VALUES(owner), owner)'
    );
    $stmt->execute([
        ':lock_key' => $lockKey,
        ':ttl_seconds' => max(1, $ttlSeconds),
        ':owner' => $owner,
    ]);

    return $stmt->rowCount() > 0;
}

function jq_release_lock(PDO $pdo, string $lockKey): void
{
    $stmt = $pdo->prepare('DELETE FROM job_locks WHERE lock_key=:lock_key');
    $stmt->execute([':lock_key' => $lockKey]);
}
