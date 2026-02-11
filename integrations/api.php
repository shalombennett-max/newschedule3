<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/aloha_helpers.php';
require_once __DIR__ . '/../jobs/job_lib.php';
require_once __DIR__ . '/../schedule/audit.php';

schedule_require_auth(true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    schedule_json_error('Method Not Allowed', 405);
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if ($action === '') {
    schedule_json_error('Missing action', 422);
}

$csrf = $_POST['csrf_token'] ?? '';
$sessionCsrf = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    schedule_json_error('Bad CSRF', 403);
}

$resId = schedule_restaurant_id();
$userId = schedule_user_id();
if ($resId === null || $userId === null) {
    schedule_json_error('Unauthorized', 401);
}

if (strpos($action, 'aloha_') === 0) {
    schedule_handle_aloha_api($action, $resId, $userId);
}

schedule_require_manager_api('integrations');
if ($action === 'job_retry') {␊
    $jobId = (int)($_POST['job_id'] ?? 0);
    if ($jobId <= 0) {
        schedule_json_error('Invalid job id.', 422);
    }
    $before = schedule_fetch_one('SELECT id,status,restaurant_id FROM job_queue WHERE id=:id AND (restaurant_id=:restaurant_id OR restaurant_id IS NULL)', [':id' => $jobId, ':restaurant_id' => $resId]);
    if ($before === null) {
        schedule_json_error('Job not found.', 404);
    }
    schedule_execute('UPDATE job_queue SET status="queued", run_after=NOW(), finished_at=NULL WHERE id=:id AND (restaurant_id=:restaurant_id OR restaurant_id IS NULL)', [':id' => $jobId, ':restaurant_id' => $resId]);
    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        schedule_audit_log($pdo, $resId, $userId, 'job_retry', 'job', (string)$jobId, $before, ['status' => 'queued']);
    }
    schedule_json_success(['message' => 'Job queued for retry.']);
}

if ($action === 'job_cancel') {
    $jobId = (int)($_POST['job_id'] ?? 0);
    if ($jobId <= 0) {
        schedule_json_error('Invalid job id.', 422);
    }
    $before = schedule_fetch_one('SELECT id,status,restaurant_id FROM job_queue WHERE id=:id AND (restaurant_id=:restaurant_id OR restaurant_id IS NULL) AND status="queued"', [':id' => $jobId, ':restaurant_id' => $resId]);
    if ($before === null) {
        schedule_json_error('Job not found.', 404);
    }
    schedule_execute('UPDATE job_queue SET status="cancelled", finished_at=NOW() WHERE id=:id AND (restaurant_id=:restaurant_id OR restaurant_id IS NULL) AND status="queued"', [':id' => $jobId, ':restaurant_id' => $resId]);
    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        schedule_audit_log($pdo, $resId, $userId, 'job_cancel', 'job', (string)$jobId, $before, ['status' => 'cancelled']);
    }
    schedule_json_success(['message' => 'Job cancelled.']);
}

schedule_json_error('Unknown action.', 422);