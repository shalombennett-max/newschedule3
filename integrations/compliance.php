<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

schedule_require_auth();
if (!schedule_is_manager()) { http_response_code(403); echo 'Forbidden'; exit; }
$resId = schedule_restaurant_id();
if ($resId === null) { header('Location: /login.php'); exit; }

$week = schedule_week_window($_GET['week_start'] ?? null);
$filter = (string)($_GET['severity'] ?? 'all');
$params = [':restaurant_id' => $resId, ':week_start_date' => $week['start']];
$where = '';
if ($filter === 'block' || $filter === 'warn') {
    $where = ' AND severity=:severity';
    $params[':severity'] = $filter;
}
$rows = schedule_fetch_all('SELECT v.*, s.start_dt, s.end_dt FROM schedule_violations v LEFT JOIN shifts s ON s.restaurant_id=v.restaurant_id AND s.id=v.shift_id WHERE v.restaurant_id=:restaurant_id AND v.week_start_date=:week_start_date' . $where . ' ORDER BY COALESCE(s.start_dt, v.created_at) ASC', $params);

$blockers = 0; $warnings = 0; $policyCount = [];
foreach ($rows as $r) {
    $sev = (string)($r['severity'] ?? 'warn');
    if ($sev === 'block') { $blockers++; } else { $warnings++; }
    $pk = (string)($r['policy_key'] ?? 'unknown');
    $policyCount[$pk] = ($policyCount[$pk] ?? 0) + 1;
}
arsort($policyCount);
$topPolicy = array_key_first($policyCount) ?: 'None';

schedule_page_start('Compliance Dashboard', 'compliance');
?>
<section>
  <h2>Compliance for Week</h2>
  <div class="week-controls">
    <a class="button" href="/schedule/compliance.php?week_start=<?= htmlspecialchars($week['prev'], ENT_QUOTES, 'UTF-8') ?>&severity=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">&larr; Prev Week</a>
    <strong><?= htmlspecialchars($week['label'], ENT_QUOTES, 'UTF-8') ?></strong>
    <a class="button" href="/schedule/compliance.php?week_start=<?= htmlspecialchars($week['next'], ENT_QUOTES, 'UTF-8') ?>&severity=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">Next Week &rarr;</a>
  </div>

  <div class="card"><strong>Blockers:</strong> <?= $blockers ?> • <strong>Warnings:</strong> <?= $warnings ?> • <strong>Top policy:</strong> <?= htmlspecialchars((string)$topPolicy, ENT_QUOTES, 'UTF-8') ?></div>

  <article class="card">
    <a class="button" href="../schedule/?week_start=<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>&severity=all">All</a>
    <a class="button" href="../schedule/?week_start=<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>&severity=block">Only Blockers</a>
    <a class="button" href="../schedule/?week_start=<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>&severity=warn">Only Warnings</a>
  </article>

  <article class="card">
    <h3>Violations</h3>
    <?php if ($rows === []): ?><p class="empty-state">No violations stored for this week.</p><?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <div class="card">
        <p><strong><?= htmlspecialchars((string)$row['severity'], ENT_QUOTES, 'UTF-8') ?></strong> • <?= htmlspecialchars((string)$row['policy_key'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= htmlspecialchars((string)$row['message'], ENT_QUOTES, 'UTF-8') ?></p>
        <p>Staff #<?= (int)($row['staff_id'] ?? 0) ?> <?php if (!empty($row['shift_id'])): ?>• <a href="/index.php?week_start=<?= htmlspecialchars($week['start'], ENT_QUOTES, 'UTF-8') ?>#shift-<?= (int)$row['shift_id'] ?>">Shift #<?= (int)$row['shift_id'] ?></a><?php endif; ?></p>
      </div>
    <?php endforeach; ?>
  </article>
</section>
<?php schedule_page_end();