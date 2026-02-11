<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

function schedule_demo_ensure_tables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schedule_demo_staff (
      id INT AUTO_INCREMENT PRIMARY KEY,
      restaurant_id INT NOT NULL,
      display_name VARCHAR(120) NOT NULL,
      role_name VARCHAR(100) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_schedule_demo_staff_restaurant (restaurant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS schedule_demo_tags (
      id INT AUTO_INCREMENT PRIMARY KEY,
      restaurant_id INT NOT NULL,
      entity_type VARCHAR(64) NOT NULL,
      entity_id INT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_schedule_demo_tags_restaurant (restaurant_id),
      KEY idx_schedule_demo_tags_entity (restaurant_id, entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function schedule_demo_tag(PDO $pdo, int $restaurantId, string $entityType, int $entityId): void
{
    $stmt = $pdo->prepare('INSERT INTO schedule_demo_tags (restaurant_id, entity_type, entity_id, created_at) VALUES (:restaurant_id, :entity_type, :entity_id, NOW())');
    $stmt->execute([':restaurant_id' => $restaurantId, ':entity_type' => $entityType, ':entity_id' => $entityId]);
}

function schedule_demo_reset(PDO $pdo, int $restaurantId): array
{
    schedule_demo_ensure_tables($pdo);
    $rows = schedule_fetch_all('SELECT entity_type, entity_id FROM schedule_demo_tags WHERE restaurant_id=:restaurant_id ORDER BY id DESC', [':restaurant_id' => $restaurantId]);
    $deleted = 0;
    $supported = ['shifts', 'time_off_requests', 'announcements', 'callouts', 'shift_pickup_requests', 'roles', 'schedule_demo_staff'];

    foreach ($rows as $row) {
        $table = (string)($row['entity_type'] ?? '');
        $id = (int)($row['entity_id'] ?? 0);
        if ($id <= 0 || !in_array($table, $supported, true) || !schedule_table_exists($table)) {
            continue;
        }
        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE restaurant_id=:restaurant_id AND id=:id LIMIT 1');
        if ($stmt && $stmt->execute([':restaurant_id' => $restaurantId, ':id' => $id])) {
            $deleted += $stmt->rowCount();
        }
    }

    $pdo->prepare('DELETE FROM schedule_demo_tags WHERE restaurant_id=:restaurant_id')->execute([':restaurant_id' => $restaurantId]);

    return ['deleted_rows' => $deleted];
}

function schedule_seed_demo(PDO $pdo, int $restaurantId, int $userId = 0): array
{
    schedule_demo_ensure_tables($pdo);

    $created = ['roles' => 0, 'demo_staff' => 0, 'shifts' => 0, 'time_off_requests' => 0, 'announcements' => 0, 'callouts' => 0, 'pickup_requests' => 0];

    $roleNames = ['Server', 'Host', 'Bartender', 'Line Cook'];
    foreach ($roleNames as $idx => $roleName) {
        $existing = schedule_fetch_one('SELECT id FROM roles WHERE restaurant_id=:restaurant_id AND name=:name LIMIT 1', [':restaurant_id' => $restaurantId, ':name' => $roleName]);
        if ($existing !== null) {
            continue;
        }
        $stmt = $pdo->prepare('INSERT INTO roles (restaurant_id, name, color, sort_order, is_active, created_at) VALUES (:restaurant_id, :name, :color, :sort_order, 1, NOW())');
        $stmt->execute([':restaurant_id' => $restaurantId, ':name' => $roleName, ':color' => '#3b82f6', ':sort_order' => $idx]);
        $roleId = (int)$pdo->lastInsertId();
        schedule_demo_tag($pdo, $restaurantId, 'roles', $roleId);
        $created['roles']++;
    }

    $demoPeople = [
        ['Avery Demo', 'Server'],
        ['Jordan Demo', 'Host'],
        ['Taylor Demo', 'Line Cook'],
    ];
    foreach ($demoPeople as $person) {
        $stmt = $pdo->prepare('INSERT INTO schedule_demo_staff (restaurant_id, display_name, role_name, created_at) VALUES (:restaurant_id, :display_name, :role_name, NOW())');
        $stmt->execute([':restaurant_id' => $restaurantId, ':display_name' => $person[0], ':role_name' => $person[1]]);
        $id = (int)$pdo->lastInsertId();
        schedule_demo_tag($pdo, $restaurantId, 'schedule_demo_staff', $id);
        $created['demo_staff']++;
    }

    $roles = schedule_fetch_all('SELECT id FROM roles WHERE restaurant_id=:restaurant_id ORDER BY id ASC LIMIT 4', [':restaurant_id' => $restaurantId]);
    $staffOptions = schedule_staff_options($restaurantId);
    $staffIds = array_map(static fn(array $r): int => (int)$r['id'], $staffOptions);

    $startMonday = (new DateTimeImmutable('monday this week'))->modify('+1 week');
    for ($d = 0; $d < 14; $d++) {
        $date = $startMonday->modify('+' . $d . ' days')->format('Y-m-d');
        for ($s = 0; $s < 2; $s++) {
            $start = $s === 0 ? '09:00:00' : '16:00:00';
            $end = $s === 0 ? '15:00:00' : '22:00:00';
            $roleId = isset($roles[($d + $s) % max(1, count($roles))]) ? (int)$roles[($d + $s) % count($roles)]['id'] : null;
            $staffId = ($d + $s) % 4 === 0 ? null : ($staffIds !== [] ? $staffIds[($d + $s) % count($staffIds)] : null);
            $stmt = $pdo->prepare('INSERT INTO shifts (restaurant_id, staff_id, role_id, start_dt, end_dt, break_minutes, notes, status, created_by, created_at)
                                   VALUES (:restaurant_id, :staff_id, :role_id, :start_dt, :end_dt, 30, :notes, :status, :created_by, NOW())');
            $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':staff_id' => $staffId,
                ':role_id' => $roleId,
                ':start_dt' => $date . ' ' . $start,
                ':end_dt' => $date . ' ' . $end,
                ':notes' => 'Demo shift',
                ':status' => 'published',
                ':created_by' => $userId > 0 ? $userId : 1,
            ]);
            $shiftId = (int)$pdo->lastInsertId();
            schedule_demo_tag($pdo, $restaurantId, 'shifts', $shiftId);
            $created['shifts']++;
        }
    }

    if ($staffIds !== [] && schedule_table_exists('time_off_requests')) {
        for ($i = 0; $i < 2; $i++) {
            $day = $startMonday->modify('+' . (2 + $i * 4) . ' days')->format('Y-m-d');
            $stmt = $pdo->prepare('INSERT INTO time_off_requests (restaurant_id, staff_id, start_dt, end_dt, reason, status, created_at)
                                   VALUES (:restaurant_id, :staff_id, :start_dt, :end_dt, :reason, :status, NOW())');
            $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':staff_id' => $staffIds[$i % count($staffIds)],
                ':start_dt' => $day . ' 00:00:00',
                ':end_dt' => $day . ' 23:59:59',
                ':reason' => 'Demo request',
                ':status' => 'pending',
            ]);
            $id = (int)$pdo->lastInsertId();
            schedule_demo_tag($pdo, $restaurantId, 'time_off_requests', $id);
            $created['time_off_requests']++;
        }
    }

    if (schedule_table_exists('announcements')) {
        $stmt = $pdo->prepare('INSERT INTO announcements (restaurant_id, title, body, audience, starts_at, created_by, created_at)
                               VALUES (:restaurant_id, :title, :body, :audience, NOW(), :created_by, NOW())');
        $stmt->execute([
            ':restaurant_id' => $restaurantId,
            ':title' => 'Welcome to Demo Week',
            ':body' => 'This is seeded demo data for onboarding and sales walkthroughs.',
            ':audience' => 'all',
            ':created_by' => $userId > 0 ? $userId : 1,
        ]);
        $id = (int)$pdo->lastInsertId();
        schedule_demo_tag($pdo, $restaurantId, 'announcements', $id);
        $created['announcements']++;
    }

    if (schedule_table_exists('callouts')) {
        $shift = schedule_fetch_one('SELECT id, staff_id FROM shifts WHERE restaurant_id=:restaurant_id ORDER BY id DESC LIMIT 1', [':restaurant_id' => $restaurantId]);
        if ($shift !== null) {
            $stmt = $pdo->prepare('INSERT INTO callouts (restaurant_id, shift_id, staff_id, reason, status, created_at)
                                   VALUES (:restaurant_id, :shift_id, :staff_id, :reason, :status, NOW())');
            $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':shift_id' => (int)$shift['id'],
                ':staff_id' => max(1, (int)($shift['staff_id'] ?? ($staffIds[0] ?? 1))),
                ':reason' => 'Demo callout',
                ':status' => 'reported',
            ]);
            $id = (int)$pdo->lastInsertId();
            schedule_demo_tag($pdo, $restaurantId, 'callouts', $id);
            $created['callouts']++;
        }
    }

    if (schedule_table_exists('shift_pickup_requests')) {
        $openShift = schedule_fetch_one('SELECT id FROM shifts WHERE restaurant_id=:restaurant_id AND staff_id IS NULL ORDER BY id DESC LIMIT 1', [':restaurant_id' => $restaurantId]);
        if ($openShift !== null && $staffIds !== []) {
            $stmt = $pdo->prepare('INSERT INTO shift_pickup_requests (restaurant_id, shift_id, staff_id, status, created_at)
                                   VALUES (:restaurant_id, :shift_id, :staff_id, :status, NOW())');
            $stmt->execute([
                ':restaurant_id' => $restaurantId,
                ':shift_id' => (int)$openShift['id'],
                ':staff_id' => $staffIds[0],
                ':status' => 'pending',
            ]);
            $id = (int)$pdo->lastInsertId();
            schedule_demo_tag($pdo, $restaurantId, 'shift_pickup_requests', $id);
            $created['pickup_requests']++;
        }
    }

    if (schedule_table_exists('schedule_settings')) {
        $stmt = $pdo->prepare('INSERT INTO schedule_settings (restaurant_id, timezone, demo_mode, created_at)
                               VALUES (:restaurant_id, :timezone, 1, NOW())
                               ON DUPLICATE KEY UPDATE demo_mode=1');
        $stmt->execute([':restaurant_id' => $restaurantId, ':timezone' => 'America/New_York']);
    }

    return $created;
}

function schedule_demo_parse_args(array $argv): array
{
    $parsed = ['restaurant' => 0, 'user' => 0, 'reset' => false];
    foreach ($argv as $arg) {
        if (strpos($arg, '--restaurant=') === 0) {
            $parsed['restaurant'] = (int)substr($arg, 13);
        } elseif (strpos($arg, '--user=') === 0) {
            $parsed['user'] = (int)substr($arg, 7);
        } elseif ($arg === '--reset') {
            $parsed['reset'] = true;
        }
    }
    return $parsed;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        fwrite(STDERR, "Database unavailable.\n");
        exit(1);
    }

    $args = schedule_demo_parse_args($argv);
    if ((int)$args['restaurant'] <= 0) {
        fwrite(STDERR, "Usage: php scripts/seed_schedule_demo.php --restaurant=<id> [--user=<id>] [--reset]\n");
        exit(1);
    }

    $result = (bool)$args['reset']
        ? schedule_demo_reset($pdo, (int)$args['restaurant'])
        : schedule_seed_demo($pdo, (int)$args['restaurant'], (int)$args['user']);

    fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL);
}