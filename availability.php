<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$resId = schedule_restaurant_id();
$myStaffId = schedule_current_staff_id();
if ($resId === null || $myStaffId === null) {
    header('Location: /login.php');
    exit;
}

$staffOptions = schedule_staff_options($resId);
$selectedStaffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : $myStaffId;
if (!schedule_is_manager()) {
    $selectedStaffId = $myStaffId;
}

$rows = schedule_fetch_all('SELECT day_of_week,start_time,end_time,status,notes FROM staff_availability WHERE restaurant_id=:restaurant_id AND staff_id=:staff_id ORDER BY day_of_week ASC', [
    ':restaurant_id'=>$resId, ':staff_id'=>$selectedStaffId,
]);
$byDay = [];
foreach ($rows as $row) {
    $byDay[(int)$row['day_of_week']] = $row;
}
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$readOnly = schedule_is_manager() && $selectedStaffId !== $myStaffId;

schedule_page_start('Availability', 'availability');
?>
<section>
    <h2>Weekly Availability</h2>
    <?php if (schedule_is_manager()): ?>
        <form method="get" action="/availability.php">
            <label>View staff
                <select name="staff_id" onchange="this.form.submit()">
                    <?php foreach ($staffOptions as $opt): ?>
                        <option value="<?= (int)$opt['id'] ?>" <?= (int)$opt['id'] === $selectedStaffId ? 'selected' : '' ?>><?= htmlspecialchars((string)$opt['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>

    <?php if ($readOnly): ?><p>Manager read-only view for this staff member.</p><?php endif; ?>

    <?php for ($i = 0; $i <= 6; $i++):
        $row = $byDay[$i] ?? null;
        $status = (string)($row['status'] ?? 'available');
        $start = isset($row['start_time']) ? substr((string)$row['start_time'], 0, 5) : '09:00';
        $end = isset($row['end_time']) ? substr((string)$row['end_time'], 0, 5) : '17:00';
    ?>
    <article class="card">
        <h3><?= htmlspecialchars($days[$i], ENT_QUOTES, 'UTF-8') ?></h3>
        <form class="api-form" data-success="Availability saved." method="post" action="/api.php">
            <input type="hidden" name="action" value="save_availability">
            <input type="hidden" name="staff_id" value="<?= $selectedStaffId ?>">
            <input type="hidden" name="day_of_week" value="<?= $i ?>">
            <label>Status
                <select name="status" <?= $readOnly ? 'disabled' : '' ?>>
                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="preferred" <?= $status === 'preferred' ? 'selected' : '' ?>>Preferred</option>
                    <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>
            </label>
            <label>Start <input type="time" name="start_time" value="<?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>" <?= $readOnly ? 'disabled' : '' ?>></label>
            <label>End <input type="time" name="end_time" value="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>" <?= $readOnly ? 'disabled' : '' ?>></label>
            <label>Notes <input type="text" name="notes" value="<?= htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $readOnly ? 'disabled' : '' ?>></label>
            <?php if (!$readOnly): ?><button class="button" type="submit">Save</button><?php endif; ?>
        </form>
    </article>
    <?php endfor; ?>
</section>
<?php
schedule_page_end();
