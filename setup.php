<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/rules_engine.php';
require_once __DIR__ . '/scripts/seed_schedule_demo.php';

schedule_require_auth();
if (!schedule_is_manager()) {
    http_response_code(403);
    die('Forbidden');
}

$pdo = schedule_get_pdo();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    die('Database unavailable.');
}

$resId = schedule_restaurant_id();
$userId = schedule_user_id();
if ($resId === null || $userId === null) {
    http_response_code(401);
    die('Unauthorized');
}

function schedule_setup_ensure_settings_table(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schedule_settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      restaurant_id INT NOT NULL,
      timezone VARCHAR(64) NOT NULL DEFAULT "America/New_York",
      demo_mode TINYINT(1) NOT NULL DEFAULT 0,
      aloha_csv_enabled TINYINT(1) NOT NULL DEFAULT 0,
      last_worker_run_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_schedule_settings_restaurant (restaurant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

schedule_setup_ensure_settings_table($pdo);

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    schedule_validate_csrf_or_die();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_timezone') {
            $timezone = trim((string)($_POST['timezone'] ?? 'America/New_York'));
            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                throw new RuntimeException('Invalid timezone selected.');
            }
            $stmt = $pdo->prepare('INSERT INTO schedule_settings (restaurant_id, timezone, created_at)
                                   VALUES (:restaurant_id, :timezone, NOW())
                                   ON DUPLICATE KEY UPDATE timezone=:timezone');
            $stmt->execute([':restaurant_id' => $resId, ':timezone' => $timezone]);
            $message = 'Timezone saved.';
        }

        if ($action === 'create_roles') {
            $roles = preg_split('/\r\n|\r|\n/', (string)($_POST['roles'] ?? ''));
            if (!is_array($roles)) {
                $roles = [];
            }
            $insert = $pdo->prepare('INSERT INTO roles (restaurant_id, name, color, sort_order, is_active, created_at)
                                     VALUES (:restaurant_id, :name, :color, :sort_order, 1, NOW())');
            $sort = 0;
            foreach ($roles as $roleName) {
                $name = trim($roleName);
                if ($name === '') {
                    continue;
                }
                $exists = schedule_fetch_one('SELECT id FROM roles WHERE restaurant_id=:restaurant_id AND name=:name LIMIT 1', [':restaurant_id' => $resId, ':name' => $name]);
                if ($exists !== null) {
                    continue;
                }
                $insert->execute([
                    ':restaurant_id' => $resId,
                    ':name' => $name,
                    ':color' => '#2563eb',
                    ':sort_order' => $sort++,
                ]);
            }
            $message = 'Roles/stations template applied.';
        }

        if ($action === 'create_policy_set') {
            if (schedule_table_exists('schedule_policy_sets') && schedule_table_exists('schedule_policies')) {
                $policySetId = se_get_active_policy_set_id($pdo, $resId);
                $pdo->prepare('UPDATE schedule_policy_sets SET is_default=0 WHERE restaurant_id=:restaurant_id')->execute([':restaurant_id' => $resId]);
                $pdo->prepare('UPDATE schedule_policy_sets SET is_default=1, is_active=1 WHERE restaurant_id=:restaurant_id AND id=:id')->execute([':restaurant_id' => $resId, ':id' => $policySetId]);
                $message = 'Default policy set is active.';
            } else {
                throw new RuntimeException('Policy tables are missing. Run migrations 024 and 028 first.');
            }
        }

        if ($action === 'grant_manager_permission') {
            if (!schedule_table_exists('schedule_permissions')) {
                throw new RuntimeException('schedule_permissions table is missing.');
            }
            $stmt = $pdo->prepare('INSERT INTO schedule_permissions (restaurant_id, user_id, can_manage_schedule, can_manage_integrations, created_at)
                                   VALUES (:restaurant_id, :user_id, 1, 1, NOW())');
            $stmt->execute([':restaurant_id' => $resId, ':user_id' => $userId]);
            $message = 'Manager permissions granted.';
        }

        if ($action === 'toggle_aloha') {
            $enabled = (int)($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
            $stmt = $pdo->prepare('INSERT INTO schedule_settings (restaurant_id, timezone, aloha_csv_enabled, created_at)
                                   VALUES (:restaurant_id, :timezone, :enabled, NOW())
                                   ON DUPLICATE KEY UPDATE aloha_csv_enabled=:enabled');
            $current = schedule_get_settings($resId);
            $stmt->execute([':restaurant_id' => $resId, ':timezone' => $current['timezone'], ':enabled' => $enabled]);
            $message = 'Aloha CSV mode updated.';
        }

        if ($action === 'seed_demo') {
            $result = schedule_seed_demo($pdo, $resId, $userId);
            $message = 'Demo data seeded. Shifts: ' . (int)$result['shifts'];
        }

        if ($action === 'reset_demo') {
            if ((string)($_POST['danger_confirm'] ?? '') !== 'RESET') {
                throw new RuntimeException('Type RESET to confirm demo reset.');
            }
            $result = schedule_demo_reset($pdo, $resId);
            $message = 'Demo data reset. Deleted rows: ' . (int)$result['deleted_rows'];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = schedule_get_settings($resId);
$roleCount = (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM roles WHERE restaurant_id=:restaurant_id', [':restaurant_id' => $resId])['c'] ?? 0);
$policyCount = schedule_table_exists('schedule_policy_sets')
    ? (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM schedule_policy_sets WHERE restaurant_id=:restaurant_id', [':restaurant_id' => $resId])['c'] ?? 0)
    : 0;
$permissionReady = schedule_table_exists('schedule_permissions')
    ? ((int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM schedule_permissions WHERE restaurant_id=:restaurant_id AND user_id=:user_id AND can_manage_schedule=1', [':restaurant_id' => $resId, ':user_id' => $userId])['c'] ?? 0) > 0)
    : false;
$needsSetup = $roleCount === 0 || $policyCount === 0 || !$permissionReady;

$defaultRolesText = implode("\n", [
    'Server', 'Host', 'Bartender', 'Expo', 'Runner', 'Dishwasher', 'Line Cook', 'Prep Cook', 'Manager',
]);

schedule_page_start('Scheduling Setup Wizard', 'setup');
?>
<section>
    <?php if ($message !== ''): ?><article class="card"><strong><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></strong></article><?php endif; ?>
    <?php if ($error !== ''): ?><article class="card"><strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></strong></article><?php endif; ?>

    <article class="card">
        <h2>First-run status</h2>
        <p><?= $needsSetup ? 'Setup is still needed for this restaurant.' : 'Setup looks complete.' ?></p>
        <ul>
            <li>Roles configured: <?= $roleCount ?></li>
            <li>Policy sets configured: <?= $policyCount ?></li>
            <li>Current user has schedule manager permission: <?= $permissionReady ? 'Yes' : 'No' ?></li>
        </ul>
    </article>

    <article class="card">
        <h2>Step 1: Timezone</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_timezone">
            <label>Restaurant timezone
                <select name="timezone">
                    <?php foreach (['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'UTC'] as $tz): ?>
                        <option value="<?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>" <?= $tz === $settings['timezone'] ? 'selected' : '' ?>><?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button" type="submit">Save Timezone</button>
        </form>
    </article>

    <article class="card">
        <h2>Step 2: Default roles/stations</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_roles">
            <label>Roles (one per line)
                <textarea name="roles"><?= htmlspecialchars($defaultRolesText, ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <button class="button" type="submit">Create Missing Roles</button>
        </form>
    </article>

    <article class="card">
        <h2>Step 3: Default policy set</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_policy_set">
            <button class="button" type="submit">Create / Activate Default Policy Set</button>
        </form>
    </article>

    <article class="card">
        <h2>Step 4: Permissions</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="grant_manager_permission">
            <button class="button" type="submit">Grant Me Schedule Manager Access</button>
        </form>
    </article>

    <article class="card">
        <h2>Step 5: Optional Aloha CSV mode</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="toggle_aloha">
            <input type="hidden" name="enabled" value="<?= (int)$settings['aloha_csv_enabled'] === 1 ? '0' : '1' ?>">
            <button class="button" type="submit"><?= (int)$settings['aloha_csv_enabled'] === 1 ? 'Disable' : 'Enable' ?> Aloha CSV Import Mode</button>
        </form>
        <p><a class="button" href="/integrations/aloha.php">Open Aloha Integration</a></p>
    </article>

    <article class="card">
        <h2>Demo mode</h2>
        <p>Demo mode is <?= (int)$settings['demo_mode'] === 1 ? 'enabled' : 'disabled' ?> for this restaurant.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="seed_demo">
            <button class="button" type="submit">Seed Demo Data</button>
        </form>
        <form method="post" onsubmit="return confirm('Reset demo data for this restaurant?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reset_demo">
            <label>Type RESET to confirm <input type="text" name="danger_confirm" required></label>
            <button class="button" type="submit">Reset Demo Data</button>
        </form>
        <p><a class="button" href="/docs/DEMO_MODE.md">Demo documentation</a></p>
    </article>

    <article class="card">
        <h2>System Status</h2>
        <ul>
            <li>Cron worker last run: <?= htmlspecialchars((string)($settings['last_worker_run_at'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></li>
            <li>Aloha CSV mode: <?= (int)$settings['aloha_csv_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></li>
            <li>Demo mode: <?= (int)$settings['demo_mode'] === 1 ? 'Enabled' : 'Disabled' ?></li>
        </ul>
    </article>
</section>
<?php
schedule_page_end();