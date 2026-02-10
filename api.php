<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth(true);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if ($action === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action']);
    exit;
}

if ($action !== 'ping') {
    $csrf = $_POST['csrf_token'] ?? '';
    $sessionCsrf = $_SESSION['csrf_token'] ?? '';
    if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Bad CSRF']);
        exit;
    }
}

$resId = schedule_restaurant_id();
if ($resId === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($action === 'ping') {
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list_roles') {
    $rows = schedule_fetch_all(
        'SELECT id, restaurant_id, name, color_hex, is_active FROM roles WHERE restaurant_id = :restaurant_id ORDER BY name ASC',
        [':restaurant_id' => $resId]
    );
    echo json_encode(['success' => true, 'roles' => $rows]);
    exit;
}

if ($action === 'list_shifts') {
    $week = schedule_week_window(isset($_POST['week_start']) && is_string($_POST['week_start']) ? $_POST['week_start'] : null);
    $rows = schedule_fetch_all(
        'SELECT id, restaurant_id, staff_id, role_id, shift_date, start_time, end_time, status FROM shifts WHERE restaurant_id = :restaurant_id AND shift_date BETWEEN :start_date AND :end_date ORDER BY shift_date ASC, start_time ASC',
        [
            ':restaurant_id' => $resId,
            ':start_date' => $week['start'],
            ':end_date' => $week['end'],
        ]
    );
    echo json_encode(['success' => true, 'week_start' => $week['start'], 'week_end' => $week['end'], 'shifts' => $rows]);
    exit;
}

if ($action === 'list_time_off') {
    $startDefault = (new DateTimeImmutable('today'))->format('Y-m-d');
    $endDefault = (new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
    $start = schedule_date(isset($_POST['start_date']) && is_string($_POST['start_date']) ? $_POST['start_date'] : '', $startDefault);
    $end = schedule_date(isset($_POST['end_date']) && is_string($_POST['end_date']) ? $_POST['end_date'] : '', $endDefault);

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $rows = schedule_fetch_all(
        'SELECT id, restaurant_id, staff_id, start_date, end_date, status, note FROM time_off_requests WHERE restaurant_id = :restaurant_id AND start_date <= :end_date AND end_date >= :start_date ORDER BY start_date ASC',
        [
            ':restaurant_id' => $resId,
            ':start_date' => $start,
            ':end_date' => $end,
        ]
    );

    echo json_encode(['success' => true, 'start_date' => $start, 'end_date' => $end, 'time_off_requests' => $rows]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);