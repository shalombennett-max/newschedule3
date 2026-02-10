<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$resId = schedule_restaurant_id();
if ($resId === null) {
    header('Location: /login.php');
    exit;
}

$week = schedule_week_window($_GET['week_start'] ?? null);
$roles = schedule_fetch_all('SELECT id,name,color,is_active FROM roles WHERE restaurant_id=:restaurant_id AND is_active=1 ORDER BY sort_order ASC, name ASC', [':restaurant_id'=>$resId]);
$staffOptions = schedule_staff_options($resId);

$start = $week['start'] . ' 00:00:00';
$end = (new DateTimeImmutable($week['start']))->modify('+7 days')->format('Y-m-d') . ' 00:00:00';
$params = [':restaurant_id'=>$resId, ':start_dt'=>$start, ':end_dt'=>$end];
$sql = 'SELECT s.id,s.staff_id,s.role_id,s.start_dt,s.end_dt,s.break_minutes,s.notes,s.status,
               r.name AS role_name,r.color AS role_color
        FROM shifts s
        LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
        WHERE s.restaurant_id=:restaurant_id AND s.status != "deleted" AND s.start_dt >= :start_dt AND s.start_dt < :end_dt';
if (!schedule_is_manager()) {
    $sql .= ' AND s.status="published"';
}
$sql .= ' ORDER BY s.start_dt ASC';
$shifts = schedule_fetch_all($sql, $params);

$staffMap = [];
foreach ($staffOptions as $opt) {
    $staffMap[(int)$opt['id']] = (string)$opt['name'];
}

$grouped = [];
for ($d = 0; $d < 7; $d++) {
    $date = (new DateTimeImmutable($week['start']))->modify('+' . $d . ' days')->format('Y-m-d');
    $grouped[$date] = [];
}
foreach ($shifts as $shift) {
    $date = substr((string)$shift['start_dt'], 0, 10);
    $grouped[$date][] = $shift;
}

schedule_page_start('Schedule', 'index');
?>
<section>
    <h2>Week View</h2>
    <div class="week-controls">
        <a class="button" href="/index.php?week_start=<?= htmlspecialchars($week['prev'], ENT_QUOTES, 'UTF-8') ?>">&larr; Prev Week</a>
        <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong>
        <a class="button" href="/index.php?week_start=<?= htmlspecialchars($week['next'], ENT_QUOTES, 'UTF-8') ?>">Next Week &rarr;</a>
    </div>

    <?php if (schedule_is_manager()): ?>
    <article class="card">
        <h3>Add Shift</h3>
        <form class="api-form" data-success="Shift created." method="post" action="/api.php">
            <input type="hidden" name="action" value="create_shift">
            <label>Date <input required type="date" name="date" value="<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Start <input required type="time" name="start_time" value="09:00"></label>
            <label>End <input required type="time" name="end_time" value="17:00"></label>
            <label>Role
                <select name="role_id">
                    <option value="">No role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Staff
                <select name="staff_id">
                    <option value="">Open shift</option>
                    <?php foreach ($staffOptions as $opt): ?>
                        <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars((string)$opt['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Break minutes <input type="number" name="break_minutes" value="0" min="0"></label>
            <label>Notes <input type="text" name="notes"></label>
            <button class="button" type="submit">Create Shift</button>
        </form>
    </article>
    <form class="api-form" data-success="Week published." method="post" action="/api.php">
        <input type="hidden" name="action" value="publish_week">
        <input type="hidden" name="week_start" value="<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>">
        <button class="button" type="submit">Publish Week</button>
    </form>
    <?php endif; ?>

    <?php foreach ($grouped as $date => $dayShifts): ?>
        <article class="card">
            <h3><?= htmlspecialchars((new DateTimeImmutable($date))->format('D, M j'), ENT_QUOTES, 'UTF-8') ?></h3>
            <?php if ($dayShifts === []): ?>
                <p>No shifts.</p>
            <?php else: ?>
                <?php foreach ($dayShifts as $shift): ?>
                    <div class="card">
                        <strong><?= htmlspecialchars(substr((string)$shift['start_dt'], 11, 5) . ' - ' . substr((string)$shift['end_dt'], 11, 5), ENT_QUOTES, 'UTF-8') ?></strong>
                        <p>
                            <?= htmlspecialchars((string)($shift['role_name'] ?: 'No role'), ENT_QUOTES, 'UTF-8') ?> •
                            <?= htmlspecialchars($shift['staff_id'] ? ($staffMap[(int)$shift['staff_id']] ?? ('Staff #' . (int)$shift['staff_id'])) : 'Open shift', ENT_QUOTES, 'UTF-8') ?> •
                            <span><?= htmlspecialchars((string)$shift['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </p>
                        <?php if (schedule_is_manager()): ?>
                        <form class="api-form" data-success="Shift updated." method="post" action="/api.php">
                            <input type="hidden" name="action" value="update_shift">
                            <input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars(substr((string)$shift['start_dt'], 0, 10), ENT_QUOTES, 'UTF-8') ?>">
                            <label>Start <input type="time" name="start_time" value="<?= htmlspecialchars(substr((string)$shift['start_dt'], 11, 5), ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label>End <input type="time" name="end_time" value="<?= htmlspecialchars(substr((string)$shift['end_dt'], 11, 5), ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label>Role
                                <select name="role_id">
                                    <option value="">No role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int)$role['id'] ?>" <?= (int)$shift['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Staff
                                <select name="staff_id">
                                    <option value="">Open shift</option>
                                    <?php foreach ($staffOptions as $opt): ?>
                                        <option value="<?= (int)$opt['id'] ?>" <?= (int)$shift['staff_id'] === (int)$opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$opt['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Break minutes <input type="number" name="break_minutes" min="0" value="<?= (int)$shift['break_minutes'] ?>"></label>
                            <label>Notes <input type="text" name="notes" value="<?= htmlspecialchars((string)($shift['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                            <button class="button" type="submit">Save</button>
                        </form>
                        <form class="api-form" data-confirm="Delete this shift?" data-success="Shift deleted." method="post" action="/api.php">
                            <input type="hidden" name="action" value="delete_shift">
                            <input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>">
                            <button class="button" type="submit">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php
schedule_page_end();