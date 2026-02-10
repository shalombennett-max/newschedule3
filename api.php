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

function schedule_has_time_off_conflict(int $resId, int $staffId, string $startDt, string $endDt, ?int $excludeShiftId = null): bool {␊
    $row = schedule_fetch_one(
        'SELECT id FROM time_off_requests WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id
         AND status="approved" AND start_dt < :end_dt AND end_dt > :start_dt',
        [':restaurant_id'=>$resId, ':staff_id'=>$staffId, ':start_dt'=>$startDt, ':end_dt'=>$endDt]
    );
    return $row !== null;
}

function schedule_hours_between(string $startDt, string $endDt, int $breakMinutes = 0): float {
    $startTs = strtotime($startDt);
    $endTs = strtotime($endDt);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        return 0.0;
    }
    $seconds = $endTs - $startTs;
    $hours = ($seconds / 3600) - (max(0, $breakMinutes) / 60);
    return max(0.0, $hours);
}

function schedule_quality_reason(string $key, int $count, int $impact, array $examples, array $suggestions): array {
    return [
        'key' => $key,
        'count' => $count,
        'impact' => $impact,
        'examples' => array_values(array_slice($examples, 0, 5)),
        'suggestions' => array_values(array_slice($suggestions, 0, 3)),
    ];
}

function schedule_generate_quality_payload(int $resId, int $userId, string $weekStart): array {
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
    $shifts = schedule_fetch_all(
        'SELECT id, staff_id, role_id, start_dt, end_dt, break_minutes, status
         FROM shifts
         WHERE restaurant_id=:restaurant_id AND status != "deleted"
           AND start_dt >= :week_start AND start_dt < :week_end
         ORDER BY staff_id ASC, start_dt ASC',
        [':restaurant_id' => $resId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00']
    );

    $score = 100;
    $reasons = [];

    $daysWithShifts = [];
    foreach ($shifts as $shift) {
        $daysWithShifts[substr((string)$shift['start_dt'], 0, 10)] = true;
    }
    $hasUncovered = false;
    for ($i = 0; $i < 7; $i++) {
        $day = (new DateTimeImmutable($weekStart))->modify('+' . $i . ' days')->format('Y-m-d');
        if (!isset($daysWithShifts[$day])) {
            $hasUncovered = true;
            break;
        }
    }
    if ($hasUncovered) {
        $score -= 15;
        $reasons[] = schedule_quality_reason('uncovered_day', 1, -15, ['At least one day in the week has zero shifts.'], ['Add minimum coverage shifts for each day to reduce service risk.']);
    }

    $assignedShifts = array_values(array_filter($shifts, static fn(array $s): bool => !empty($s['staff_id'])));
    $byStaff = [];
    foreach ($assignedShifts as $shift) {
        $byStaff[(int)$shift['staff_id']][] = $shift;
    }

    $clopenCount = 0;
    $clopenExamples = [];
    foreach ($byStaff as $staffId => $staffShifts) {
        usort($staffShifts, static fn(array $a, array $b): int => strcmp((string)$a['start_dt'], (string)$b['start_dt']));
        for ($i = 0; $i < count($staffShifts) - 1; $i++) {
            $current = $staffShifts[$i];
            $next = $staffShifts[$i + 1];
            $currentEnd = new DateTimeImmutable((string)$current['end_dt']);
            $nextStart = new DateTimeImmutable((string)$next['start_dt']);
            if ($currentEnd->format('Y-m-d') !== $nextStart->modify('-1 day')->format('Y-m-d')) {
                continue;
            }
            if ((int)$currentEnd->format('G') >= 22 && (int)$nextStart->format('G') < 10) {
                $clopenCount++;
                if (count($clopenExamples) < 3) {
                    $clopenExamples[] = 'Staff #' . $staffId . ' closes at ' . $currentEnd->format('g:ia') . ' then opens at ' . $nextStart->format('g:ia') . '.';
                }
            }
        }
    }
    if ($clopenCount > 0) {
        $impact = -min(25, $clopenCount * 10);
        $score += $impact;
        $reasons[] = schedule_quality_reason('clopen_risk', $clopenCount, $impact, $clopenExamples, ['Consider moving opening shifts later or reassigning late closers.']);
    }

    $overtimeCount = 0;
    $overtimeExamples = [];
    foreach ($byStaff as $staffId => $staffShifts) {
        $hours = 0.0;
        foreach ($staffShifts as $shift) {
            $hours += schedule_hours_between((string)$shift['start_dt'], (string)$shift['end_dt'], (int)($shift['break_minutes'] ?? 0));
        }
        if ($hours > 40) {
            $overtimeCount++;
            if (count($overtimeExamples) < 3) {
                $overtimeExamples[] = 'Staff #' . $staffId . ' scheduled for ' . round($hours, 1) . ' hours.';
            }
        }
    }
    if ($overtimeCount > 0) {
        $impact = -min(30, $overtimeCount * 10);
        $score += $impact;
        $reasons[] = schedule_quality_reason('overtime_risk', $overtimeCount, $impact, $overtimeExamples, ['Rebalance long weekly assignments to keep staff closer to 40 hours.']);
    }

    $availabilityRows = schedule_fetch_all(
        'SELECT staff_id, day_of_week, start_time, end_time
         FROM staff_availability
         WHERE restaurant_id=:restaurant_id AND status="unavailable"',
        [':restaurant_id' => $resId]
    );
    $availabilityMap = [];
    foreach ($availabilityRows as $row) {
        $availabilityMap[(int)$row['staff_id']][] = $row;
    }
    $availabilityCount = 0;
    $availabilityExamples = [];
    foreach ($assignedShifts as $shift) {
        $staffId = (int)$shift['staff_id'];
        if (!isset($availabilityMap[$staffId])) {
            continue;
        }
        $start = new DateTimeImmutable((string)$shift['start_dt']);
        $end = new DateTimeImmutable((string)$shift['end_dt']);
        $dayOfWeek = (int)$start->format('w');
        $shiftStart = $start->format('H:i:s');
        $shiftEnd = $end->format('H:i:s');
        foreach ($availabilityMap[$staffId] as $slot) {
            if ((int)$slot['day_of_week'] !== $dayOfWeek) {
                continue;
            }
            if ($shiftStart < (string)$slot['end_time'] && $shiftEnd > (string)$slot['start_time']) {
                $availabilityCount++;
                if (count($availabilityExamples) < 3) {
                    $availabilityExamples[] = 'Staff #' . $staffId . ' scheduled during unavailable window on ' . $start->format('D') . '.';
                }
                break;
            }
        }
    }
    if ($availabilityCount > 0) {
        $impact = -min(30, $availabilityCount * 10);
        $score += $impact;
        $reasons[] = schedule_quality_reason('availability_conflict', $availabilityCount, $impact, $availabilityExamples, ['Move or swap shifts that overlap unavailable windows.']);
    }

    $timeOffCount = 0;
    $timeOffExamples = [];
    foreach ($assignedShifts as $shift) {
        $staffId = (int)$shift['staff_id'];
        if (schedule_has_time_off_conflict($resId, $staffId, (string)$shift['start_dt'], (string)$shift['end_dt'])) {
            $timeOffCount++;
            if (count($timeOffExamples) < 3) {
                $timeOffExamples[] = 'Staff #' . $staffId . ' assigned during approved time off on ' . substr((string)$shift['start_dt'], 0, 10) . '.';
            }
        }
    }
    if ($timeOffCount > 0) {
        $impact = -min(50, $timeOffCount * 25);
        $score += $impact;
        $reasons[] = schedule_quality_reason('time_off_conflict', $timeOffCount, $impact, $timeOffExamples, ['Remove assignments overlapping approved time off and repost open shifts.']);
    }

    $hasRoles = schedule_fetch_one('SELECT id FROM roles WHERE restaurant_id=:restaurant_id LIMIT 1', [':restaurant_id' => $resId]) !== null;
    if ($hasRoles) {
        $hasRoleAssignments = false;
        foreach ($shifts as $shift) {
            if (!empty($shift['role_id'])) {
                $hasRoleAssignments = true;
                break;
            }
        }
        if (!$hasRoleAssignments) {
            $score -= 10;
            $reasons[] = schedule_quality_reason('role_coverage_missing', 1, -10, ['Roles exist, but no shifts in this week have role assignments.'], ['Assign roles to shifts so staffing coverage is easier to audit.']);
        }
    }

    $score = max(0, min(100, $score));
    return ['score' => $score, 'reasons' => $reasons, 'week_start' => $weekStart, 'generated_by' => $userId];
}

function schedule_table_has_columns(string $tableName, array $requiredColumns): bool {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
        if (!$stmt || !$stmt->execute([':table_name' => $tableName])) {
            return false;
        }
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($columns)) {
            return false;
        }
        $normalized = array_map('strtolower', $columns);
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

function schedule_try_insert_trigger_task(int $resId, string $title): void {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO || trim($title) === '') {
        return;
    }

    try {
        if (schedule_table_has_columns('planner_tasks', ['restaurant_id', 'title'])) {
            $stmt = $pdo->prepare('INSERT INTO planner_tasks (restaurant_id, title, created_at) VALUES (:restaurant_id, :title, NOW())');
            if ($stmt) {
                $stmt->execute([':restaurant_id' => $resId, ':title' => $title]);
            }
            return;
        }

        if (schedule_table_has_columns('tasks', ['restaurant_id', 'name'])) {
            $stmt = $pdo->prepare('INSERT INTO tasks (restaurant_id, name, created_at) VALUES (:restaurant_id, :name, NOW())');
            if ($stmt) {
                $stmt->execute([':restaurant_id' => $resId, ':name' => $title]);
            }
        }
        // Expected planner integration tables: planner_tasks(title) or tasks(name); skip silently if absent.
    } catch (Throwable $e) {
        // Trigger writes are best-effort and must never block publish.
    }
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
@@ -311,64 +545,315 @@ if ($action === 'update_shift') {
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

if ($action === 'publish_week') {␊
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

    $qualityPayload = schedule_generate_quality_payload($resId, $userId, $weekStart);
    $reasonsJson = json_encode($qualityPayload['reasons'], JSON_UNESCAPED_UNICODE);
    if (!is_string($reasonsJson)) {
        $reasonsJson = '[]';
    }
    schedule_execute(
        'INSERT INTO schedule_quality (restaurant_id, week_start_date, score, reasons_json, generated_at, generated_by)
         VALUES (:restaurant_id, :week_start_date, :score, :reasons_json, NOW(), :generated_by)
         ON DUPLICATE KEY UPDATE score=VALUES(score), reasons_json=VALUES(reasons_json), generated_at=NOW(), generated_by=VALUES(generated_by)',
        [
            ':restaurant_id' => $resId,
            ':week_start_date' => $weekStart,
            ':score' => (int)$qualityPayload['score'],
            ':reasons_json' => $reasonsJson,
            ':generated_by' => $userId,
        ]
    );

    if ((int)$qualityPayload['score'] < 85) {
        schedule_try_insert_trigger_task($resId, 'Review Schedule Quality Issues');
    }
    foreach ($qualityPayload['reasons'] as $reason) {
        if (($reason['key'] ?? '') === 'time_off_conflict' && (int)($reason['count'] ?? 0) > 0) {
            schedule_try_insert_trigger_task($resId, 'Resolve Time-Off Conflicts');
            break;
        }
    }

    schedule_json_success(['message' => 'Week published.', 'week_start' => $weekStart, 'week_end' => $weekEnd]);
}

if ($action === 'get_quality_score') {
    schedule_require_manager_api();
    $weekStart = schedule_date((string)($_POST['week_start'] ?? ''), '');
    if ($weekStart === '') {
        schedule_json_error('week_start is required.', 422);
    }
    $row = schedule_fetch_one(
        'SELECT week_start_date, score, reasons_json, generated_at, generated_by
         FROM schedule_quality
         WHERE restaurant_id=:restaurant_id AND week_start_date=:week_start_date',
        [':restaurant_id' => $resId, ':week_start_date' => $weekStart]
    );
    if ($row === null) {
        schedule_json_success(['quality' => null]);
    }
    $reasons = json_decode((string)($row['reasons_json'] ?? '[]'), true);
    if (!is_array($reasons)) {
        $reasons = [];
    }
    schedule_json_success(['quality' => [
        'week_start_date' => $row['week_start_date'],
        'score' => (int)$row['score'],
        'reasons' => $reasons,
        'generated_at' => $row['generated_at'],
        'generated_by' => $row['generated_by'],
    ]]);
}

if ($action === 'generate_quality_score') {
    schedule_require_manager_api();
    $weekStart = schedule_date((string)($_POST['week_start'] ?? ''), '');
    if ($weekStart === '') {
        schedule_json_error('week_start is required.', 422);
    }
    $payload = schedule_generate_quality_payload($resId, $userId, $weekStart);
    $reasonsJson = json_encode($payload['reasons'], JSON_UNESCAPED_UNICODE);
    if (!is_string($reasonsJson)) {
        $reasonsJson = '[]';
    }
    schedule_execute(
        'INSERT INTO schedule_quality (restaurant_id, week_start_date, score, reasons_json, generated_at, generated_by)
         VALUES (:restaurant_id, :week_start_date, :score, :reasons_json, NOW(), :generated_by)
         ON DUPLICATE KEY UPDATE score=VALUES(score), reasons_json=VALUES(reasons_json), generated_at=NOW(), generated_by=VALUES(generated_by)',
        [
            ':restaurant_id' => $resId,
            ':week_start_date' => $weekStart,
            ':score' => (int)$payload['score'],
            ':reasons_json' => $reasonsJson,
            ':generated_by' => $userId,
        ]
    );
    schedule_json_success(['quality' => [
        'week_start_date' => $weekStart,
        'score' => (int)$payload['score'],
        'reasons' => $payload['reasons'],
    ]]);
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
    $staffId = (int)($_POST['staff_id'] ?? $myStaffId);
    if ($staffId !== $myStaffId) {
        schedule_json_error('Forbidden', 403);
    }
    $shift = $shiftId > 0 ? schedule_shift_by_id($resId, $shiftId) : null;
    if ($shift === null || (string)$shift['status'] === 'deleted') {
        schedule_json_error('Shift not found.', 422);
    }
    if (!in_array((string)$shift['status'], ['draft', 'published'], true) || !empty($shift['staff_id'])) {
        schedule_json_error('Shift is not open for pickup.', 422);
    }
    $existing = schedule_fetch_one(
        'SELECT id FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND staff_id=:staff_id AND status="pending"',
        [':restaurant_id' => $resId, ':shift_id' => $shiftId, ':staff_id' => $myStaffId]
    );
    if ($existing !== null) {
        schedule_json_error('Pickup request already pending for this shift.', 422);
    }
    schedule_execute(
        'INSERT INTO shift_pickup_requests (restaurant_id, shift_id, staff_id, status, created_at)
         VALUES (:restaurant_id, :shift_id, :staff_id, "pending", NOW())',
        [':restaurant_id' => $resId, ':shift_id' => $shiftId, ':staff_id' => $myStaffId]
    );
    schedule_json_success(['message' => 'Pickup request submitted.']);
}

if ($action === 'list_open_shifts') {
    if (schedule_is_manager()) {
        schedule_json_error('Forbidden', 403);
    }
    $startDate = schedule_date((string)($_POST['start_date'] ?? date('Y-m-d')), date('Y-m-d'));
    $days = (int)($_POST['days'] ?? 14);
    $days = max(1, min(30, $days));
    $endDate = (new DateTimeImmutable($startDate))->modify('+' . $days . ' days')->format('Y-m-d');

    $rows = schedule_fetch_all(
        'SELECT s.id, s.role_id, s.start_dt, s.end_dt, s.break_minutes, s.notes, s.status,
                r.name AS role_name,
                pr.status AS my_request_status
         FROM shifts s
         LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
         LEFT JOIN shift_pickup_requests pr ON pr.restaurant_id=s.restaurant_id AND pr.shift_id=s.id AND pr.staff_id=:staff_id
         WHERE s.restaurant_id=:restaurant_id AND s.status IN ("draft","published")
           AND s.staff_id IS NULL AND s.start_dt >= :start_dt AND s.start_dt < :end_dt
         ORDER BY s.start_dt ASC',
        [
            ':restaurant_id' => $resId,
            ':staff_id' => $myStaffId,
            ':start_dt' => $startDate . ' 00:00:00',
            ':end_dt' => $endDate . ' 00:00:00',
        ]
    );
    schedule_json_success(['open_shifts' => $rows, 'start_date' => $startDate, 'end_date' => $endDate]);
}

if ($action === 'list_pickup_requests') {
    schedule_require_manager_api();
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $params = [':restaurant_id' => $resId];
    $sql = 'SELECT pr.id, pr.shift_id, pr.staff_id, pr.status, pr.created_at,
                   s.start_dt, s.end_dt, s.role_id, s.notes,
                   r.name AS role_name
            FROM shift_pickup_requests pr
            INNER JOIN shifts s ON s.restaurant_id=pr.restaurant_id AND s.id=pr.shift_id
            LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
            WHERE pr.restaurant_id=:restaurant_id';
    if ($shiftId > 0) {
        $sql .= ' AND pr.shift_id=:shift_id';
        $params[':shift_id'] = $shiftId;
    }
    $sql .= ' ORDER BY pr.created_at DESC';
    $rows = schedule_fetch_all($sql, $params);
    schedule_json_success(['pickup_requests' => $rows]);
}

if ($action === 'approve_pickup') {
    schedule_require_manager_api();
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($requestId <= 0) {
        schedule_json_error('Invalid request.', 422);
    }
    $request = schedule_fetch_one('SELECT * FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    if ($request === null) {
        schedule_json_error('Request not found.', 422);
    }
    if ((string)$request['status'] !== 'pending') {
        schedule_json_error('Request is no longer pending.', 422);
    }
    $shift = schedule_shift_by_id($resId, (int)$request['shift_id']);
    if ($shift === null || (string)$shift['status'] === 'deleted') {
        schedule_json_error('Shift not found.', 422);
    }
    if (!empty($shift['staff_id'])) {
        schedule_json_error('Shift is no longer open.', 422);
    }

    $staffId = (int)$request['staff_id'];
    if (schedule_has_overlap($resId, $staffId, (string)$shift['start_dt'], (string)$shift['end_dt'])) {
        schedule_json_error('Staff has an overlapping shift.', 422);
    }
    if (schedule_has_time_off_conflict($resId, $staffId, (string)$shift['start_dt'], (string)$shift['end_dt'])) {
        schedule_json_error('Staff has approved time off during this shift.', 422);
    }

    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        schedule_json_error('Database unavailable.', 500);
    }
    try {
        $pdo->beginTransaction();
        schedule_execute('UPDATE shifts SET staff_id=:staff_id WHERE restaurant_id=:restaurant_id AND id=:id AND staff_id IS NULL', [
            ':staff_id' => $staffId,
            ':restaurant_id' => $resId,
            ':id' => (int)$request['shift_id'],
        ]);
        schedule_execute('UPDATE shift_pickup_requests SET status="approved", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
        schedule_execute('UPDATE shift_pickup_requests SET status="denied", updated_at=NOW() WHERE restaurant_id=:restaurant_id AND shift_id=:shift_id AND status="pending" AND id != :id', [
            ':restaurant_id' => $resId,
            ':shift_id' => (int)$request['shift_id'],
            ':id' => $requestId,
        ]);
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
    if ($requestId <= 0) {
        schedule_json_error('Invalid request.', 422);
    }
    $request = schedule_fetch_one('SELECT id, status FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND id=:id', [':restaurant_id' => $resId, ':id' => $requestId]);
    if ($request === null) {
        schedule_json_error('Request not found.', 422);
    }
    if ((string)$request['status'] !== 'pending') {
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