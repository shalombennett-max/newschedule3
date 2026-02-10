<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

schedule_page_start('Availability', 'availability');
?>
<section>
    <h2>Availability</h2>
    <p>Set your weekly availability preferences here (skeleton only).</p>
</section>
<?php
schedule_page_end();