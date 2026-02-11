<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$resId = schedule_restaurant_id();
if ($resId === null) {
    header('Location: /login.php');
    exit;
}

$now = date('Y-m-d H:i:s');
$whereAudience = schedule_is_manager() ? '' : ' AND (audience IN ("all","staff") OR audience LIKE "role:%")';
$rows = schedule_fetch_all(
    'SELECT id,title,body,audience,starts_at,ends_at,created_by,created_at
     FROM announcements
     WHERE restaurant_id=:restaurant_id
       AND (starts_at IS NULL OR starts_at <= :now)
       AND (ends_at IS NULL OR ends_at >= :now)' . $whereAudience . '
     ORDER BY created_at DESC',
    [':restaurant_id' => $resId, ':now' => $now]
);

schedule_page_start('Announcements', 'announcements');
?>
<section>
    <h2>Broadcasts</h2>
    <?php if (schedule_is_manager()): ?>
        <article class="card">
            <h3>Create Announcement</h3>
            <form class="api-form" data-success="Announcement posted." method="post" action="/api.php">
                <input type="hidden" name="action" value="create_announcement">
                <label>Title <input type="text" name="title" required maxlength="200"></label>
                <label>Body <textarea name="body" required></textarea></label>
                <label>Audience
                    <select name="audience">
                        <option value="all">All</option>
                        <option value="managers">Managers</option>
                        <option value="staff">Staff</option>
                        <option value="role:staff">Role: Staff</option>
                    </select>
                </label>
                <label>Start <input type="datetime-local" name="starts_at"></label>
                <label>End <input type="datetime-local" name="ends_at"></label>
                <button class="button" type="submit">Post</button>
            </form>
        </article>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="empty-state">No active announcements right now.</p>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <article class="card">
                <h3><?= htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= nl2br(htmlspecialchars((string)$row['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><small>Audience: <?= htmlspecialchars((string)$row['audience'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></small></p>
                <?php if (schedule_is_manager()): ?>
                    <form class="api-form" data-confirm="Delete this announcement?" data-success="Announcement deleted." method="post" action="/api.php">
                        <input type="hidden" name="action" value="delete_announcement">
                        <input type="hidden" name="announcement_id" value="<?= (int)$row['id'] ?>">
                        <button class="button" type="submit">Delete</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
schedule_page_end();
