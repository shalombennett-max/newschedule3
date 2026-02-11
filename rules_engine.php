<?php
declare(strict_types=1);

function se_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    if (!$stmt || !$stmt->execute([':table_name' => $table])) {
        $cache[$key] = false;
        return false;
    }
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function se_default_policy_config(): array
{
    return [
        'max_weekly_hours' => ['enabled' => 1, 'mode' => 'warn', 'params' => ['hours' => 40]],
        'max_daily_hours' => ['enabled' => 1, 'mode' => 'warn', 'params' => ['hours' => 12]],
        'min_rest_between_shifts' => ['enabled' => 1, 'mode' => 'warn', 'params' => ['hours' => 10]],
        'break_required_after_hours' => ['enabled' => 1, 'mode' => 'warn', 'params' => ['hours_worked' => 6, 'break_minutes' => 30]],
        'minor_rules' => ['enabled' => 0, 'mode' => 'warn', 'params' => ['enabled' => false, 'max_daily_hours' => 8, 'max_weekly_hours' => 20, 'latest_end_hour' => 22]],
        'availability_conflict' => ['enabled' => 1, 'mode' => 'warn', 'params' => []],
        'timeoff_conflict' => ['enabled' => 1, 'mode' => 'block', 'params' => []],
    ];
}

function se_get_active_policy_set_id(PDO $pdo, int $restaurantId): int
{
    $stmt = $pdo->prepare('SELECT id FROM schedule_policy_sets WHERE restaurant_id=:restaurant_id AND is_active=1 ORDER BY is_default DESC, id ASC LIMIT 1');
    $stmt->execute([':restaurant_id' => $restaurantId]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $pdo->prepare('INSERT INTO schedule_policy_sets (restaurant_id, name, is_active, is_default, created_at) VALUES (:restaurant_id, :name, 1, 1, NOW())')
        ->execute([':restaurant_id' => $restaurantId, ':name' => 'Company Standard']);
    $policySetId = (int)$pdo->lastInsertId();
    se_reset_policy_set_defaults($pdo, $restaurantId, $policySetId);
    return $policySetId;
}

function se_reset_policy_set_defaults(PDO $pdo, int $restaurantId, int $policySetId): void
{
    $pdo->prepare('DELETE FROM schedule_policies WHERE restaurant_id=:restaurant_id AND policy_set_id=:policy_set_id')
        ->execute([':restaurant_id' => $restaurantId, ':policy_set_id' => $policySetId]);
    $insert = $pdo->prepare('INSERT INTO schedule_policies (restaurant_id, policy_set_id, policy_key, enabled, mode, params_json, created_at)
                             VALUES (:restaurant_id, :policy_set_id, :policy_key, :enabled, :mode, :params_json, NOW())');
    foreach (se_default_policy_config() as $key => $cfg) {
        $insert->execute([
            ':restaurant_id' => $restaurantId,
            ':policy_set_id' => $policySetId,
            ':policy_key' => $key,
            ':enabled' => (int)$cfg['enabled'],
            ':mode' => $cfg['mode'] === 'block' ? 'block' : 'warn',
            ':params_json' => json_encode($cfg['params'], JSON_UNESCAPED_SLASHES),
        ]);
    }
}

function se_load_policies(PDO $pdo, int $restaurantId, int $policySetId): array
{
    $rows = [];
    if (se_table_exists($pdo, 'schedule_policies')) {
        $stmt = $pdo->prepare('SELECT policy_key, enabled, mode, params_json FROM schedule_policies WHERE restaurant_id=:restaurant_id AND policy_set_id=:policy_set_id');
        $stmt->execute([':restaurant_id' => $restaurantId, ':policy_set_id' => $policySetId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($rows === []) {
        se_reset_policy_set_defaults($pdo, $restaurantId, $policySetId);
        $stmt = $pdo->prepare('SELECT policy_key, enabled, mode, params_json FROM schedule_policies WHERE restaurant_id=:restaurant_id AND policy_set_id=:policy_set_id');
        $stmt->execute([':restaurant_id' => $restaurantId, ':policy_set_id' => $policySetId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $policies = [];
    foreach ($rows as $row) {
        $params = json_decode((string)($row['params_json'] ?? '{}'), true);
        if (!is_array($params)) {
            $params = [];
        }
        $mode = ((string)$row['mode'] === 'block') ? 'block' : 'warn';
        $policies[(string)$row['policy_key']] = [
            'enabled' => (int)($row['enabled'] ?? 1) === 1,
            'mode' => $mode,
            'params' => $params,
        ];
    }
    foreach (se_default_policy_config() as $key => $cfg) {
        if (!isset($policies[$key])) {
            $policies[$key] = ['enabled' => (bool)$cfg['enabled'], 'mode' => $cfg['mode'], 'params' => $cfg['params']];
        }
    }
    return $policies;
}

function se_violation(string $policyKey, array $policy, string $message, array $details = []): array
{
    return [
        'policy_key' => $policyKey,
        'severity' => (($policy['mode'] ?? 'warn') === 'block') ? 'block' : 'warn',
        'message' => $message,
        'details' => $details,
    ];
}

function se_hours_between(string $start, string $end, int $breakMinutes = 0): float
{
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        return 0.0;
    }
    $minutes = max(0, (int)round(($endTs - $startTs) / 60) - max(0, $breakMinutes));
    return $minutes / 60;
}

function se_check_shift(PDO $pdo, int $restaurantId, array $shift, array $policies): array
{
    $staffId = isset($shift['staff_id']) ? (int)$shift['staff_id'] : 0;
    if ($staffId <= 0) {
        return [];
    }
    $shiftId = isset($shift['id']) ? (int)$shift['id'] : 0;
    $startDt = (string)($shift['start_dt'] ?? '');
    $endDt = (string)($shift['end_dt'] ?? '');
    $breakMinutes = (int)($shift['break_minutes'] ?? 0);
    if ($startDt === '' || $endDt === '') {
        return [];
    }

    $violations = [];

    if (($policies['timeoff_conflict']['enabled'] ?? false) === true) {
        $stmt = $pdo->prepare('SELECT id FROM time_off_requests WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status="approved" AND start_dt < :end_dt AND end_dt > :start_dt LIMIT 1');
        $stmt->execute([':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':start_dt' => $startDt, ':end_dt' => $endDt]);
        if ($stmt->fetchColumn()) {
            $violations[] = se_violation('timeoff_conflict', $policies['timeoff_conflict'], 'Shift conflicts with approved time off.');
        }
    }

    if (($policies['availability_conflict']['enabled'] ?? false) === true) {
        $dayOfWeek = (int)date('w', strtotime($startDt));
        $shiftStart = substr($startDt, 11, 8);
        $shiftEnd = substr($endDt, 11, 8);
        $stmt = $pdo->prepare('SELECT id FROM staff_availability WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND day_of_week=:day_of_week AND status="unavailable" AND start_time < :shift_end AND end_time > :shift_start LIMIT 1');
        $stmt->execute([':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':day_of_week' => $dayOfWeek, ':shift_start' => $shiftStart, ':shift_end' => $shiftEnd]);
        if ($stmt->fetchColumn()) {
            $violations[] = se_violation('availability_conflict', $policies['availability_conflict'], 'Shift overlaps an unavailable availability window.');
        }
    }

    if (($policies['min_rest_between_shifts']['enabled'] ?? false) === true) {
        $minHours = (float)($policies['min_rest_between_shifts']['params']['hours'] ?? 10);
        $params = [':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':start_dt' => $startDt, ':end_dt' => $endDt];
        $excludeSql = '';
        if ($shiftId > 0) {
            $excludeSql = ' AND id != :exclude_id';
            $params[':exclude_id'] = $shiftId;
        }

        $prev = $pdo->prepare('SELECT id, end_dt FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND end_dt <= :start_dt' . $excludeSql . ' ORDER BY end_dt DESC LIMIT 1');
        $prev->execute($params);
        $prevShift = $prev->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($prevShift !== null) {
            $gapHours = (strtotime($startDt) - strtotime((string)$prevShift['end_dt'])) / 3600;
            if ($gapHours < $minHours) {
                $violations[] = se_violation('min_rest_between_shifts', $policies['min_rest_between_shifts'], 'Rest period is only ' . number_format($gapHours, 2) . 'h; minimum is ' . number_format($minHours, 2) . 'h.', ['gap_hours' => $gapHours, 'minimum_hours' => $minHours]);
            }
        }

        $next = $pdo->prepare('SELECT id, start_dt FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt >= :end_dt' . $excludeSql . ' ORDER BY start_dt ASC LIMIT 1');
        $next->execute($params);
        $nextShift = $next->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($nextShift !== null) {
            $gapHours = (strtotime((string)$nextShift['start_dt']) - strtotime($endDt)) / 3600;
            if ($gapHours < $minHours) {
                $violations[] = se_violation('min_rest_between_shifts', $policies['min_rest_between_shifts'], 'Next shift starts in ' . number_format($gapHours, 2) . 'h; minimum rest is ' . number_format($minHours, 2) . 'h.', ['gap_hours' => $gapHours, 'minimum_hours' => $minHours]);
            }
        }
    }

    $dayStart = substr($startDt, 0, 10) . ' 00:00:00';
    $dayEnd = (new DateTimeImmutable(substr($startDt, 0, 10)))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    if (($policies['max_daily_hours']['enabled'] ?? false) === true) {
        $params = [':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':day_start' => $dayStart, ':day_end' => $dayEnd];
        $excludeSql = '';
        if ($shiftId > 0) {
            $excludeSql = ' AND id != :exclude_id';
            $params[':exclude_id'] = $shiftId;
        }
        $stmt = $pdo->prepare('SELECT start_dt,end_dt,break_minutes FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt >= :day_start AND start_dt < :day_end' . $excludeSql);
        $stmt->execute($params);
        $sumHours = se_hours_between($startDt, $endDt, $breakMinutes);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $existing) {
            $sumHours += se_hours_between((string)$existing['start_dt'], (string)$existing['end_dt'], (int)($existing['break_minutes'] ?? 0));
        }
        $maxDaily = (float)($policies['max_daily_hours']['params']['hours'] ?? 12);
        if ($sumHours > $maxDaily) {
            $violations[] = se_violation('max_daily_hours', $policies['max_daily_hours'], 'Daily scheduled hours would be ' . number_format($sumHours, 2) . 'h, above max ' . number_format($maxDaily, 2) . 'h.');
        }
    }

    $weekStart = (new DateTimeImmutable(substr($startDt, 0, 10)))->modify('monday this week')->format('Y-m-d');
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
    $profile = null;
    if (se_table_exists($pdo, 'staff_labor_profile')) {
        $pr = $pdo->prepare('SELECT * FROM staff_labor_profile WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id LIMIT 1');
        $pr->execute([':restaurant_id' => $restaurantId, ':staff_id' => $staffId]);
        $profile = $pr->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (($policies['max_weekly_hours']['enabled'] ?? false) === true) {
        $params = [':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00'];
        $excludeSql = '';
        if ($shiftId > 0) {
            $excludeSql = ' AND id != :exclude_id';
            $params[':exclude_id'] = $shiftId;
        }
        $stmt = $pdo->prepare('SELECT start_dt,end_dt,break_minutes FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt >= :week_start AND start_dt < :week_end' . $excludeSql);
        $stmt->execute($params);
        $sumWeek = se_hours_between($startDt, $endDt, $breakMinutes);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $existing) {
            $sumWeek += se_hours_between((string)$existing['start_dt'], (string)$existing['end_dt'], (int)($existing['break_minutes'] ?? 0));
        }
        $maxWeekly = (float)($policies['max_weekly_hours']['params']['hours'] ?? 40);
        if ($profile !== null && (string)($profile['max_weekly_hours_override'] ?? '') !== '') {
            $maxWeekly = (float)$profile['max_weekly_hours_override'];
        }
        if ($sumWeek > $maxWeekly) {
            $violations[] = se_violation('max_weekly_hours', $policies['max_weekly_hours'], 'Weekly scheduled hours would be ' . number_format($sumWeek, 2) . 'h, above max ' . number_format($maxWeekly, 2) . 'h.');
        }
    }

    if (($policies['break_required_after_hours']['enabled'] ?? false) === true) {
        $hoursWorked = (float)($policies['break_required_after_hours']['params']['hours_worked'] ?? 6);
        $requiredBreak = (int)($policies['break_required_after_hours']['params']['break_minutes'] ?? 30);
        $duration = se_hours_between($startDt, $endDt, 0);
        if ($duration > $hoursWorked && $breakMinutes < $requiredBreak) {
            $violations[] = se_violation('break_required_after_hours', $policies['break_required_after_hours'], 'Shift is ' . number_format($duration, 2) . 'h with only ' . $breakMinutes . ' break minutes; at least ' . $requiredBreak . ' required.');
        }
    }

    if (($policies['minor_rules']['enabled'] ?? false) === true && (bool)($policies['minor_rules']['params']['enabled'] ?? true) === true && $profile !== null && (int)($profile['is_minor'] ?? 0) === 1) {
        $minorDailyMax = (float)($policies['minor_rules']['params']['max_daily_hours'] ?? 8);
        $minorWeeklyMax = (float)($policies['minor_rules']['params']['max_weekly_hours'] ?? 20);
        $latestEndHour = (int)($policies['minor_rules']['params']['latest_end_hour'] ?? 22);

        $dailyHours = se_hours_between($startDt, $endDt, $breakMinutes);
        if ($dailyHours > $minorDailyMax) {
            $violations[] = se_violation('minor_rules', $policies['minor_rules'], 'Minor daily hours ' . number_format($dailyHours, 2) . 'h exceed limit ' . number_format($minorDailyMax, 2) . 'h.');
        }

        $weekStmt = $pdo->prepare('SELECT start_dt,end_dt,break_minutes FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id AND status != "deleted" AND start_dt >= :week_start AND start_dt < :week_end' . ($shiftId > 0 ? ' AND id != :exclude_id' : ''));
        $args = [':restaurant_id' => $restaurantId, ':staff_id' => $staffId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00'];
        if ($shiftId > 0) {
            $args[':exclude_id'] = $shiftId;
        }
        $weekStmt->execute($args);
        $weekHours = $dailyHours;
        foreach ($weekStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $existing) {
            $weekHours += se_hours_between((string)$existing['start_dt'], (string)$existing['end_dt'], (int)($existing['break_minutes'] ?? 0));
        }
        if ($weekHours > $minorWeeklyMax) {
            $violations[] = se_violation('minor_rules', $policies['minor_rules'], 'Minor weekly hours would be ' . number_format($weekHours, 2) . 'h (limit ' . number_format($minorWeeklyMax, 2) . 'h).');
        }

        $endHour = (int)substr($endDt, 11, 2);
        if ($endHour >= $latestEndHour) {
            $violations[] = se_violation('minor_rules', $policies['minor_rules'], 'Minor shift ends at ' . substr($endDt, 11, 5) . '; latest allowed end is ' . sprintf('%02d:00', $latestEndHour) . '.');
        }
    }

    return $violations;
}

function se_check_week(PDO $pdo, int $restaurantId, string $weekStart, array $policies): array
{
    $weekEnd = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
    $stmt = $pdo->prepare('SELECT id,staff_id,start_dt,end_dt,break_minutes,status FROM shifts WHERE restaurant_id=:restaurant_id AND status != "deleted" AND start_dt >= :week_start AND start_dt < :week_end ORDER BY start_dt ASC');
    $stmt->execute([':restaurant_id' => $restaurantId, ':week_start' => $weekStart . ' 00:00:00', ':week_end' => $weekEnd . ' 00:00:00']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $violations = [];
    foreach ($rows as $shift) {
        $staffId = (int)($shift['staff_id'] ?? 0);
        if ($staffId <= 0) {
            continue;
        }
        foreach (se_check_shift($pdo, $restaurantId, $shift, $policies) as $v) {
            $v['shift_id'] = (int)$shift['id'];
            $v['staff_id'] = $staffId;
            $violations[] = $v;
        }
    }
    return $violations;
}
