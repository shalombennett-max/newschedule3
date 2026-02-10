<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

schedule_require_auth();
if (!schedule_is_manager()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$resId = schedule_restaurant_id();
if ($resId === null) {
    header('Location: /login.php');
    exit;
}

$week = schedule_week_window($_GET['week_start'] ?? null);
$startDt = $week['start'] . ' 00:00:00';
$endDt = (new DateTimeImmutable($week['start']))->modify('+7 days')->format('Y-m-d') . ' 00:00:00';

$staffRows = schedule_fetch_all(
    'SELECT ur.user_id AS id, u.name FROM user_restaurants ur
     INNER JOIN users u ON u.id=ur.user_id
     WHERE ur.restaurant_id=:restaurant_id AND ur.is_active=1',
    [':restaurant_id' => $resId]
);
$staffNames = [];
foreach ($staffRows as $row) {
    $staffNames[(int)$row['id']] = (string)$row['name'];
}

$scheduledRows = schedule_fetch_all(
    'SELECT staff_id, start_dt, end_dt, break_minutes
     FROM shifts
     WHERE restaurant_id=:restaurant_id AND staff_id IS NOT NULL AND status IN ("draft", "published")
       AND start_dt >= :start_dt AND start_dt < :end_dt',
    [':restaurant_id' => $resId, ':start_dt' => $startDt, ':end_dt' => $endDt]
);

$scheduledByStaff = [];
$scheduledByDay = [];
foreach ($scheduledRows as $row) {
    $hours = schedule_hours_between((string)$row['start_dt'], (string)$row['end_dt'], (int)($row['break_minutes'] ?? 0));
    $staffId = (int)$row['staff_id'];
    $day = substr((string)$row['start_dt'], 0, 10);
    $scheduledByStaff[$staffId] = ($scheduledByStaff[$staffId] ?? 0.0) + $hours;
    $scheduledByDay[$day] = ($scheduledByDay[$day] ?? 0.0) + $hours;
}

$actualRows = schedule_fetch_all(
    'SELECT pm.internal_id, l.punch_in_dt, l.punch_out_dt
     FROM aloha_labor_punches_stage l
     INNER JOIN pos_mappings pm ON pm.restaurant_id=l.restaurant_id AND pm.provider="aloha" AND pm.type="employee" AND pm.external_id=l.external_employee_id
     WHERE l.restaurant_id=:restaurant_id AND l.punch_in_dt >= :start_dt AND l.punch_in_dt < :end_dt',
    [':restaurant_id' => $resId, ':start_dt' => $startDt, ':end_dt' => $endDt]
);

$actualByStaff = [];
$actualByDay = [];
$openPunchCount = 0;
foreach ($actualRows as $row) {
    $staffId = (int)$row['internal_id'];
    if ((string)$row['punch_out_dt'] === '') {
        $openPunchCount++;
        continue;
    }
    $hours = schedule_hours_between((string)$row['punch_in_dt'], (string)$row['punch_out_dt'], 0);
    $day = substr((string)$row['punch_in_dt'], 0, 10);
    $actualByStaff[$staffId] = ($actualByStaff[$staffId] ?? 0.0) + $hours;
    $actualByDay[$day] = ($actualByDay[$day] ?? 0.0) + $hours;
}

$mappedEmployeeCount = (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM pos_mappings WHERE restaurant_id=:restaurant_id AND provider="aloha" AND type="employee"', [':restaurant_id' => $resId])['c'] ?? 0);
$laborRowsCount = (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM aloha_labor_punches_stage WHERE restaurant_id=:restaurant_id', [':restaurant_id' => $resId])['c'] ?? 0);

$staffIds = array_values(array_unique(array_merge(array_keys($scheduledByStaff), array_keys($actualByStaff))));
sort($staffIds);

schedule_page_start('Scheduled vs Actual Labor', 'labor_actuals');
?>
<section>
    <h2>Week Comparison</h2>
    <div class="week-controls">
        <a class="button" href="/schedule/labor_actuals.php?week_start=<?= htmlspecialchars($week['prev'], ENT_QUOTES, 'UTF-8') ?>">&larr; Prev Week</a>
        <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong>
        <a class="button" href="/schedule/labor_actuals.php?week_start=<?= htmlspecialchars($week['next'], ENT_QUOTES, 'UTF-8') ?>">Next Week &rarr;</a>
    </div>

    <?php if ($laborRowsCount === 0): ?>
        <article class="card"><p>No Aloha labor data imported yet.</p></article>
    <?php elseif ($mappedEmployeeCount === 0): ?>
        <article class="card"><p>No employee mappings completed.</p></article>
    <?php else: ?>
        <?php if ($openPunchCount > 0): ?><article class="card"><p><?= (int)$openPunchCount ?> open punches were excluded from actual hours.</p></article><?php endif; ?>
        <article class="card">
            <h3>By Staff</h3>
            <table>
                <thead><tr><th>Staff</th><th>Scheduled Hrs</th><th>Actual Hrs</th><th>Variance</th></tr></thead>
                <tbody>
                <?php foreach ($staffIds as $sid): $scheduled = $scheduledByStaff[$sid] ?? 0.0; $actual = $actualByStaff[$sid] ?? 0.0; ?>
                    <tr>
                        <td><?= htmlspecialchars($staffNames[$sid] ?? ('Staff #' . $sid), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format($scheduled, 2) ?></td>
                        <td><?= number_format($actual, 2) ?></td>
                        <td><?= number_format($actual - $scheduled, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <article class="card">
            <h3>Daily Totals</h3>
            <table>
                <thead><tr><th>Date</th><th>Scheduled Hrs</th><th>Actual Hrs</th></tr></thead>
                <tbody>
                <?php for ($i = 0; $i < 7; $i++): $d = (new DateTimeImmutable($week['start']))->modify('+' . $i . ' days')->format('Y-m-d'); ?>
                    <tr>
                        <td><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format($scheduledByDay[$d] ?? 0.0, 2) ?></td>
                        <td><?= number_format($actualByDay[$d] ?? 0.0, 2) ?></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </article>
    <?php endif; ?>
</section>
<?php schedule_page_end();