<?php
declare(strict_types=1);

require_once __DIR__ . '/schedule/_auth.php';

function schedule_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

function schedule_user_id(): ?int
{
    $candidate = $_SESSION['user_id'] ?? null;
    return is_numeric($candidate) ? (int)$candidate : null;
}

function schedule_restaurant_id(): ?int
{
    $candidate = $_SESSION['res_id'] ?? ($_SESSION['restaurant_id'] ?? null);
    return is_numeric($candidate) ? (int)$candidate : null;
}

function schedule_current_staff_id(): ?int
@@ -30,69 +32,79 @@ function schedule_current_staff_id(): ?int
}

function schedule_next_param(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    return urlencode($requestUri);
}

function schedule_require_auth(bool $api = false): void
{
    schedule_start_session();

    $hasAuth = schedule_user_id() !== null && schedule_restaurant_id() !== null;
    if ($hasAuth) {
        return;
    }

    if ($api) {
        schedule_json_error('Unauthorized', 401);
    }

    header('Location: /login.php?next=' . schedule_next_param());
    exit;
}

function schedule_is_manager(): bool␊
{␊
    $flags = [$_SESSION['is_manager'] ?? null, $_SESSION['is_admin'] ?? null, $_SESSION['can_manage_schedule'] ?? null];␊
    foreach ($flags as $flag) {
        if ($flag === true || $flag === 1 || $flag === '1') {
            return true;
        }
    }

    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);
    if (is_string($role) && in_array(strtolower($role), ['manager', 'admin', 'owner'], true)) {
        return true;
    }

    return schedule_has_permission('can_manage_schedule');
}

function schedule_can_manage_integrations(): bool
{
    return schedule_has_permission('can_manage_integrations') || schedule_is_manager();
}

function schedule_require_manager_api(string $capability = 'schedule'): void
{
    $allowed = $capability === 'integrations' ? schedule_can_manage_integrations() : schedule_is_manager();
    if (!$allowed) {
        schedule_json_error('Forbidden', 403);
    }
}

function schedule_get_pdo(): ?PDO
{
    static $pdo = false;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbFile = __DIR__ . '/db.php';
    if (is_file($dbFile)) {
        require_once $dbFile;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
        return $pdo;
    }
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        $pdo = $GLOBALS['db'];
        return $pdo;
    }
    if (function_exists('db')) {
        $conn = db();
        if ($conn instanceof PDO) {
            $pdo = $conn;
@@ -294,26 +306,26 @@ function schedule_nav(string $active): string
        $class = $key === $active ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
    }
    $html .= '</nav>';

    return $html;
}

function schedule_page_start(string $title, string $active): void
{
    $csrf = $_SESSION['csrf_token'] ?? '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="/assets/schedule.css">';
    echo '</head><body>';
    echo '<header><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo schedule_nav($active);
    echo '</header><main data-csrf-token="' . htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div id="toast" class="toast" hidden></div>';
}

function schedule_page_end(): void
{
    echo '</main><script src="/assets/schedule.js"></script></body></html>';
}