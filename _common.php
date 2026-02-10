<?php
declare(strict_types=1);

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
    return is_numeric($candidate) ? (int) $candidate : null;
}

function schedule_restaurant_id(): ?int
{
    $candidate = $_SESSION['res_id'] ?? ($_SESSION['restaurant_id'] ?? null);
    return is_numeric($candidate) ? (int) $candidate : null;
}

function schedule_current_staff_id(): ?int
{
    return schedule_user_id();
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

function schedule_is_manager(): bool
{
    $flags = [
        $_SESSION['is_manager'] ?? null,
        $_SESSION['is_admin'] ?? null,
        $_SESSION['can_manage_schedule'] ?? null,
    ];

    foreach ($flags as $flag) {
        if ($flag === true || $flag === 1 || $flag === '1') {
            return true;
        }
    }

    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);
    return is_string($role) && in_array(strtolower($role), ['manager', 'admin', 'owner'], true);
}

function schedule_require_manager_api(): void
{
    if (!schedule_is_manager()) {
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

    $pdo = null;
    return null;
}

function schedule_fetch_all(string $sql, array $params): array
{
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (!$stmt->execute($params)) {
        return [];
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function schedule_fetch_one(string $sql, array $params): ?array
{
    $rows = schedule_fetch_all($sql, $params);
    return $rows[0] ?? null;
}

function schedule_execute(string $sql, array $params): bool
{
    $pdo = schedule_get_pdo();
    if (!$pdo instanceof PDO) {
        return false;
    }

    $stmt = $pdo->prepare($sql);
    return $stmt ? $stmt->execute($params) : false;
}

function schedule_json_success($data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function schedule_json_error(string $message, int $code = 422): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function schedule_date(string $value, string $default): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt instanceof DateTime && $dt->format('Y-m-d') === $value) ? $value : $default;
}

function schedule_time_or_null(?string $value): ?string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('H:i', $value);
    return ($dt instanceof DateTime && $dt->format('H:i') === $value) ? $value . ':00' : null;
}

function schedule_week_window(?string $weekStart): array
{
    $today = new DateTimeImmutable('today');
    $defaultStart = $today->modify('monday this week')->format('Y-m-d');
    $start = schedule_date((string) $weekStart, $defaultStart);
    $startDt = new DateTimeImmutable($start);
    $endDt = $startDt->modify('+6 days');

    return [
        'start' => $startDt->format('Y-m-d'),
        'end' => $endDt->format('Y-m-d'),
        'label' => $startDt->format('M j, Y') . ' - ' . $endDt->format('M j, Y'),
        'prev' => $startDt->modify('-7 days')->format('Y-m-d'),
        'next' => $startDt->modify('+7 days')->format('Y-m-d'),
    ];
}

function schedule_datetime_from_inputs(string $date, string $time): ?string
{
    $cleanDate = schedule_date($date, '');
    $cleanTime = schedule_time_or_null($time);
    if ($cleanDate === '' || $cleanTime === null) {
        return null;
    }
    return $cleanDate . ' ' . substr($cleanTime, 0, 8);
}

function schedule_staff_options(int $restaurantId): array
{
    $options = [];
    $rows = schedule_fetch_all(
        'SELECT DISTINCT staff_id FROM shifts WHERE restaurant_id = :restaurant_id AND staff_id IS NOT NULL
         UNION SELECT DISTINCT staff_id FROM staff_availability WHERE restaurant_id = :restaurant_id
         UNION SELECT DISTINCT staff_id FROM time_off_requests WHERE restaurant_id = :restaurant_id',
        [':restaurant_id' => $restaurantId]
    );

    foreach ($rows as $row) {
        if (is_numeric($row['staff_id'] ?? null)) {
            $staffId = (int) $row['staff_id'];
            $options[$staffId] = 'Staff #' . $staffId;
        }
    }

    $current = schedule_current_staff_id();
    if ($current !== null) {
        $options[$current] = 'Me (#' . $current . ')';
    }

    ksort($options);
    $result = [];
    foreach ($options as $id => $name) {
        $result[] = ['id' => $id, 'name' => $name];
    }
    return $result;
}

function schedule_nav(string $active): string
{
    $isManager = schedule_is_manager();
    $links = [
        'index' => ['/index.php', 'Schedule'],
        'my' => ['/my.php', 'My Schedule'],
        'availability' => ['/availability.php', 'Availability'],
        'time_off' => ['/time_off.php', 'Time Off'],
    ];

    if ($isManager) {
        $links['roles'] = ['/roles.php', 'Roles'];
    }

    $html = '<nav class="schedule-nav">';
    foreach ($links as $key => [$href, $label]) {
        $class = $key === $active ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
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
    echo '</header><main data-csrf-token="' . htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div id="toast" class="toast" hidden></div>';
}

function schedule_page_end(): void
{
    echo '</main><script src="/assets/schedule.js"></script></body></html>';
}