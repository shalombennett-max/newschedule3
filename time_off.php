<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$resId = schedule_restaurant_id();
$staffId = schedule_current_staff_id();
if ($resId === null || $staffId === null) {
    header('Location: /login.php');
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$params = [':restaurant_id' => $resId];
$sql = 'SELECT id,staff_id,start_dt,end_dt,reason,status,reviewed_by,reviewed_at FROM time_off_requests WHERE restaurant_id=:restaurant_id';
if (!schedule_is_manager()) {
    $sql .= ' AND staff_id=:staff_id';
    $params[':staff_id'] = $staffId;
} elseif (in_array($statusFilter, ['pending', 'approved', 'denied'], true)) {
    $sql .= ' AND status=:status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$rows = schedule_fetch_all($sql, $params);

schedule_page_start('Time Off', 'time_off');
?>
<section>
    <h2>Time-Off Requests</h2>
    <article class="card">
        <h3>Request Time Off</h3>
        <form class="api-form" data-success="Time-off request submitted." method="post" action="/api.php">
            <input type="hidden" name="action" value="create_time_off">
            <label>Start <input required type="datetime-local" name="start_dt"></label>
            <label>End <input required type="datetime-local" name="end_dt"></label>
            <label>Reason <textarea required name="reason"></textarea></label>
            <button class="button" type="submit">Submit Request</button>
        </form>
    </article>

    <?php if (schedule_is_manager()): ?>
        <form method="get" action="/time_off.php">
            <label>Status
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="denied" <?= $statusFilter === 'denied' ? 'selected' : '' ?>>Denied</option>
                </select>
            </label>
        </form>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p>No time-off requests found.</p>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <article class="card">
                <p><strong>Staff #<?= (int)$row['staff_id'] ?></strong></p>
                <p><?= htmlspecialchars((string)$row['start_dt'], ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars((string)$row['end_dt'], ENT_QUOTES, 'UTF-8') ?></p>
                <p>Status: <?= htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= nl2br(htmlspecialchars((string)$row['reason'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php if (schedule_is_manager() && $row['status'] === 'pending'): ?>
                    <form class="api-form" data-success="Request approved." method="post" action="/api.php">
                        <input type="hidden" name="action" value="review_time_off">
                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="decision" value="approved">
                        <label>Manager note <input type="text" name="review_note"></label>
                        <button class="button" type="submit">Approve</button>
                    </form>
                    <form class="api-form" data-success="Request denied." method="post" action="/api.php">
                        <input type="hidden" name="action" value="review_time_off">
                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="decision" value="denied">
                        <label>Manager note <input type="text" name="review_note"></label>
                        <button class="button" type="submit">Deny</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
schedule_page_end();
