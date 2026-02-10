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

$week = schedule_week_window($_GET['week_start'] ?? null);
$start = $week['start'] . ' 00:00:00';
$end = (new DateTimeImmutable($week['start']))->modify('+7 days')->format('Y-m-d') . ' 00:00:00';
$rows = schedule_fetch_all('SELECT s.id,s.start_dt,s.end_dt,s.break_minutes,s.notes,r.name AS role_name␊
    FROM shifts s
    LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
    WHERE s.restaurant_id=:restaurant_id AND s.staff_id=:staff_id AND s.status="published"
      AND s.start_dt >= :start_dt AND s.start_dt < :end_dt
    ORDER BY s.start_dt ASC', [
        ':restaurant_id'=>$resId, ':staff_id'=>$staffId, ':start_dt'=>$start, ':end_dt'=>$end,
    ]);

$openShiftRows = schedule_fetch_all(
    'SELECT s.id,s.start_dt,s.end_dt,s.break_minutes,s.notes,s.status,r.name AS role_name,
            pr.status AS request_status
     FROM shifts s
     LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
     LEFT JOIN shift_pickup_requests pr ON pr.restaurant_id=s.restaurant_id AND pr.shift_id=s.id AND pr.staff_id=:staff_id
     WHERE s.restaurant_id=:restaurant_id AND s.staff_id IS NULL AND s.status IN ("draft", "published")
       AND s.start_dt >= :open_start AND s.start_dt < :open_end
     ORDER BY s.start_dt ASC',
    [
        ':restaurant_id' => $resId,
        ':staff_id' => $staffId,
        ':open_start' => (new DateTimeImmutable('today'))->format('Y-m-d') . ' 00:00:00',
        ':open_end' => (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d') . ' 00:00:00',
    ]
);

schedule_page_start('My Schedule', 'my');
?>
<section>
    <h2>Published Shifts</h2>
    <div class="week-controls">
        <a class="button" href="/my.php?week_start=<?= htmlspecialchars($week['prev'], ENT_QUOTES, 'UTF-8') ?>">&larr; Prev Week</a>
        <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong>
        <a class="button" href="/my.php?week_start=<?= htmlspecialchars($week['next'], ENT_QUOTES, 'UTF-8') ?>">Next Week &rarr;</a>
    </div>

    <?php if ($rows === []): ?>
        <p>No published shifts assigned to you in this week.</p>
    <?php else: ?>
        <?php foreach ($rows as $shift): ?>
            <article class="card">
                <h3><?= htmlspecialchars((new DateTimeImmutable((string)$shift['start_dt']))->format('D, M j'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars(substr((string)$shift['start_dt'], 11, 5) . ' - ' . substr((string)$shift['end_dt'], 11, 5), ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= htmlspecialchars((string)($shift['role_name'] ?: 'No role'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($shift['notes'])): ?><p><?= htmlspecialchars((string)$shift['notes'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section>
    <h2>Open Shifts</h2>
    <?php if ($openShiftRows === []): ?>
        <p>No open shifts in the next 14 days.</p>
    <?php else: ?>
        <?php foreach ($openShiftRows as $shift): ?>
            <article class="card">
                <h3><?= htmlspecialchars((new DateTimeImmutable((string)$shift['start_dt']))->format('D, M j'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars(substr((string)$shift['start_dt'], 11, 5) . ' - ' . substr((string)$shift['end_dt'], 11, 5), ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= htmlspecialchars((string)($shift['role_name'] ?: 'No role'), ENT_QUOTES, 'UTF-8') ?> • Open shift</p>
                <?php if (!empty($shift['notes'])): ?><p><?= htmlspecialchars((string)$shift['notes'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php $requestStatus = (string)($shift['request_status'] ?? ''); ?>
                <?php if ($requestStatus === 'pending'): ?>
                    <p><strong>Pickup request pending</strong></p>
                <?php elseif ($requestStatus === 'approved'): ?>
                    <p><strong>Pickup approved</strong></p>
                <?php elseif ($requestStatus === 'denied'): ?>
                    <p><strong>Pickup denied</strong></p>
                <?php else: ?>
                    <form class="api-form" data-success="Pickup request submitted." method="post" action="/api.php">
                        <input type="hidden" name="action" value="request_pickup">
                        <input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>">
                        <input type="hidden" name="staff_id" value="<?= (int)$staffId ?>">
                        <button class="button" type="submit">Request Pickup</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
schedule_page_end();