<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/schedule/rules_engine.php';
schedule_require_auth(true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    schedule_json_error('Method Not Allowed', 405);
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if ($action === '') {
    schedule_json_error('Missing action', 422);
}

if ($action !== 'ping') {
    $csrf = $_POST['csrf_token'] ?? '';
    $sessionCsrf = $_SESSION['csrf_token'] ?? '';
    if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        schedule_json_error('Bad CSRF', 403);
    }
}

$resId = schedule_restaurant_id();
$userId = schedule_user_id();
$myStaffId = schedule_current_staff_id();
if ($resId === null || $userId === null || $myStaffId === null) {
    schedule_json_error('Unauthorized', 401);
}

function schedule_shift_by_id(int $resId, int $shiftId): ?array {
    return schedule_fetch_one('SELECT * FROM shifts WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $shiftId]);
}

function schedule_has_overlap(int $resId, int $staffId, string $startDt, string $endDt, ?int $excludeShiftId = null): bool {
    $sql = 'SELECT id FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt < :end_dt AND end_dt > :start_dt';
    $params = [':restaurant_id' => $resId, ':staff_id' => $staffId, ':start_dt' => $startDt, ':end_dt' => $endDt];
    if ($excludeShiftId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeShiftId;
    }
    return schedule_fetch_one($sql, $params) !== null;
}

function schedule_has_time_off_conflict(int $resId, int $staffId, string $startDt, string $endDt): bool {
    return schedule_fetch_one(
        'SELECT id FROM time_off_requests WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status="approved" AND start_dt < :end_dt AND end_dt > :start_dt',
        [':restaurant_id' => $resId, ':staff_id' => $staffId, ':start_dt' => $startDt, ':end_dt' => $endDt]
    ) !== null;
}

function schedule_table_has_columns(string $tableName, array $requiredColumns): bool {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO || $tableName === '' || $requiredColumns === []) {
        return false;
    }
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $tableName) . '`');
        if (!$stmt) {
            return false;
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = strtolower((string)($row['Field'] ?? ''));
        }
        foreach ($requiredColumns as $col) {
            if (!in_array(strtolower($col), $normalized, true)) {
                return false;
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}


function schedule_compliance_for_shift(int $restaurantId, array $shift): array {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO || !se_table_exists($pdo, 'schedule_policy_sets') || !se_table_exists($pdo, 'schedule_policies')) {
        return ['warnings' => [], 'blockers' => []];
    }
    $policySetId = se_get_active_policy_set_id($pdo, $restaurantId);
    $policies = se_load_policies($pdo, $restaurantId, $policySetId);
    $violations = se_check_shift($pdo, $restaurantId, $shift, $policies);
    $warnings = [];
    $blockers = [];
    foreach ($violations as $v) {
        if (($v['severity'] ?? 'warn') === 'block') {
            $blockers[] = $v;
        } else {
            $warnings[] = $v;
        }
    }
    return ['warnings' => $warnings, 'blockers' => $blockers];
}

function notify_user(PDO $pdo, int $restaurantId, int $userId, string $type, string $title, string $body, ?string $linkUrl = null): void {
    $stmt = $pdo->prepare('INSERT INTO notifications (restaurant_id, user_id, type, title, body, link_url, is_read, created_at)
                           VALUES (:restaurant_id, :user_id, :type, :title, :body, :link_url, 0, NOW())');
    if ($stmt) {
        $stmt->execute([
            ':restaurant_id' => $restaurantId,
            ':user_id' => $userId,
            ':type' => substr($type, 0, 64),
            ':title' => substr($title, 0, 140),
            ':body' => $body,
            ':link_url' => $linkUrl,
        ]);
    }
}

function manager_user_ids(int $resId): array {
    if (!schedule_table_has_columns('users', ['id', 'restaurant_id'])) {
        return [];
    }

    $rows = schedule_fetch_all(
        'SELECT id, role, is_manager, is_admin, can_manage_schedule FROM users WHERE restaurant_id=:restaurant_id',
        [':restaurant_id' => $resId]
    );
    $ids = [];
    foreach ($rows as $row) {
        $isMgr = in_array(strtolower((string)($row['role'] ?? '')), ['manager', 'admin', 'owner'], true)
            || (int)($row['is_manager'] ?? 0) === 1
            || (int)($row['is_admin'] ?? 0) === 1
            || (int)($row['can_manage_schedule'] ?? 0) === 1;
        if ($isMgr) {
            $ids[] = (int)$row['id'];
        }
    }
    return array_values(array_unique($ids));
}

if ($action === 'ping') {
    schedule_json_success(['pong' => true]);
}

if ($action === 'list_notifications') {
    $rows = schedule_fetch_all(
        'SELECT id, type, title, body, link_url, is_read, created_at
         FROM notifications
         WHERE restaurant_id=:restaurant_id AND user_id=:user_id
         ORDER BY created_at DESC
         LIMIT 200',
        [':restaurant_id' => $resId, ':user_id' => $userId]
    );
    $unread = schedule_fetch_one(
        'SELECT COUNT(*) AS c FROM notifications WHERE restaurant_id=:restaurant_id AND user_id=:user_id AND is_read=0',
        [':restaurant_id' => $resId, ':user_id' => $userId]
    );
    schedule_json_success(['notifications' => $rows, 'unread_count' => (int)($unread['c'] ?? 0)]);
}

if ($action === 'mark_notification_read') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        schedule_json_error('Invalid notification.', 422);
    }
    schedule_execute('UPDATE notifications SET is_read=1 WHERE restaurant_id=:restaurant_id AND user_id=:user_id AND id=:id', [
        ':restaurant_id' => $resId,
        ':user_id' => $userId,
        ':id' => $notificationId,
    ]);
    schedule_json_success(['message' => 'Notification marked read.']);
}

if ($action === 'mark_all_notifications_read') {
    schedule_execute('UPDATE notifications SET is_read=1 WHERE restaurant_id=:restaurant_id AND user_id=:user_id AND is_read=0', [
        ':restaurant_id' => $resId,
        ':user_id' => $userId,
    ]);
    schedule_json_success(['message' => 'All notifications marked read.']);
}

if ($action === 'list_roles') {
    schedule_json_success(['roles' => schedule_fetch_all('SELECT id,name,color,is_active,sort_order FROM roles WHERE restaurant_id=:restaurant_id ORDER BY sort_order ASC, name ASC', [':restaurant_id' => $resId])]);
}

if ($action === 'create_role') {
    schedule_require_manager_api();
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        schedule_json_error('Role name is required.', 422);
    }
    schedule_execute('INSERT INTO roles (restaurant_id,name,color,sort_order,is_active) VALUES (:restaurant_id,:name,:color,:sort_order,:is_active)', [
        ':restaurant_id' => $resId,
        ':name' => $name,
        ':color' => trim((string)($_POST['color'] ?? '')) ?: null,
        ':sort_order' => (int)($_POST['sort_order'] ?? 0),
        ':is_active' => (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0,
    ]);
    schedule_json_success(['message' => 'Role created.']);
}

if ($action === 'save_availability') {
    $staffId = (int)($_POST['staff_id'] ?? $myStaffId);
    if (!schedule_is_manager() && $staffId !== $myStaffId) {
        schedule_json_error('Forbidden', 403);
    }
    $day = (int)($_POST['day_of_week'] ?? -1);
    if ($day < 0 || $day > 6) {
        schedule_json_error('Invalid day.', 422);
    }
    $status = (string)($_POST['status'] ?? 'available');
    if (!in_array($status, ['available', 'preferred', 'unavailable'], true)) {
        schedule_json_error('Invalid status.', 422);
    }
    $start = schedule_time_or_null((string)($_POST['start_time'] ?? ''));
    $end = schedule_time_or_null((string)($_POST['end_time'] ?? ''));
    if ($start === null || $end === null || $end <= $start) {
        schedule_json_error('Invalid start/end time.', 422);
    }

    schedule_execute('DELETE FROM staff_availability WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND day_of_week=:day_of_week', [
        ':restaurant_id' => $resId,
        ':staff_id' => $staffId,
        ':day_of_week' => $day,
    ]);
    schedule_execute('INSERT INTO staff_availability (restaurant_id,staff_id,day_of_week,start_time,end_time,status,notes)
                      VALUES (:restaurant_id,:staff_id,:day_of_week,:start_time,:end_time,:status,:notes)', [
        ':restaurant_id' => $resId,
        ':staff_id' => $staffId,
        ':day_of_week' => $day,
        ':start_time' => $start,
        ':end_time' => $end,
        ':status' => $status,
        ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
    ]);
    schedule_json_success(['message' => 'Availability saved.']);
}

if ($action === 'create_shift' || $action === 'update_shift') {
    schedule_require_manager_api();
    $shiftId = $action === 'update_shift' ? (int)($_POST['shift_id'] ?? 0) : 0;
    if ($action === 'update_shift' && $shiftId <= 0) {
        schedule_json_error('Invalid shift.', 422);
    }
    $current = $shiftId > 0 ? schedule_shift_by_id($resId, $shiftId) : null;
    if ($shiftId > 0 && $current === null) {
        schedule_json_error('Shift not found.', 422);
    }

    $date = schedule_date((string)($_POST['date'] ?? substr((string)($current['start_dt'] ?? ''), 0, 10)), '');
    $startTime = schedule_time_or_null((string)($_POST['start_time'] ?? substr((string)($current['start_dt'] ?? ''), 11, 5)));
    $endTime = schedule_time_or_null((string)($_POST['end_time'] ?? substr((string)($current['end_dt'] ?? ''), 11, 5)));
    if ($date === '' || $startTime === null || $endTime === null) {
        schedule_json_error('Date, start, and end are required.', 422);
    }
    $startDt = $date . ' ' . substr($startTime, 0, 8);
    $endDt = $date . ' ' . substr($endTime, 0, 8);
    if ($endDt <= $startDt) {
        schedule_json_error('End must be after start.', 422);
    }

    $staffId = trim((string)($_POST['staff_id'] ?? ''));
    $staffId = $staffId === '' ? null : (int)$staffId;
    if ($staffId !== null && schedule_has_overlap($resId, $staffId, $startDt, $endDt, $shiftId > 0 ? $shiftId : null)) {
        schedule_json_error('Staff has an overlapping shift.', 422);
    }
    if ($staffId !== null && schedule_has_time_off_conflict($resId, $staffId, $startDt, $endDt)) {
        schedule_json_error('Staff has approved time off during this shift.', 422);
    }

    $complianceWarnings = [];
    if ($staffId !== null) {
        $compliance = schedule_compliance_for_shift($resId, [
            'id' => $shiftId,
            'staff_id' => $staffId,
            'start_dt' => $startDt,
            'end_dt' => $endDt,
            'break_minutes' => max(0, (int)($_POST['break_minutes'] ?? ($current['break_minutes'] ?? 0))),
        ]);
        if ($compliance['blockers'] !== []) {
            $first = $compliance['blockers'][0]['message'] ?? 'Blocking policy violation.';
            schedule_json_error_with_details((string)$first, 422, ['violations' => $compliance['blockers']]);
        }
        $complianceWarnings = $compliance['warnings'];
    }

    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        schedule_json_error('Database unavailable.', 500);
    }

    if ($shiftId > 0) {
        schedule_execute('UPDATE shifts SET staff_id=:staff_id, role_id=:role_id, start_dt=:start_dt, end_dt=:end_dt, break_minutes=:break_minutes, notes=:notes
                          WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':staff_id' => $staffId,
            ':role_id' => (int)($_POST['role_id'] ?? ($current['role_id'] ?? 0)) ?: null,
            ':start_dt' => $startDt,
            ':end_dt' => $endDt,
            ':break_minutes' => max(0, (int)($_POST['break_minutes'] ?? ($current['break_minutes'] ?? 0))),
            ':notes' => trim((string)($_POST['notes'] ?? (string)($current['notes'] ?? ''))) ?: null,
            ':restaurant_id' => $resId,
            ':id' => $shiftId,
        ]);
        if ($staffId !== null) {
            notify_user($pdo, $resId, $staffId, 'shift_changed', 'Shift updated', 'Your shift details were updated.', '/my.php?week_start=' . substr($startDt, 0, 10));
        }
        schedule_json_success(['message' => 'Shift updated.', 'warnings' => $complianceWarnings]);
    }

    schedule_execute('INSERT INTO shifts (restaurant_id,staff_id,role_id,start_dt,end_dt,break_minutes,notes,status)
                      VALUES (:restaurant_id,:staff_id,:role_id,:start_dt,:end_dt,:break_minutes,:notes,"draft")', [
        ':restaurant_id' => $resId,
        ':staff_id' => $staffId,
        ':role_id' => (int)($_POST['role_id'] ?? 0) ?: null,
        ':start_dt' => $startDt,
        ':end_dt' => $endDt,
        ':break_minutes' => max(0, (int)($_POST['break_minutes'] ?? 0)),
        ':notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
    ]);
    if ($staffId !== null) {
        notify_user($pdo, $resId, $staffId, 'shift_assigned', 'New shift assigned', 'You were assigned a new shift.', '/my.php?week_start=' . substr($startDt, 0, 10));
    }
    schedule_json_success(['message' => 'Shift created.', 'warnings' => $complianceWarnings]);
}

if ($action === 'delete_shift') {
    schedule_require_manager_api();
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    if ($shiftId <= 0 || !schedule_shift_by_id($resId, $shiftId)) {
        schedule_json_error('Shift not found.', 422);
    }
    schedule_execute('UPDATE shifts SET status="deleted" WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $shiftId]);
    schedule_json_success(['message' => 'Shift deleted.']);
}

if ($action === 'publish_week') {
    schedule_require_manager_api();
    $weekStart = schedule_date((string)($_POST['week_start'] ?? ''), '');
    if ($weekStart === '') {
        schedule_json_error('week_start is required.', 422);
    }
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');

    $pdo = schedule_get_pdo();
    $weekViolations = [];
    $blockers = [];
    $warnings = [];
    if ($pdo instanceof PDO && se_table_exists($pdo, 'schedule_policy_sets') && se_table_exists($pdo, 'schedule_policies')) {
        $policySetId = se_get_active_policy_set_id($pdo, $resId);
        $policies = se_load_policies($pdo, $resId, $policySetId);
        $weekViolations = se_check_week($pdo, $resId, $weekStart, $policies);
        foreach ($weekViolations as $v) {
            if (($v['severity'] ?? 'warn') === 'block') {
                $blockers[] = $v;
            } else {
                $warnings[] = $v;
            }
        }

        if (se_table_exists($pdo, 'schedule_violations')) {
            schedule_execute('DELETE FROM schedule_violations WHERE restaurant_id=:restaurant_id AND week_start_date=:week_start_date', [':restaurant_id' => $resId, ':week_start_date' => $weekStart]);
            foreach ($weekViolations as $v) {
                schedule_execute('INSERT INTO schedule_violations (restaurant_id, week_start_date, shift_id, staff_id, policy_key, severity, message, details_json, created_at)
                                  VALUES (:restaurant_id, :week_start_date, :shift_id, :staff_id, :policy_key, :severity, :message, :details_json, NOW())', [
                    ':restaurant_id' => $resId,
                    ':week_start_date' => $weekStart,
                    ':shift_id' => (int)($v['shift_id'] ?? 0) ?: null,
                    ':staff_id' => (int)($v['staff_id'] ?? 0) ?: null,
                    ':policy_key' => (string)($v['policy_key'] ?? ''),
                    ':severity' => (string)($v['severity'] ?? 'warn'),
                    ':message' => substr((string)($v['message'] ?? 'Policy violation'), 0, 255),
                    ':details_json' => json_encode($v['details'] ?? [], JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    if ($blockers !== []) {
        schedule_json_error_with_details('Publish blocked by compliance rules.', 422, ['blockers' => $blockers, 'warnings' => $warnings]);
    }

    schedule_execute('UPDATE shifts SET status="published" WHERE restaurant_id=:restaurant_id AND start_dt >= :week_start AND start_dt < :week_end AND status != "deleted"', [
        ':restaurant_id' => $resId,
        ':week_start' => $weekStart . ' 00:00:00',
        ':week_end' => $weekEnd . ' 00:00:00',
    ]);

    if ($pdo instanceof PDO) {
        $assigned = schedule_fetch_all(
            'SELECT DISTINCT staff_id FROM shifts WHERE restaurant_id=:restaurant_id AND status="published" AND start_dt >= :week_start AND start_dt < :week_end AND staff_id IS NOT NULL',
            [':restaurant_id' => $resId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00']
        );
        foreach ($assigned as $row) {
            notify_user($pdo, $resId, (int)$row['staff_id'], 'schedule_published', 'Schedule published', 'Your upcoming schedule is now published.', '/my.php?week_start=' . $weekStart);
        }
    }
    schedule_json_success(['message' => 'Week published.', 'warnings' => $warnings]);
}

if ($action === 'mark_shift_open') {
    schedule_require_manager_api();
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $shift = $shiftId > 0 ? schedule_shift_by_id($resId, $shiftId) : null;
    if ($shift === null || (string)$shift['status'] === 'deleted') {
        schedule_json_error('Shift not found.', 422);
    }
    schedule_execute('UPDATE shifts SET staff_id = NULL WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $shiftId]);
    schedule_json_success(['message' => 'Shift marked open.']);
}

if ($action === 'request_pickup') {
    if (schedule_is_manager()) {
        schedule_json_error('Forbidden', 403);
    }
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $shift = $shiftId > 0 ? schedule_shift_by_id($resId, $shiftId) : null;
    if ($shift === null || (string)$shift['status'] === 'deleted' || !empty($shift['staff_id'])) {
        schedule_json_error('Shift is not open for pickup.', 422);
    }
    $existing = schedule_fetch_one('SELECT id FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND staff_id=:staff_id AND status="pending"', [
        ':restaurant_id' => $resId,
        ':shift_id' => $shiftId,
        ':staff_id' => $myStaffId,
    ]);
    if ($existing !== null) {
        schedule_json_error('Pickup request already pending.', 422);
    }
    schedule_execute('INSERT INTO shift_pickup_requests (restaurant_id,shift_id,staff_id,status,created_at) VALUES (:restaurant_id,:shift_id,:staff_id,"pending",NOW())', [
        ':restaurant_id' => $resId,
        ':shift_id' => $shiftId,
        ':staff_id' => $myStaffId,
    ]);
    schedule_json_success(['message' => 'Pickup request submitted.']);
}

if ($action === 'list_open_shifts') {
    if (schedule_is_manager()) {
        schedule_json_error('Forbidden', 403);
    }
    $startDate = schedule_date((string)($_POST['start_date'] ?? date('Y-m-d')), date('Y-m-d'));
    $days = max(1, min(30, (int)($_POST['days'] ?? 14)));
    $endDate = (new DateTimeImmutable($startDate))->modify('+' . $days . ' days')->format('Y-m-d');

    $rows = schedule_fetch_all(
        'SELECT s.id,s.role_id,s.start_dt,s.end_dt,s.break_minutes,s.notes,s.status,r.name AS role_name,pr.status AS my_request_status
         FROM shifts s
         LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
         LEFT JOIN shift_pickup_requests pr ON pr.restaurant_id=s.restaurant_id AND pr.shift_id=s.id AND pr.staff_id=:staff_id
         WHERE s.restaurant_id=:restaurant_id AND s.staff_id IS NULL AND s.status IN ("draft","published")
           AND s.start_dt >= :start_dt AND s.start_dt < :end_dt
         ORDER BY s.start_dt ASC',
        [
            ':restaurant_id' => $resId,
            ':staff_id' => $myStaffId,
            ':start_dt' => $startDate . ' 00:00:00',
            ':end_dt' => $endDate . ' 00:00:00',
        ]
    );
    schedule_json_success(['open_shifts' => $rows]);
}

if ($action === 'list_pickup_requests') {
    schedule_require_manager_api();
    $rows = schedule_fetch_all(
        'SELECT pr.id,pr.shift_id,pr.staff_id,pr.status,pr.created_at,s.start_dt,s.end_dt,r.name AS role_name
         FROM shift_pickup_requests pr
         INNER JOIN shifts s ON s.restaurant_id=pr.restaurant_id AND s.id=pr.shift_id
         LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
         WHERE pr.restaurant_id=:restaurant_id
         ORDER BY pr.created_at DESC',
        [':restaurant_id' => $resId]
    );
    schedule_json_success(['pickup_requests' => $rows]);
}

if ($action === 'approve_pickup') {
    schedule_require_manager_api();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $request = schedule_fetch_one('SELECT * FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    if ($request === null || (string)$request['status'] !== 'pending') {
        schedule_json_error('Request is no longer pending.', 422);
    }
    $shift = schedule_shift_by_id($resId, (int)$request['shift_id']);
    if ($shift === null || !empty($shift['staff_id'])) {
        schedule_json_error('Shift is no longer open.', 422);
    }
    $staffId = (int)$request['staff_id'];
    if (schedule_has_overlap($resId, $staffId, (string)$shift['start_dt'], (string)$shift['end_dt']) || schedule_has_time_off_conflict($resId, $staffId, (string)$shift['start_dt'], (string)$shift['end_dt'])) {
        schedule_json_error('Staff is no longer eligible for this shift.', 422);
    }
    $pickupCompliance = schedule_compliance_for_shift($resId, [
        'id' => (int)$shift['id'],
        'staff_id' => $staffId,
        'start_dt' => (string)$shift['start_dt'],
        'end_dt' => (string)$shift['end_dt'],
        'break_minutes' => (int)($shift['break_minutes'] ?? 0),
    ]);
    if ($pickupCompliance['blockers'] !== []) {
        schedule_json_error_with_details('Pickup cannot be approved due to blocking policy violations.', 422, ['violations' => $pickupCompliance['blockers']]);
    }

    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        schedule_json_error('Database unavailable.', 500);
    }
    $pdo->beginTransaction();
    try {
        schedule_execute('UPDATE shifts SET staff_id=:staff_id WHERE restaurant_id=:restaurant_id AND id=:id AND staff_id IS NULL', [
            ':staff_id' => $staffId,
            ':restaurant_id' => $resId,
            ':id' => (int)$request['shift_id'],
        ]);
        schedule_execute('UPDATE shift_pickup_requests SET status="approved", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
        $pendingOthers = schedule_fetch_all('SELECT id, staff_id FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND status="pending" AND id != :id', [
            ':restaurant_id' => $resId,
            ':shift_id' => (int)$request['shift_id'],
            ':id' => $requestId,
        ]);
        schedule_execute('UPDATE shift_pickup_requests SET status="denied", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND status="pending" AND id != :id', [
            ':restaurant_id' => $resId,
            ':shift_id' => (int)$request['shift_id'],
            ':id' => $requestId,
        ]);
        notify_user($pdo, $resId, $staffId, 'pickup_approved', 'Shift pickup approved', 'Your pickup request was approved.', '/my.php');
        foreach ($pendingOthers as $other) {
            notify_user($pdo, $resId, (int)$other['staff_id'], 'pickup_denied', 'Shift pickup denied', 'Another staff member was assigned this shift.', '/my.php');
        }
        $callout = schedule_fetch_one('SELECT id FROM callouts WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND status IN ("reported","coverage_requested") ORDER BY id DESC', [
            ':restaurant_id' => $resId,
            ':shift_id' => (int)$request['shift_id'],
        ]);
        if ($callout !== null) {
            schedule_execute('UPDATE callouts SET status="covered", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => (int)$callout['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        schedule_json_error('Could not approve pickup request.', 500);
    }
    schedule_json_success(['message' => 'Pickup request approved.']);
}

if ($action === 'deny_pickup') {
    schedule_require_manager_api();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $request = schedule_fetch_one('SELECT id,status FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    if ($request === null || (string)$request['status'] !== 'pending') {
        schedule_json_error('Request is no longer pending.', 422);
    }
    schedule_execute('UPDATE shift_pickup_requests SET status="denied", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    schedule_json_success(['message' => 'Pickup request denied.']);
}

if ($action === 'create_time_off') {
    $staffId = $myStaffId;
    if (schedule_is_manager() && isset($_POST['staff_id']) && $_POST['staff_id'] !== '') {
        $staffId = (int)$_POST['staff_id'];
    }
    if (!schedule_is_manager() && $staffId !== $myStaffId) {
        schedule_json_error('Forbidden', 403);
    }
    $start = trim((string)($_POST['start_dt'] ?? ''));
    $end = trim((string)($_POST['end_dt'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($start === '' || $end === '' || $reason === '' || $end <= $start) {
        schedule_json_error('Invalid time off request.', 422);
    }
    schedule_execute('INSERT INTO time_off_requests (restaurant_id,staff_id,start_dt,end_dt,reason,status,created_at) VALUES (:restaurant_id,:staff_id,:start_dt,:end_dt,:reason,"pending",NOW())', [
        ':restaurant_id' => $resId,
        ':staff_id' => $staffId,
        ':start_dt' => $start,
        ':end_dt' => $end,
        ':reason' => $reason,
    ]);
    schedule_json_success(['message' => 'Time-off request submitted.']);
}

if ($action === 'review_time_off') {
    schedule_require_manager_api();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    if (!in_array($decision, ['approved', 'denied'], true)) {
        schedule_json_error('Invalid decision.', 422);
    }
    $request = schedule_fetch_one('SELECT id,staff_id,status FROM time_off_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    if ($request === null || (string)$request['status'] !== 'pending') {
        schedule_json_error('Request not pending.', 422);
    }
    schedule_execute('UPDATE time_off_requests SET status=:status, reviewed_by=:reviewed_by, reviewed_at=NOW(), review_note=:review_note WHERE restaurant_id=:restaurant_id AND id=:id', [
        ':status' => $decision,
        ':reviewed_by' => $userId,
        ':review_note' => trim((string)($_POST['review_note'] ?? '')) ?: null,
        ':restaurant_id' => $resId,
        ':id' => $requestId,
    ]);
    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        notify_user($pdo, $resId, (int)$request['staff_id'], 'time_off_' . $decision, 'Time-off request ' . $decision, 'Your time-off request was ' . $decision . '.', '/time_off.php');
    }
    schedule_json_success(['message' => 'Time-off request reviewed.']);
}

if ($action === 'list_announcements') {
    $now = date('Y-m-d H:i:s');
    $whereAudience = schedule_is_manager() ? '' : ' AND (a.audience="all" OR a.audience="staff" OR a.audience="role:staff")';
    $rows = schedule_fetch_all(
        'SELECT a.id,a.title,a.body,a.audience,a.starts_at,a.ends_at,a.created_by,a.created_at
         FROM announcements a
         WHERE a.restaurant_id=:restaurant_id
           AND (a.starts_at IS NULL OR a.starts_at <= :now)
           AND (a.ends_at IS NULL OR a.ends_at >= :now)' . $whereAudience . '
         ORDER BY a.created_at DESC',
        [':restaurant_id' => $resId, ':now' => $now]
    );
    schedule_json_success(['announcements' => $rows]);
}

if ($action === 'create_announcement' || $action === 'update_announcement' || $action === 'delete_announcement') {
    schedule_require_manager_api();
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        schedule_json_error('Database unavailable.', 500);
    }

    if ($action === 'delete_announcement') {
        $id = (int)($_POST['announcement_id'] ?? 0);
        schedule_execute('DELETE FROM announcements WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
        schedule_json_success(['message' => 'Announcement deleted.']);
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? 'all'));
    $startsAt = trim((string)($_POST['starts_at'] ?? '')) ?: null;
    $endsAt = trim((string)($_POST['ends_at'] ?? '')) ?: null;
    if ($title === '' || $body === '') {
        schedule_json_error('Title and body are required.', 422);
    }

    if ($action === 'create_announcement') {
        schedule_execute('INSERT INTO announcements (restaurant_id,title,body,audience,starts_at,ends_at,created_by,created_at)
                          VALUES (:restaurant_id,:title,:body,:audience,:starts_at,:ends_at,:created_by,NOW())', [
            ':restaurant_id' => $resId,
            ':title' => $title,
            ':body' => $body,
            ':audience' => $audience,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
            ':created_by' => $userId,
        ]);

        $aud = strtolower($audience);
        $staffUsers = schedule_staff_options($resId);
        foreach ($staffUsers as $staff) {
            $uid = (int)$staff['id'];
            $send = $aud === 'all' || $aud === 'staff' || ($aud === 'managers' && in_array($uid, manager_user_ids($resId), true)) || str_starts_with($aud, 'role:');
            if ($send) {
                notify_user($pdo, $resId, $uid, 'announcement', 'New announcement: ' . $title, $body, '/announcements.php');
            }
        }
        schedule_json_success(['message' => 'Announcement created.']);
    }

    $id = (int)($_POST['announcement_id'] ?? 0);
    schedule_execute('UPDATE announcements SET title=:title,body=:body,audience=:audience,starts_at=:starts_at,ends_at=:ends_at WHERE restaurant_id=:restaurant_id AND id=:id', [
        ':title' => $title,
        ':body' => $body,
        ':audience' => $audience,
        ':starts_at' => $startsAt,
        ':ends_at' => $endsAt,
        ':restaurant_id' => $resId,
        ':id' => $id,
    ]);
    schedule_json_success(['message' => 'Announcement updated.']);
}

if ($action === 'create_swap_request') {
    if (schedule_is_manager()) {
        schedule_json_error('Staff only.', 403);
    }
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $toStaffRaw = trim((string)($_POST['to_staff_id'] ?? ''));
    $toStaffId = $toStaffRaw === '' ? null : (int)$toStaffRaw;
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;

    $shift = schedule_shift_by_id($resId, $shiftId);
    if ($shift === null || (int)$shift['staff_id'] !== $myStaffId || (string)$shift['status'] !== 'published') {
        schedule_json_error('You can only request swaps for your own published shifts.', 422);
    }

    schedule_execute('INSERT INTO shift_trade_requests (restaurant_id,shift_id,from_staff_id,to_staff_id,status,notes,created_at)
                      VALUES (:restaurant_id,:shift_id,:from_staff_id,:to_staff_id,"pending",:notes,NOW())', [
        ':restaurant_id' => $resId,
        ':shift_id' => $shiftId,
        ':from_staff_id' => $myStaffId,
        ':to_staff_id' => $toStaffId,
        ':notes' => $notes,
    ]);

    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        foreach (manager_user_ids($resId) as $managerId) {
            notify_user($pdo, $resId, $managerId, 'swap_requested', 'New swap request', 'A staff member requested a shift swap.', '/swaps.php');
        }
        if ($toStaffId !== null) {
            notify_user($pdo, $resId, $toStaffId, 'swap_requested', 'Swap request sent to you', 'A teammate requested to swap a shift with you.', '/my.php');
        }
    }
    schedule_json_success(['message' => 'Swap request submitted.']);
}

if ($action === 'cancel_swap_request') {
    $id = (int)($_POST['request_id'] ?? 0);
    $request = schedule_fetch_one('SELECT * FROM shift_trade_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    if ($request === null || (int)$request['from_staff_id'] !== $myStaffId || (string)$request['status'] !== 'pending') {
        schedule_json_error('Swap request cannot be cancelled.', 422);
    }
    schedule_execute('UPDATE shift_trade_requests SET status="cancelled" WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    schedule_json_success(['message' => 'Swap request cancelled.']);
}

if ($action === 'list_swap_requests') {
    $params = [':restaurant_id' => $resId];
    $sql = 'SELECT tr.*, s.start_dt, s.end_dt, s.role_id, r.name AS role_name
            FROM shift_trade_requests tr
            INNER JOIN shifts s ON s.restaurant_id=tr.restaurant_id AND s.id=tr.shift_id
            LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
            WHERE tr.restaurant_id=:restaurant_id';
    if (!schedule_is_manager()) {
        $sql .= ' AND (tr.from_staff_id=:my_staff OR tr.to_staff_id=:my_staff)';
        $params[':my_staff'] = $myStaffId;
    }
    $sql .= ' ORDER BY tr.created_at DESC';
    schedule_json_success(['swap_requests' => schedule_fetch_all($sql, $params)]);
}

if ($action === 'approve_swap_request' || $action === 'deny_swap_request') {
    schedule_require_manager_api();
    $id = (int)($_POST['request_id'] ?? 0);
    $request = schedule_fetch_one('SELECT * FROM shift_trade_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    if ($request === null || (string)$request['status'] !== 'pending') {
        schedule_json_error('Swap request not pending.', 422);
    }

    if ($action === 'deny_swap_request') {
        schedule_execute('UPDATE shift_trade_requests SET status="denied", reviewed_by=:reviewed_by, reviewed_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':reviewed_by' => $userId,
            ':restaurant_id' => $resId,
            ':id' => $id,
        ]);
        $pdo = schedule_get_pdo();
        if ($pdo instanceof PDO) {
            notify_user($pdo, $resId, (int)$request['from_staff_id'], 'swap_denied', 'Swap request denied', 'Your swap request was denied.', '/my.php');
        }
        schedule_json_success(['message' => 'Swap request denied.']);
    }

    $shift = schedule_shift_by_id($resId, (int)$request['shift_id']);
    if ($shift === null || (int)$shift['staff_id'] !== (int)$request['from_staff_id']) {
        schedule_json_error('Shift assignment changed, cannot approve.', 422);
    }

    $targetStaff = $request['to_staff_id'] !== null ? (int)$request['to_staff_id'] : null;
    if ($targetStaff === null) {
        schedule_execute('UPDATE shifts SET staff_id=NULL WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => (int)$request['shift_id']]);
    } else {
        if (schedule_has_overlap($resId, $targetStaff, (string)$shift['start_dt'], (string)$shift['end_dt'], (int)$shift['id']) || schedule_has_time_off_conflict($resId, $targetStaff, (string)$shift['start_dt'], (string)$shift['end_dt'])) {
            schedule_json_error('Target staff member is no longer eligible.', 422);
        }
        $swapCompliance = schedule_compliance_for_shift($resId, [
            'id' => (int)$shift['id'],
            'staff_id' => $targetStaff,
            'start_dt' => (string)$shift['start_dt'],
            'end_dt' => (string)$shift['end_dt'],
            'break_minutes' => (int)($shift['break_minutes'] ?? 0),
        ]);
        if ($swapCompliance['blockers'] !== []) {
            schedule_json_error_with_details('Swap cannot be approved due to blocking policy violations.', 422, ['violations' => $swapCompliance['blockers']]);
        }
        schedule_execute('UPDATE shifts SET staff_id=:staff_id WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':staff_id' => $targetStaff,
            ':restaurant_id' => $resId,
            ':id' => (int)$request['shift_id'],
        ]);
    }
    schedule_execute('UPDATE shift_trade_requests SET status="approved", reviewed_by=:reviewed_by, reviewed_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [
        ':reviewed_by' => $userId,
        ':restaurant_id' => $resId,
        ':id' => $id,
    ]);

    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        notify_user($pdo, $resId, (int)$request['from_staff_id'], 'swap_approved', 'Swap request approved', 'Your swap request was approved.', '/my.php');
        if ($targetStaff !== null) {
            notify_user($pdo, $resId, $targetStaff, 'swap_approved', 'You were assigned a swapped shift', 'A manager approved a swap and assigned you this shift.', '/my.php');
        }
    }
    schedule_json_success(['message' => 'Swap request approved.']);
}

if ($action === 'create_callout') {
    if (schedule_is_manager()) {
        schedule_json_error('Staff only.', 403);
    }
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? '')) ?: null;
    $shift = schedule_shift_by_id($resId, $shiftId);
    if ($shift === null || (int)$shift['staff_id'] !== $myStaffId || (string)$shift['status'] === 'deleted') {
        schedule_json_error('Call-out allowed only for your own shifts.', 422);
    }
    schedule_execute('INSERT INTO callouts (restaurant_id,shift_id,staff_id,reason,status,created_at,updated_at)
                      VALUES (:restaurant_id,:shift_id,:staff_id,:reason,"reported",NOW(),NOW())', [
        ':restaurant_id' => $resId,
        ':shift_id' => $shiftId,
        ':staff_id' => $myStaffId,
        ':reason' => $reason,
    ]);
    $pdo = schedule_get_pdo();
    if ($pdo instanceof PDO) {
        foreach (manager_user_ids($resId) as $managerId) {
            notify_user($pdo, $resId, $managerId, 'callout_reported', 'Staff call-out reported', 'A staff member called out for an upcoming shift.', '/index.php');
        }
    }
    schedule_json_success(['message' => 'Call-out reported.']);
}

if ($action === 'list_callouts') {
    if (!schedule_is_manager()) {
        schedule_json_error('Forbidden', 403);
    }
    $rows = schedule_fetch_all(
        'SELECT c.*, s.start_dt, s.end_dt, s.role_id, r.name AS role_name
         FROM callouts c
         INNER JOIN shifts s ON s.restaurant_id=c.restaurant_id AND s.id=c.shift_id
         LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
         WHERE c.restaurant_id=:restaurant_id
         ORDER BY c.created_at DESC',
        [':restaurant_id' => $resId]
    );
    schedule_json_success(['callouts' => $rows]);
}

if ($action === 'request_coverage') {
    schedule_require_manager_api();
    $id = (int)($_POST['callout_id'] ?? 0);
    $callout = schedule_fetch_one('SELECT * FROM callouts WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    if ($callout === null) {
        schedule_json_error('Call-out not found.', 422);
    }
    schedule_execute('UPDATE callouts SET status="coverage_requested", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    schedule_execute('UPDATE shifts SET staff_id=NULL WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => (int)$callout['shift_id']]);
    schedule_json_success(['message' => 'Coverage requested and shift moved to marketplace.']);
}

if ($action === 'close_callout') {
    schedule_require_manager_api();
    $id = (int)($_POST['callout_id'] ?? 0);
    schedule_execute('UPDATE callouts SET status="manager_closed", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $id]);
    schedule_json_success(['message' => 'Call-out closed.']);
}


if ($action === 'update_policy_set' || $action === 'reset_policy_set_defaults') {
    schedule_require_manager_api();
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO || !se_table_exists($pdo, 'schedule_policy_sets') || !se_table_exists($pdo, 'schedule_policies')) {
        schedule_json_error('Policy tables are unavailable.', 500);
    }
    $policySetId = (int)($_POST['policy_set_id'] ?? 0);
    if ($policySetId <= 0) {
        $policySetId = se_get_active_policy_set_id($pdo, $resId);
    }

    if ($action === 'reset_policy_set_defaults') {
        se_reset_policy_set_defaults($pdo, $resId, $policySetId);
        schedule_json_success(['message' => 'Policy defaults restored.']);
    }

    $defaults = se_default_policy_config();
    $rows = $_POST['policies'] ?? [];
    if (!is_array($rows)) {
        schedule_json_error('Invalid policy payload.', 422);
    }

    $pdo->beginTransaction();
    try {
        schedule_execute('DELETE FROM schedule_policies WHERE restaurant_id=:restaurant_id AND policy_set_id=:policy_set_id', [':restaurant_id' => $resId, ':policy_set_id' => $policySetId]);
        foreach ($defaults as $key => $cfg) {
            $in = is_array($rows[$key] ?? null) ? $rows[$key] : [];
            $enabled = (int)($in['enabled'] ?? 0) === 1 ? 1 : 0;
            $mode = ((string)($in['mode'] ?? 'warn')) === 'block' ? 'block' : 'warn';
            $params = $cfg['params'];
            foreach ($params as $pk => $pv) {
                if (isset($in['params'][$pk])) {
                    $params[$pk] = is_numeric($in['params'][$pk]) ? (0 + $in['params'][$pk]) : $in['params'][$pk];
                }
            }
            schedule_execute('INSERT INTO schedule_policies (restaurant_id, policy_set_id, policy_key, enabled, mode, params_json, created_at)
                              VALUES (:restaurant_id, :policy_set_id, :policy_key, :enabled, :mode, :params_json, NOW())', [
                ':restaurant_id' => $resId,
                ':policy_set_id' => $policySetId,
                ':policy_key' => $key,
                ':enabled' => $enabled,
                ':mode' => $mode,
                ':params_json' => json_encode($params, JSON_UNESCAPED_SLASHES),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        schedule_json_error('Could not update policy set.', 500);
    }

    schedule_json_success(['message' => 'Policy set updated.']);
}

if ($action === 'generate_enforcement_events') {
    schedule_require_manager_api();
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO || !se_table_exists($pdo, 'schedule_enforcement_events')) {
        schedule_json_error('Enforcement events table unavailable.', 500);
    }
    $weekStart = schedule_date((string)($_POST['week_start'] ?? ''), '');
    if ($weekStart === '') {
        schedule_json_error('week_start is required.', 422);
    }
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
    schedule_execute('DELETE FROM schedule_enforcement_events WHERE restaurant_id=:restaurant_id AND event_dt >= :week_start AND event_dt < :week_end', [
        ':restaurant_id' => $resId,
        ':week_start' => $weekStart . ' 00:00:00',
        ':week_end' => $weekEnd . ' 00:00:00',
    ]);

    $punches = schedule_fetch_all('SELECT l.external_employee_id, pm.internal_id AS staff_id, l.punch_in_dt, l.punch_out_dt
                                   FROM aloha_labor_punches_stage l
                                   INNER JOIN pos_mappings pm ON pm.restaurant_id=l.restaurant_id AND pm.provider="aloha" AND pm.type="employee" AND pm.external_id=l.external_employee_id
                                   WHERE l.restaurant_id=:restaurant_id AND l.punch_in_dt >= :week_start AND l.punch_in_dt < :week_end',
        [':restaurant_id' => $resId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00']);

    $count = 0;
    foreach ($punches as $p) {
        $staffId = (int)$p['staff_id'];
        $pin = (string)$p['punch_in_dt'];
        $match = schedule_fetch_one('SELECT id,start_dt,end_dt FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt <= DATE_ADD(:pin, INTERVAL 30 MINUTE) AND end_dt >= DATE_SUB(:pin, INTERVAL 30 MINUTE) ORDER BY ABS(TIMESTAMPDIFF(MINUTE, start_dt, :pin)) ASC LIMIT 1',
            [':restaurant_id' => $resId, ':staff_id' => $staffId, ':pin' => $pin]);

        $events = [];
        if ($match === null) {
            $events[] = ['unscheduled_punch', null, 'Punch has no matching scheduled shift (+/- 30 min).'];
        } else {
            $delta = (int)round((strtotime($pin) - strtotime((string)$match['start_dt'])) / 60);
            if ($delta < -10) {
                $events[] = ['early_punch', (int)$match['id'], 'Punch-in is ' . abs($delta) . ' minutes early.'];
            }
            if ($delta > 10) {
                $events[] = ['late_punch', (int)$match['id'], 'Punch-in is ' . $delta . ' minutes late.'];
            }
        }

        foreach ($events as [$type, $shiftId, $msg]) {
            schedule_execute('INSERT INTO schedule_enforcement_events (restaurant_id,event_type,staff_id,shift_id,external_employee_id,event_dt,message,details_json,created_at)
                              VALUES (:restaurant_id,:event_type,:staff_id,:shift_id,:external_employee_id,:event_dt,:message,:details_json,NOW())', [
                ':restaurant_id' => $resId,
                ':event_type' => $type,
                ':staff_id' => $staffId ?: null,
                ':shift_id' => $shiftId,
                ':external_employee_id' => (string)($p['external_employee_id'] ?? ''),
                ':event_dt' => $pin,
                ':message' => $msg,
                ':details_json' => json_encode(['punch_in_dt' => $pin], JSON_UNESCAPED_SLASHES),
            ]);
            $count++;
        }
    }
    schedule_json_success(['message' => 'Enforcement events generated.', 'count' => $count]);
}

schedule_json_error('Unknown action', 404);