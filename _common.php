<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

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
{
    $candidates = [
        $_SESSION['staff_id'] ?? null,
        $_SESSION['current_staff_id'] ?? null,
        $_SESSION['employee_id'] ?? null,
        $_SESSION['user_id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_numeric($candidate) && (int)$candidate > 0) {
            return (int)$candidate;
        }
    }

    return null;
}

function schedule_next_param(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    return urlencode((string)$requestUri);
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

function schedule_is_manager(): bool
{
    $flags = [$_SESSION['is_manager'] ?? null, $_SESSION['is_admin'] ?? null, $_SESSION['can_manage_schedule'] ?? null];
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
            return $pdo;
        }
    }

    return null;
}

function schedule_json_success(array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function schedule_json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function schedule_json_error_with_details(string $message, int $code = 422, array $details = []): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message, 'details' => $details], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function schedule_fetch_all(string $sql, array $params = []): array
{
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $stmt = $pdo->prepare($sql);
    if (!$stmt || !$stmt->execute($params)) {
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function schedule_fetch_one(string $sql, array $params = []): ?array
{
    $rows = schedule_fetch_all($sql, $params);
    return $rows[0] ?? null;
}

function schedule_execute(string $sql, array $params = []): bool
{
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        return false;
    }
    $stmt = $pdo->prepare($sql);
    return $stmt ? $stmt->execute($params) : false;
}

function schedule_date(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTimeImmutable ? $dt->format('Y-m-d') : $fallback;
}

function schedule_time_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
        $value .= ':00';
    }
    $dt = DateTimeImmutable::createFromFormat('H:i:s', $value);
    return $dt instanceof DateTimeImmutable ? $dt->format('H:i:s') : null;
}

function schedule_week_window(?string $requestedStart): array
{
    $start = schedule_date((string)$requestedStart, '');
    if ($start === '') {
        $start = (new DateTimeImmutable('today'))->modify('monday this week')->format('Y-m-d');
    }
    $startDate = new DateTimeImmutable($start);
    $endDate = $startDate->modify('+6 days');

    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d'),
        'next' => $startDate->modify('+7 days')->format('Y-m-d'),
        'prev' => $startDate->modify('-7 days')->format('Y-m-d'),
        'label' => $startDate->format('M j') . ' - ' . $endDate->format('M j, Y'),
    ];
}

function schedule_hours_between(string $startDt, string $endDt, int $breakMinutes = 0): float
{
    try {
        $start = new DateTimeImmutable($startDt);
        $end = new DateTimeImmutable($endDt);
    } catch (Throwable $e) {
        return 0.0;
    }

    $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
    $seconds -= max(0, $breakMinutes) * 60;
    return max(0, $seconds / 3600);
}

function schedule_table_exists(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        $cache[$table] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function schedule_table_has_columns(string $tableName, array $requiredColumns): bool
{
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

function schedule_staff_options(int $restaurantId): array
{
    if ($restaurantId <= 0) {
        return [];
    }

    if (schedule_table_has_columns('staff_members', ['id', 'restaurant_id'])) {
        $nameExpr = schedule_table_has_columns('staff_members', ['name'])
            ? 'name'
            : "TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))";
        return schedule_fetch_all(
            'SELECT id, ' . $nameExpr . ' AS name FROM staff_members WHERE restaurant_id=:restaurant_id ORDER BY name ASC',
            [':restaurant_id' => $restaurantId]
        );
    }

    if (schedule_table_has_columns('users', ['id', 'restaurant_id'])) {
        $hasName = schedule_table_has_columns('users', ['name']);
        $nameExpr = $hasName ? 'name' : "TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))";
        return schedule_fetch_all(
            'SELECT id, ' . $nameExpr . ' AS name FROM users WHERE restaurant_id=:restaurant_id ORDER BY name ASC',
            [':restaurant_id' => $restaurantId]
        );
    }

    return [];
}

function schedule_validate_csrf_or_die(): void
{
    $csrf = $_POST['csrf_token'] ?? '';
    $sessionCsrf = $_SESSION['csrf_token'] ?? '';
    if (!is_string($csrf) || !is_string($sessionCsrf) || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function schedule_get_settings(int $restaurantId): array
{
    if ($restaurantId <= 0 || !schedule_table_exists('schedule_settings')) {
        return ['timezone' => 'America/New_York', 'demo_mode' => 0, 'aloha_csv_enabled' => 0, 'last_worker_run_at' => null];
    }

    $row = schedule_fetch_one('SELECT * FROM schedule_settings WHERE restaurant_id=:restaurant_id LIMIT 1', [':restaurant_id' => $restaurantId]);
    if ($row === null) {
        return ['timezone' => 'America/New_York', 'demo_mode' => 0, 'aloha_csv_enabled' => 0, 'last_worker_run_at' => null];
    }

    return [
        'timezone' => (string)($row['timezone'] ?? 'America/New_York'),
        'demo_mode' => (int)($row['demo_mode'] ?? 0),
        'aloha_csv_enabled' => (int)($row['aloha_csv_enabled'] ?? 0),
        'last_worker_run_at' => $row['last_worker_run_at'] ?? null,
    ];
}

function schedule_nav(string $active): string
{
    $links = [
        'index' => ['label' => 'Schedule', 'href' => '/index.php'],
        'my' => ['label' => 'My Schedule', 'href' => '/my.php'],
        'availability' => ['label' => 'Availability', 'href' => '/availability.php'],
        'time_off' => ['label' => 'Time Off', 'href' => '/time_off.php'],
        'swaps' => ['label' => 'Swaps', 'href' => '/swaps.php'],
        'announcements' => ['label' => 'Announcements', 'href' => '/announcements.php'],
        'notifications' => ['label' => 'Notifications', 'href' => '/notifications.php'],
        'labor_actuals' => ['label' => 'Labor', 'href' => '/labor_actuals.php'],
        'compliance' => ['label' => 'Compliance', 'href' => '/compliance.php'],
        'rules' => ['label' => 'Rules', 'href' => '/rules.php'],
        'roles' => ['label' => 'Roles', 'href' => '/roles.php'],
    ];

    if (schedule_is_manager()) {
        $links['setup'] = ['label' => 'Setup', 'href' => '/setup.php'];
        $links['compare'] = ['label' => 'Compare', 'href' => '/compare.php'];
        if (schedule_table_exists('job_queue')) {
            $links['jobs'] = ['label' => 'Jobs', 'href' => '/jobs/run_once.php'];
        }
    }

    $html = '<nav class="schedule-nav">';
    foreach ($links as $key => $cfg) {
        $class = $key === $active ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="' . htmlspecialchars($cfg['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') . '</a>';
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