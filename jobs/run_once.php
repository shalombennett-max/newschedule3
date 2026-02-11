<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/job_lib.php';
require_once __DIR__ . '/worker.php';

schedule_require_auth(true);
schedule_require_manager_api('integrations');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    schedule_json_error('Method Not Allowed', 405);
}

$csrf = $_POST['csrf_token'] ?? '';
$sessionCsrf = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    schedule_json_error('Bad CSRF', 403);
}

$pdo = schedule_get_pdo();
if (!$pdo instanceof PDO) {
    schedule_json_error('Database unavailable.', 500);
}

$owner = 'web-' . (string)schedule_user_id();
if (!jq_acquire_lock($pdo, 'job_worker_global', 240, $owner)) {
    schedule_json_success(['message' => 'Worker already running.', 'processed' => 0]);
}

try {
    $result = jq_process_jobs($pdo, 2, $owner);
    schedule_json_success(['message' => 'Run once complete.', 'result' => $result]);
} finally {
    jq_release_lock($pdo, 'job_worker_global');
}
