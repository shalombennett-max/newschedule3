<?php
declare(strict_types=1);

function schedule_permissions_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $pdo = function_exists('schedule_get_pdo') ? schedule_get_pdo() : null;
    if (!$pdo instanceof PDO) {
        $exists = false;
        return false;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'schedule_permissions'");
        $exists = $stmt instanceof PDOStatement && $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function schedule_has_permission(string $permission): bool
{
    static $cache = [];
    $allowed = ['can_manage_schedule', 'can_manage_integrations'];
    if (!in_array($permission, $allowed, true)) {
        return false;
    }

    $userId = function_exists('schedule_user_id') ? schedule_user_id() : null;
    $restaurantId = function_exists('schedule_restaurant_id') ? schedule_restaurant_id() : null;
    if ($userId === null || $restaurantId === null) {
        return false;
    }

    $key = $restaurantId . ':' . $userId;
    if (isset($cache[$key])) {
        return (int)($cache[$key][$permission] ?? 0) === 1;
    }

    $cache[$key] = ['can_manage_schedule' => 0, 'can_manage_integrations' => 0];
    if (!schedule_permissions_table_exists()) {
        return false;
    }

    $pdo = function_exists('schedule_get_pdo') ? schedule_get_pdo() : null;
    if (!$pdo instanceof PDO) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT can_manage_schedule, can_manage_integrations FROM schedule_permissions WHERE restaurant_id=:restaurant_id AND user_id=:user_id ORDER BY id DESC LIMIT 1');
    if ($stmt && $stmt->execute([':restaurant_id' => $restaurantId, ':user_id' => $userId])) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $cache[$key]['can_manage_schedule'] = (int)($row['can_manage_schedule'] ?? 0);
            $cache[$key]['can_manage_integrations'] = (int)($row['can_manage_integrations'] ?? 0);
        }
    }

    return (int)($cache[$key][$permission] ?? 0) === 1;
}