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
$roles = schedule_fetch_all('SELECT id,name,color,is_active FROM roles WHERE restaurant_id=:restaurant_id AND is_active=1 ORDER BY sort_order ASC, name ASC', [':restaurant_id' => $resId]);
$staffOptions = schedule_staff_options($resId);

$start = $week['start'] . ' 00:00:00';
$end = (new DateTimeImmutable($week['start']))->modify('+7 days')->format('Y-m-d') . ' 00:00:00';
$params = [':restaurant_id' => $resId, ':start_dt' => $start, ':end_dt' => $end];
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

$callouts = schedule_is_manager()
    ? schedule_fetch_all('SELECT c.id,c.status,c.reason,s.id AS shift_id,s.start_dt,s.end_dt,r.name AS role_name,c.staff_id
                          FROM callouts c
                          INNER JOIN shifts s ON s.restaurant_id=c.restaurant_id AND s.id=c.shift_id
                          LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
                          WHERE c.restaurant_id=:restaurant_id AND c.status IN ("reported","coverage_requested")
                          ORDER BY c.created_at DESC', [':restaurant_id' => $resId])
    : [];

$pendingSwaps = schedule_is_manager()
    ? schedule_fetch_all('SELECT tr.id,tr.shift_id,tr.from_staff_id,tr.to_staff_id,tr.notes,s.start_dt,s.end_dt,r.name AS role_name
                          FROM shift_trade_requests tr
                          INNER JOIN shifts s ON s.restaurant_id=tr.restaurant_id AND s.id=tr.shift_id
                          LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
                          WHERE tr.restaurant_id=:restaurant_id AND tr.status="pending"
                          ORDER BY tr.created_at DESC', [':restaurant_id' => $resId])
    : [];

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
                    <select name="role_id"><option value="">No role</option><?php foreach ($roles as $role): ?><option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                </label>
                <label>Staff
                    <select name="staff_id"><option value="">Open shift</option><?php foreach ($staffOptions as $opt): ?><option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars((string)$opt['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
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

        <article class="card">
            <h3>Call-out Alerts <span class="badge-chip"><?= count($callouts) ?></span></h3>
            <?php if ($callouts === []): ?><p class="empty-state">No active call-outs.</p><?php endif; ?>
            <?php foreach ($callouts as $callout): ?>
                <div class="card">
                    <p><strong>Staff #<?= (int)$callout['staff_id'] ?></strong> • <?= htmlspecialchars((string)$callout['role_name'] ?: 'No role', ENT_QUOTES, 'UTF-8') ?></p>
                    <p><?= htmlspecialchars((string)$callout['start_dt'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$callout['end_dt'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p>Status: <?= htmlspecialchars((string)$callout['status'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($callout['reason'])): ?><p><?= htmlspecialchars((string)$callout['reason'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <form class="api-form" data-success="Coverage requested." method="post" action="/api.php">
                        <input type="hidden" name="action" value="request_coverage">
                        <input type="hidden" name="callout_id" value="<?= (int)$callout['id'] ?>">
                        <button class="button" type="submit">Request Coverage</button>
                    </form>
                    <form class="api-form" data-success="Callout closed." method="post" action="/api.php">
                        <input type="hidden" name="action" value="close_callout">
                        <input type="hidden" name="callout_id" value="<?= (int)$callout['id'] ?>">
                        <button class="button" type="submit">Close</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>

        <article class="card">
            <h3>Pending Swap Requests <span class="badge-chip"><?= count($pendingSwaps) ?></span></h3>
            <?php if ($pendingSwaps === []): ?><p class="empty-state">No pending swaps.</p><?php endif; ?>
            <?php foreach ($pendingSwaps as $swap): ?>
                <div class="card">
                    <p>From #<?= (int)$swap['from_staff_id'] ?> to <?= $swap['to_staff_id'] ? ('#' . (int)$swap['to_staff_id']) : 'Anyone' ?></p>
                    <p><?= htmlspecialchars((string)$swap['start_dt'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$swap['end_dt'], ENT_QUOTES, 'UTF-8') ?></p>
                    <form class="api-form" data-success="Swap approved." method="post" action="/api.php">
                        <input type="hidden" name="action" value="approve_swap_request">
                        <input type="hidden" name="request_id" value="<?= (int)$swap['id'] ?>">
                        <button class="button" type="submit">Approve</button>
                    </form>
                    <form class="api-form" data-success="Swap denied." method="post" action="/api.php">
                        <input type="hidden" name="action" value="deny_swap_request">
                        <input type="hidden" name="request_id" value="<?= (int)$swap['id'] ?>">
                        <button class="button" type="submit">Deny</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
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
                                    <select name="role_id"><option value="">No role</option><?php foreach ($roles as $role): ?><option value="<?= (int)$role['id'] ?>" <?= (int)$shift['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                </label>
                                <label>Staff
                                    <select name="staff_id"><option value="">Open shift</option><?php foreach ($staffOptions as $opt): ?><option value="<?= (int)$opt['id'] ?>" <?= (int)$shift['staff_id'] === (int)$opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$opt['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
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