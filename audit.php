<?php
declare(strict_types=1);

function schedule_audit_log(PDO $pdo, int $restaurantId, int $userId, string $action, string $entityType, string $entityId, ?array $beforeArr = null, ?array $afterArr = null): void
{
    $beforeJson = $beforeArr === null ? null : json_encode($beforeArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = $afterArr === null ? null : json_encode($afterArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($beforeJson) && $beforeArr !== null) {
        $beforeJson = '{"error":"encode_failed"}';
    }
    if (!is_string($afterJson) && $afterArr !== null) {
        $afterJson = '{"error":"encode_failed"}';
    }

    if ($beforeJson !== null && strlen($beforeJson) > 8000) {
        $beforeJson = substr($beforeJson, 0, 8000);
    }
    if ($afterJson !== null && strlen($afterJson) > 8000) {
        $afterJson = substr($afterJson, 0, 8000);
    }

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $stmt = $pdo->prepare('INSERT INTO audit_log (restaurant_id, user_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent, created_at)
                           VALUES (:restaurant_id, :user_id, :action, :entity_type, :entity_id, :before_json, :after_json, :ip, :user_agent, NOW())');
    if ($stmt) {
        $stmt->execute([
            ':restaurant_id' => $restaurantId,
            ':user_id' => $userId,
            ':action' => substr($action, 0, 64),
            ':entity_type' => substr($entityType, 0, 64),
            ':entity_id' => substr($entityId, 0, 64),
            ':before_json' => $beforeJson,
            ':after_json' => $afterJson,
            ':ip' => $ip !== '' ? $ip : null,
            ':user_agent' => $ua !== '' ? $ua : null,
        ]);
    }
}
