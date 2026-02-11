<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

schedule_require_auth();
if (!schedule_is_manager()) {
    http_response_code(403);
    die('Forbidden');
}

$resId = schedule_restaurant_id();
if ($resId === null) {
    http_response_code(401);
    die('Unauthorized');
}

$rangeStart = (new DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d H:i:s');
$stats = [
    'swaps' => schedule_table_exists('shift_trade_requests')
        ? (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM shift_trade_requests WHERE restaurant_id=:restaurant_id AND created_at >= :start_at', [':restaurant_id' => $resId, ':start_at' => $rangeStart])['c'] ?? 0)
        : 0,
    'callouts' => schedule_table_exists('callouts')
        ? (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM callouts WHERE restaurant_id=:restaurant_id AND created_at >= :start_at', [':restaurant_id' => $resId, ':start_at' => $rangeStart])['c'] ?? 0)
        : 0,
    'pickup_approvals' => schedule_table_exists('shift_pickup_requests')
        ? (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM shift_pickup_requests WHERE restaurant_id=:restaurant_id AND status IN ("approved","accepted") AND created_at >= :start_at', [':restaurant_id' => $resId, ':start_at' => $rangeStart])['c'] ?? 0)
        : 0,
    'violations' => schedule_table_exists('schedule_violations')
        ? (int)(schedule_fetch_one('SELECT COUNT(*) AS c FROM schedule_violations WHERE restaurant_id=:restaurant_id AND created_at >= :start_at', [':restaurant_id' => $resId, ':start_at' => $rangeStart])['c'] ?? 0)
        : 0,
];

$settings = schedule_get_settings($resId);

$matrix = [
    ['Feature', 'HospiEdge Scheduler', 'Typical Scheduling Tools'],
    ['Templates and reusable role blocks', 'Yes', 'Often'],
    ['Shift swaps and approvals', 'Yes', 'Often'],
    ['Time-off workflow with statuses', 'Yes', 'Often'],
    ['Team messaging + announcements', 'Yes', 'Sometimes'],
    ['Notifications with unread tracking', 'Yes', 'Sometimes'],
    ['Labor actuals vs scheduled view', 'Yes', 'Sometimes'],
    ['Quality score tied to compliance + audits', 'Yes', 'Differentiator'],
    ['Incident/temp triggers to staffing actions', 'Yes', 'Differentiator'],
    ['Audit-ready compliance evidence pack', 'Yes', 'Differentiator'],
    ['Punch exception awareness in scheduling loop', 'Yes', 'Rarely'],
];

schedule_page_start('Scheduler Comparison & Proof', 'compare');
?>
<section>
    <article class="card">
        <h2>Section A: Core Scheduling Parity</h2>
        <ul>
            <li>Role templates and weekly publishing.</li>
            <li>Swap and pickup workflows with approvals.</li>
            <li>Time-off requests and manager review paths.</li>
            <li>Announcements and in-app notifications.</li>
            <li>Labor actuals visibility for manager decisions.</li>
        </ul>
    </article>

    <article class="card">
        <h2>Section B: Better Than Typical Schedulers</h2>
        <ul>
            <li>Schedule quality scoring connected to compliance and staffing rules.</li>
            <li>Operational trigger support (incident/temperature) that can feed staffing actions.</li>
            <li>Compliance dashboard and evidence-friendly audit trail.</li>
            <li>Punch/labor exception signal visibility in scheduler workflows.</li>
        </ul>
    </article>

    <article class="card">
        <h2>Section C: Proof Links</h2>
        <p>
            <a class="button" href="/compliance.php">Compliance</a>
            <a class="button" href="/rules.php">Quality & Rules</a>
            <a class="button" href="/labor_actuals.php">Labor Actuals</a>
            <a class="button" href="/notifications.php">Notifications</a>
        </p>
        <ul>
            <li>Last 7 days swaps: <?= $stats['swaps'] ?></li>
            <li>Last 7 days callouts: <?= $stats['callouts'] ?></li>
            <li>Last 7 days pickup approvals: <?= $stats['pickup_approvals'] ?></li>
            <li>Last 7 days violations: <?= $stats['violations'] ?></li>
        </ul>
    </article>

    <article class="card">
        <h2>Conservative Comparison Matrix</h2>
        <table style="width:100%; border-collapse: collapse;">
            <?php foreach ($matrix as $i => $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                        <<?= $i === 0 ? 'th' : 'td' ?> style="border:1px solid #e5e7eb; padding:.45rem; text-align:left;"><?= htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') ?></<?= $i === 0 ? 'th' : 'td' ?>>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </article>

    <article class="card">
        <h2>System Status</h2>
        <ul>
            <li>Cron worker last run: <?= htmlspecialchars((string)($settings['last_worker_run_at'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></li>
            <li>Aloha CSV mode: <?= (int)$settings['aloha_csv_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></li>
            <li>Demo mode: <?= (int)$settings['demo_mode'] === 1 ? 'Enabled' : 'Disabled' ?></li>
        </ul>
    </article>
</section>
<?php
schedule_page_end();