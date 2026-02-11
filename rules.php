<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/rules_engine.php';

schedule_require_auth();
if (!schedule_is_manager()) { http_response_code(403); echo 'Forbidden'; exit; }
$resId = schedule_restaurant_id();
$pdo = schedule_get_pdo();
if ($resId === null || !$pdo instanceof PDO) { http_response_code(500); echo 'Database unavailable'; exit; }

$policySetId = 0;
$policySets = [];
$policies = [];
$tablesReady = se_table_exists($pdo, 'schedule_policy_sets') && se_table_exists($pdo, 'schedule_policies');
if ($tablesReady) {
    $policySetId = se_get_active_policy_set_id($pdo, $resId);
    $policySets = schedule_fetch_all('SELECT id,name,is_active,is_default FROM schedule_policy_sets WHERE restaurant_id=:restaurant_id ORDER BY is_default DESC,name ASC', [':restaurant_id'=>$resId]);
    $policies = se_load_policies($pdo, $resId, $policySetId);
}

schedule_page_start('Rules Engine Settings', 'rules');
?>
<section>
  <h2>Policy Set</h2>
  <?php if (!$tablesReady): ?>
    <article class="card"><p class="empty-state">Run migrations 023 and 024 to enable rules configuration.</p></article>
  <?php else: ?>
  <article class="card">
    <label>Active Policy Set
      <select disabled>
        <?php foreach ($policySets as $set): ?>
          <option value="<?= (int)$set['id'] ?>" <?= (int)$set['id'] === $policySetId ? 'selected' : '' ?>><?= htmlspecialchars((string)$set['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="empty-state">Additional set switching can be layered on later; this screen edits the active set.</p>
  </article>

  <article class="card">
    <h3>Policies</h3>
    <form class="api-form" action="/schedule/api.php" method="post" data-success="Policy settings saved.">
      <input type="hidden" name="action" value="update_policy_set">
      <input type="hidden" name="policy_set_id" value="<?= (int)$policySetId ?>">
      <?php foreach ($policies as $key => $policy): $params = is_array($policy['params']) ? $policy['params'] : []; ?>
        <div class="card">
          <strong><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></strong>
          <label><input type="checkbox" name="policies[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][enabled]" value="1" <?= !empty($policy['enabled']) ? 'checked' : '' ?>> Enabled</label>
          <label>Mode
            <select name="policies[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][mode]">
              <option value="warn" <?= ($policy['mode'] ?? 'warn') === 'warn' ? 'selected' : '' ?>>Warn</option>
              <option value="block" <?= ($policy['mode'] ?? 'warn') === 'block' ? 'selected' : '' ?>>Block</option>
            </select>
          </label>
          <?php foreach ($params as $pkey => $pval): ?>
            <label><?= htmlspecialchars((string)$pkey, ENT_QUOTES, 'UTF-8') ?>
              <input type="text" name="policies[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][params][<?= htmlspecialchars((string)$pkey, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string)$pval, ENT_QUOTES, 'UTF-8') ?>">
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <button class="button" type="submit">Save Policy Set</button>
    </form>

    <form class="api-form" action="/schedule/api.php" method="post" data-success="Defaults restored." data-confirm="Reset this policy set to defaults?">
      <input type="hidden" name="action" value="reset_policy_set_defaults">
      <input type="hidden" name="policy_set_id" value="<?= (int)$policySetId ?>">
      <button class="button" type="submit">Reset to Defaults</button>
    </form>
  </article>
  <?php endif; ?>
</section>
<?php schedule_page_end();