<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$resId = schedule_restaurant_id();
$userId = schedule_user_id();
if ($resId === null || $userId === null) {
    header('Location: /login.php');
    exit;
}

$rows = schedule_fetch_all(
    'SELECT id,type,title,body,link_url,is_read,created_at
     FROM notifications
     WHERE restaurant_id=:restaurant_id AND user_id=:user_id
     ORDER BY created_at DESC
     LIMIT 200',
    [':restaurant_id' => $resId, ':user_id' => $userId]
);
$unreadCount = 0;
foreach ($rows as $row) {
    if ((int)$row['is_read'] === 0) {
        $unreadCount++;
    }
}

schedule_page_start('Notifications', 'notifications');
?>
<section>
    <h2>Notifications <span class="badge-chip"><?= (int)$unreadCount ?></span></h2>
    <form class="api-form" data-success="All notifications marked read." method="post" action="/api.php">
        <input type="hidden" name="action" value="mark_all_notifications_read">
        <button class="button" type="submit">Mark all read</button>
    </form>

    <?php if ($rows === []): ?>
        <p class="empty-state">You're all caught up. New alerts will appear here.</p>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <article class="card <?= (int)$row['is_read'] === 0 ? 'unread-card' : '' ?>">
                <h3><?= htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= nl2br(htmlspecialchars((string)$row['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><small><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></small></p>
                <?php if ((int)$row['is_read'] === 0): ?>
                    <form class="api-form" data-success="Notification marked read." method="post" action="/api.php">
                        <input type="hidden" name="action" value="mark_notification_read">
                        <input type="hidden" name="notification_id" value="<?= (int)$row['id'] ?>">
                        <button class="button" type="submit">Mark read</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($row['link_url'])): ?>
                    <a class="button" href="<?= htmlspecialchars((string)$row['link_url'], ENT_QUOTES, 'UTF-8') ?>">Open</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
schedule_page_end();