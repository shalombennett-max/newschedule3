<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

$week = schedule_week_window($_GET['week_start'] ?? null);

schedule_page_start('My Schedule', 'my');
?>
<section>
    <h2>My Schedule</h2>
    <p>Week: <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p>Your assigned shifts will appear here in a future update.</p>
</section>
<?php
schedule_page_end()