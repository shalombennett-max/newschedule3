<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
schedule_require_auth();

schedule_page_start('Roles', 'roles');
?>
<section>
    <h2>Roles</h2>
    <?php if (!schedule_is_manager()): ?>
        <p>Manager only.</p>
    <?php else: ?>
        <p>Role setup and assignment tools will be added here in a later step.</p>
    <?php endif; ?>
</section>
<?php
schedule_page_end();