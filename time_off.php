<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

schedule_page_start('Time Off', 'time_off');
?>
<section>
    <h2>Time-Off Requests</h2>
    <p>Submit and review time-off requests here (skeleton only).</p>
</section>
<?php
schedule_page_end();
