<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
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

function schedule_role_exists(int $resId, int $roleId): bool {
    return schedule_fetch_one('SELECT id FROM roles WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$roleId]) !== null;
}

function schedule_shift_by_id(int $resId, int $shiftId): ?array {
    return schedule_fetch_one('SELECT * FROM shifts WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$shiftId]);
}

function schedule_time_off_by_id(int $resId, int $id): ?array {
    return schedule_fetch_one('SELECT * FROM time_off_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$id]);
}

function schedule_has_overlap(int $resId, int $staffId, string $startDt, string $endDt, ?int $excludeShiftId = null): bool {
    $sql = 'SELECT id FROM shifts WHERE restaurant_id = :restaurant_id AND staff_id = :staff_id
            AND status != "deleted" AND start_dt < :end_dt AND end_dt > :start_dt';
    $params = [':restaurant_id' => $resId, ':staff_id' => $staffId, ':start_dt' => $startDt, ':end_dt' => $endDt];
    if ($excludeShiftId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeShiftId;
    }
    return schedule_fetch_one($sql, $params) !== null;
}

function schedule_has_time_off_conflict(int $resId, int $staffId, string $startDt, string $endDt, ?int $excludeShiftId = null): bool {
    $row = schedule_fetch_one(
        'SELECT id FROM time_off_requests WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id
         AND status="approved" AND start_dt < :end_dt AND end_dt > :start_dt',
        [':restaurant_id'=>$resId, ':staff_id'=>$staffId, ':start_dt'=>$startDt, ':end_dt'=>$endDt]
    );
    return $row !== null;
}

if ($action === 'ping') {
    schedule_json_success(['pong' => true]);
}

if ($action === 'list_roles') {
    $rows = schedule_fetch_all(
        'SELECT id, restaurant_id, name, color, sort_order, is_active FROM roles WHERE restaurant_id = :restaurant_id ORDER BY sort_order ASC, name ASC',
        [':restaurant_id' => $resId]
    );
    schedule_json_success(['roles' => $rows]);
}

if ($action === 'create_role') {
    schedule_require_manager_api();
    $name = trim((string)($_POST['name'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $sort = (int)($_POST['sort_order'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
    if ($name === '') {
        schedule_json_error('Role name is required.', 422);
    }
    try {
        $ok = schedule_execute('INSERT INTO roles (restaurant_id, name, color, sort_order, is_active) VALUES (:restaurant_id,:name,:color,:sort_order,:is_active)', [
            ':restaurant_id' => $resId,
            ':name' => $name,
            ':color' => $color !== '' ? $color : null,
            ':sort_order' => $sort,
            ':is_active' => $isActive,
        ]);
        if (!$ok) {
            schedule_json_error('Could not create role.', 500);
        }
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'uniq_roles_restaurant_name') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
            schedule_json_error('Role name already exists for this restaurant.', 422);
        }
        schedule_json_error('Could not create role.', 500);
    }
    schedule_json_success(['message' => 'Role created.']);
}

if ($action === 'update_role') {
    schedule_require_manager_api();
    $id = (int)($_POST['role_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $sort = (int)($_POST['sort_order'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
    if ($id <= 0 || $name === '') {
        schedule_json_error('Invalid role payload.', 422);
    }
    if (!schedule_role_exists($resId, $id)) {
        schedule_json_error('Role not found.', 422);
    }
    try {
        $ok = schedule_execute('UPDATE roles SET name=:name,color=:color,sort_order=:sort_order,is_active=:is_active WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':name' => $name,
            ':color' => $color !== '' ? $color : null,
            ':sort_order' => $sort,
            ':is_active' => $isActive,
            ':restaurant_id' => $resId,
            ':id' => $id,
        ]);
        if (!$ok) {
            schedule_json_error('Could not update role.', 500);
        }
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'uniq_roles_restaurant_name') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
            schedule_json_error('Role name already exists for this restaurant.', 422);
        }
        schedule_json_error('Could not update role.', 500);
    }
    schedule_json_success(['message' => 'Role updated.']);
}

if ($action === 'toggle_role_active') {
    schedule_require_manager_api();
    $id = (int)($_POST['role_id'] ?? 0);
    if ($id <= 0) {
        schedule_json_error('Invalid role.', 422);
    }
    $role = schedule_fetch_one('SELECT id,is_active FROM roles WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$id]);
    if (!$role) {
        schedule_json_error('Role not found.', 422);
    }
    $next = ((int)$role['is_active'] === 1) ? 0 : 1;
    schedule_execute('UPDATE roles SET is_active=:is_active WHERE restaurant_id=:restaurant_id AND id=:id', [':is_active'=>$next, ':restaurant_id'=>$resId, ':id'=>$id]);
    schedule_json_success(['message' => $next === 1 ? 'Role reactivated.' : 'Role deactivated.']);
}

if ($action === 'delete_role') {
    $_POST['action'] = 'toggle_role_active';
    schedule_require_manager_api();
    $id = (int)($_POST['role_id'] ?? 0);
    if ($id <= 0) {
        schedule_json_error('Invalid role.', 422);
    }
    schedule_execute('UPDATE roles SET is_active=0 WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$id]);
    schedule_json_success(['message' => 'Role deactivated.']);
}

if ($action === 'save_availability') {
    $staffId = (int)($_POST['staff_id'] ?? $myStaffId);
    if (!schedule_is_manager() && $staffId !== $myStaffId) {
        schedule_json_error('Forbidden', 403);
    }
    $day = (int)($_POST['day_of_week'] ?? -1);
    $status = trim((string)($_POST['status'] ?? 'available'));
    $start = schedule_time_or_null((string)($_POST['start_time'] ?? ''));
    $end = schedule_time_or_null((string)($_POST['end_time'] ?? ''));
    if ($day < 0 || $day > 6) {
        schedule_json_error('Day must be between 0 and 6.', 422);
    }
    if (!in_array($status, ['available', 'preferred', 'unavailable'], true)) {
        $status = 'available';
    }
    if ($status !== 'unavailable') {
        if ($start === null || $end === null) {
            schedule_json_error('Start and end times are required unless unavailable.', 422);
        }
        if ($end <= $start) {
            schedule_json_error('End time must be after start time.', 422);
        }
    } else {
        $start = '00:00:00';
        $end = '00:00:00';
    }

    $existing = schedule_fetch_one('SELECT id FROM staff_availability WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND day_of_week=:day', [
        ':restaurant_id' => $resId, ':staff_id' => $staffId, ':day' => $day,
    ]);

    if ($existing) {
        schedule_execute('UPDATE staff_availability SET start_time=:start_time,end_time=:end_time,status=:status,notes=:notes WHERE restaurant_id=:restaurant_id AND id=:id', [
            ':start_time'=>$start, ':end_time'=>$end, ':status'=>$status, ':notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,
            ':restaurant_id'=>$resId, ':id'=>(int)$existing['id'],
        ]);
    } else {
        schedule_execute('INSERT INTO staff_availability (restaurant_id,staff_id,day_of_week,start_time,end_time,status,notes) VALUES (:restaurant_id,:staff_id,:day,:start_time,:end_time,:status,:notes)', [
            ':restaurant_id'=>$resId, ':staff_id'=>$staffId, ':day'=>$day, ':start_time'=>$start, ':end_time'=>$end,
            ':status'=>$status, ':notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,
        ]);
    }
    schedule_json_success(['message' => 'Availability saved.']);
}

if ($action === 'list_availability') {
    $staffId = (int)($_POST['staff_id'] ?? $myStaffId);
    if (!schedule_is_manager() && $staffId !== $myStaffId) {
        schedule_json_error('Forbidden', 403);
    }
    $rows = schedule_fetch_all('SELECT id,staff_id,day_of_week,start_time,end_time,status,notes FROM staff_availability WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id ORDER BY day_of_week ASC', [
        ':restaurant_id'=>$resId, ':staff_id'=>$staffId,
    ]);
    schedule_json_success(['availability' => $rows]);
}

if ($action === 'list_shifts') {
    $week = schedule_week_window(isset($_POST['week_start']) && is_string($_POST['week_start']) ? $_POST['week_start'] : null);
    $start = $week['start'] . ' 00:00:00';
    $end = (new DateTimeImmutable($week['start']))->modify('+7 days')->format('Y-m-d') . ' 00:00:00';
    $filter = 'restaurant_id = :restaurant_id AND status != "deleted" AND start_dt >= :start_dt AND start_dt < :end_dt';
    $params = [':restaurant_id'=>$resId, ':start_dt'=>$start, ':end_dt'=>$end];
    if (!schedule_is_manager()) {
        $filter .= ' AND status = "published"';
    }
    $rows = schedule_fetch_all(
        'SELECT s.id, s.restaurant_id, s.staff_id, s.role_id, s.start_dt, s.end_dt, s.break_minutes, s.notes, s.status,
                r.name AS role_name, r.color AS role_color
         FROM shifts s
         LEFT JOIN roles r ON r.restaurant_id = s.restaurant_id AND r.id = s.role_id
         WHERE ' . $filter . '
         ORDER BY s.start_dt ASC',
        $params
    );
    schedule_json_success(['week_start' => $week['start'], 'week_end' => $week['end'], 'shifts' => $rows]);
}

if ($action === 'create_shift') {
    schedule_require_manager_api();
    $date = (string)($_POST['date'] ?? '');
    $startTime = (string)($_POST['start_time'] ?? '');
    $endTime = (string)($_POST['end_time'] ?? '');
    $startDt = schedule_datetime_from_inputs($date, $startTime);
    $endDt = schedule_datetime_from_inputs($date, $endTime);
    if ($startDt === null || $endDt === null) {
        schedule_json_error('Date, start time, and end time are required.', 422);
    }
    if ($endDt <= $startDt) {
        schedule_json_error('Shift end must be after start.', 422);
    }
    $roleId = (int)($_POST['role_id'] ?? 0);
    $staffInput = trim((string)($_POST['staff_id'] ?? ''));
    $staffId = ($staffInput === '' || $staffInput === '0') ? null : (int)$staffInput;
    if ($roleId > 0 && !schedule_role_exists($resId, $roleId)) {
        schedule_json_error('Selected role is invalid.', 422);
    }
    if ($staffId !== null) {
        if (schedule_has_overlap($resId, $staffId, $startDt, $endDt, null)) {
            schedule_json_error('Assigned staff has an overlapping shift.', 422);
        }
        if (schedule_has_time_off_conflict($resId, $staffId, $startDt, $endDt)) {
            schedule_json_error('Assigned staff has approved time off during this shift.', 422);
        }
    }
    schedule_execute('INSERT INTO shifts (restaurant_id,staff_id,role_id,start_dt,end_dt,break_minutes,notes,status,created_by) VALUES (:restaurant_id,:staff_id,:role_id,:start_dt,:end_dt,:break_minutes,:notes,"draft",:created_by)', [
        ':restaurant_id'=>$resId,
        ':staff_id'=>$staffId,
        ':role_id'=>$roleId > 0 ? $roleId : null,
        ':start_dt'=>$startDt,
        ':end_dt'=>$endDt,
        ':break_minutes'=>max(0, (int)($_POST['break_minutes'] ?? 0)),
        ':notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,
        ':created_by'=>$userId,
    ]);
    schedule_json_success(['message' => 'Shift created.']);
}

if ($action === 'update_shift') {
    schedule_require_manager_api();
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    if ($shiftId <= 0) {
        schedule_json_error('Invalid shift.', 422);
    }
    $shift = schedule_shift_by_id($resId, $shiftId);
    if (!$shift) {
        schedule_json_error('Shift not found.', 422);
    }
    $date = (string)($_POST['date'] ?? substr((string)$shift['start_dt'], 0, 10));
    $startTime = (string)($_POST['start_time'] ?? substr((string)$shift['start_dt'], 11, 5));
    $endTime = (string)($_POST['end_time'] ?? substr((string)$shift['end_dt'], 11, 5));
    $startDt = schedule_datetime_from_inputs($date, $startTime);
    $endDt = schedule_datetime_from_inputs($date, $endTime);
    if ($startDt === null || $endDt === null || $endDt <= $startDt) {
        schedule_json_error('Shift start/end are invalid.', 422);
    }
    $roleId = (int)($_POST['role_id'] ?? ($shift['role_id'] ?? 0));
    $staffInput = trim((string)($_POST['staff_id'] ?? (string)($shift['staff_id'] ?? '')));
    $staffId = ($staffInput === '' || $staffInput === '0') ? null : (int)$staffInput;
    if ($roleId > 0 && !schedule_role_exists($resId, $roleId)) {
        schedule_json_error('Selected role is invalid.', 422);
    }
    if ($staffId !== null) {
        if (schedule_has_overlap($resId, $staffId, $startDt, $endDt, $shiftId)) {
            schedule_json_error('Assigned staff has an overlapping shift.', 422);
        }
        if (schedule_has_time_off_conflict($resId, $staffId, $startDt, $endDt, $shiftId)) {
            schedule_json_error('Assigned staff has approved time off during this shift.', 422);
        }
    }
    schedule_execute('UPDATE shifts SET staff_id=:staff_id,role_id=:role_id,start_dt=:start_dt,end_dt=:end_dt,break_minutes=:break_minutes,notes=:notes WHERE restaurant_id=:restaurant_id AND id=:id', [
        ':staff_id'=>$staffId,
        ':role_id'=>$roleId > 0 ? $roleId : null,
        ':start_dt'=>$startDt,
        ':end_dt'=>$endDt,
        ':break_minutes'=>max(0, (int)($_POST['break_minutes'] ?? ($shift['break_minutes'] ?? 0))),
        ':notes'=>trim((string)($_POST['notes'] ?? (string)($shift['notes'] ?? ''))) ?: null,
        ':restaurant_id'=>$resId,
        ':id'=>$shiftId,
    ]);
    schedule_json_success(['message' => 'Shift updated.']);
}

if ($action === 'delete_shift') {
    schedule_require_manager_api();
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    if ($shiftId <= 0 || !schedule_shift_by_id($resId, $shiftId)) {
        schedule_json_error('Shift not found.', 422);
    }
    schedule_execute('UPDATE shifts SET status="deleted" WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id'=>$resId, ':id'=>$shiftId]);
    schedule_json_success(['message' => 'Shift deleted.']);
}

if ($action === 'publish_week') {
    schedule_require_manager_api();
    $weekStart = schedule_date((string)($_POST['week_start'] ?? ''), '');
    if ($weekStart === '') {
        schedule_json_error('week_start is required.', 422);
    }
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
    schedule_execute('UPDATE shifts SET status="published" WHERE restaurant_id=:restaurant_id AND start_dt >= :week_start AND start_dt < :week_end AND status != "deleted"', [
        ':restaurant_id'=>$resId,
        ':week_start'=>$weekStart . ' 00:00:00',
        ':week_end'=>$weekEnd . ' 00:00:00',
    ]);
    schedule_json_success(['message' => 'Week published.', 'week_start' => $weekStart, 'week_end' => $weekEnd]);
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
    if ($start === '' || $end === '' || $reason === '') {
        schedule_json_error('Start, end, and reason are required.', 422);
    }
    if ($end <= $start) {
        schedule_json_error('End must be after start.', 422);
    }
    schedule_execute('INSERT INTO time_off_requests (restaurant_id,staff_id,start_dt,end_dt,reason,status) VALUES (:restaurant_id,:staff_id,:start_dt,:end_dt,:reason,"pending")', [
        ':restaurant_id'=>$resId, ':staff_id'=>$staffId, ':start_dt'=>$start, ':end_dt'=>$end, ':reason'=>$reason,
    ]);
    schedule_json_success(['message' => 'Time-off request submitted.']);
}

if ($action === 'review_time_off') {
    schedule_require_manager_api();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = trim((string)($_POST['decision'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approved', 'denied'], true)) {
        schedule_json_error('Invalid review payload.', 422);
    }
    if (!schedule_time_off_by_id($resId, $requestId)) {
        schedule_json_error('Time-off request not found.', 422);
    }
    $note = trim((string)($_POST['review_note'] ?? ''));
    $reason = $note !== '' ? $note : null;
    schedule_execute('UPDATE time_off_requests SET status=:status, reviewed_by=:reviewed_by, reviewed_at=NOW(), reason=COALESCE(:reason, reason) WHERE restaurant_id=:restaurant_id AND id=:id', [
        ':status'=>$decision,
        ':reviewed_by'=>$userId,
        ':reason'=>$reason,
        ':restaurant_id'=>$resId,
        ':id'=>$requestId,
    ]);
    schedule_json_success(['message' => 'Time-off request reviewed.']);
}

if ($action === 'list_time_off') {
    $status = trim((string)($_POST['status'] ?? ''));
    $params = [':restaurant_id' => $resId];
    $sql = 'SELECT id, staff_id, start_dt, end_dt, reason, status, reviewed_by, reviewed_at
            FROM time_off_requests WHERE restaurant_id = :restaurant_id';

    if (!schedule_is_manager()) {
        $sql .= ' AND staff_id = :staff_id';
        $params[':staff_id'] = $myStaffId;
    } elseif ($status !== '' && in_array($status, ['pending', 'approved', 'denied', 'cancelled'], true)) {
        $sql .= ' AND status = :status';
        $params[':status'] = $status;
    }

    $sql .= ' ORDER BY start_dt DESC';
    $rows = schedule_fetch_all($sql, $params);
    schedule_json_success(['time_off_requests' => $rows]);
}

schedule_json_error('Unknown action', 422);
