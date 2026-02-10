<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$week = schedule_week_window($_GET['week_start'] ?? null);

schedule_page_start('Schedule', 'index');
?>
<section>
    <h2>Manager Week View</h2>
    <div class="week-controls">
        <a class="button" href="/index.php?week_start=<?= htmlspecialchars($week['prev'], ENT_QUOTES, 'UTF-8') ?>">&larr; Prev Week</a>
        <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong>
        <a class="button" href="/index.php?week_start=<?= htmlspecialchars($week['next'], ENT_QUOTES, 'UTF-8') ?>">Next Week &rarr;</a>
    </div>
    <p>This is the schedule skeleton. Shift grid and editing tools will be added in a later step.</p>
</section>
<?php
schedule_page_end();
