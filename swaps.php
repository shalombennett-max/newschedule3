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

$params = [':restaurant_id' => $resId];
$sql = 'SELECT tr.*, s.start_dt, s.end_dt, r.name AS role_name
        FROM shift_trade_requests tr
        INNER JOIN shifts s ON s.restaurant_id=tr.restaurant_id AND s.id=tr.shift_id
        LEFT JOIN roles r ON r.restaurant_id=s.restaurant_id AND r.id=s.role_id
        WHERE tr.restaurant_id=:restaurant_id';
if (!schedule_is_manager()) {
    $sql .= ' AND (tr.from_staff_id=:my_staff OR tr.to_staff_id=:my_staff)';
    $params[':my_staff'] = $myStaffId;
}
$sql .= ' ORDER BY tr.created_at DESC';
$rows = schedule_fetch_all($sql, $params);

schedule_page_start('Shift Swaps', 'swaps');
?>
<section>
    <h2>Swap Requests</h2>
    <?php if ($rows === []): ?>
        <p class="empty-state">No swap requests yet.</p>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <article class="card">
                <h3><?= htmlspecialchars((string)$row['role_name'] ?: 'No role', ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string)$row['start_dt'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$row['end_dt'], ENT_QUOTES, 'UTF-8') ?></p>
                <p>From #<?= (int)$row['from_staff_id'] ?> to <?= $row['to_staff_id'] ? ('#' . (int)$row['to_staff_id']) : 'Anyone' ?></p>
                <p>Status: <strong><?= htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php if (!empty($row['notes'])): ?><p><?= nl2br(htmlspecialchars((string)$row['notes'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                <?php if (schedule_is_manager() && (string)$row['status'] === 'pending'): ?>
                    <form class="api-form" data-success="Swap approved." method="post" action="/api.php">
                        <input type="hidden" name="action" value="approve_swap_request">
                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                        <button class="button" type="submit">Approve</button>
                    </form>
                    <form class="api-form" data-success="Swap denied." method="post" action="/api.php">
                        <input type="hidden" name="action" value="deny_swap_request">
                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                        <button class="button" type="submit">Deny</button>
                    </form>
                <?php elseif (!schedule_is_manager() && (int)$row['from_staff_id'] === $myStaffId && (string)$row['status'] === 'pending'): ?>
                    <form class="api-form" data-success="Swap cancelled." method="post" action="/api.php">
                        <input type="hidden" name="action" value="cancel_swap_request">
                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                        <button class="button" type="submit">Cancel</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
schedule_page_end();
