<?php␊
declare(strict_types=1);␊
␊
require_once __DIR__ . '/../_common.php';␊
␊
schedule_require_auth();␊
if (!schedule_can_manage_integrations()) {
    http_response_code(403);␊
    echo 'Forbidden';␊
    exit;␊
}␊
␊
$resId = schedule_restaurant_id();␊
if ($resId === null) {␊
    header('Location: /login.php');␊
    exit;␊
}␊
␊
$status = trim((string)($_GET['status'] ?? ''));␊
$jobType = trim((string)($_GET['job_type'] ?? ''));␊
$params = [':restaurant_id' => $resId];␊
$where = ['(restaurant_id=:restaurant_id OR restaurant_id IS NULL)'];␊
if ($status !== '') {␊
    $where[] = 'status=:status';␊
    $params[':status'] = $status;␊
}␊
if ($jobType !== '') {␊
    $where[] = 'job_type=:job_type';␊
    $params[':job_type'] = $jobType;␊
}␊
␊
$sql = 'SELECT id, job_type, status, attempts, max_attempts, run_after, created_at, started_at, finished_at, last_error␊
        FROM job_queue WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 50';␊
$jobs = schedule_fetch_all($sql, $params);␊
$types = schedule_fetch_all('SELECT DISTINCT job_type FROM job_queue WHERE restaurant_id=:restaurant_id ORDER BY job_type ASC', [':restaurant_id' => $resId]);␊
␊
schedule_page_start('Jobs', 'integrations');␊
?>␊
<section>␊
    <article class="card">␊
        <h2>Job Queue</h2>␊
        <form method="get" action="/integrations/jobs.php">␊
            <label>Status␊
                <select name="status">␊
                    <option value="">All</option>␊
                    <?php foreach (['queued', 'running', 'succeeded', 'failed', 'cancelled'] as $opt): ?>␊
                        <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>␊
                    <?php endforeach; ?>␊
                </select>␊
            </label>␊
            <label>Type␊
                <select name="job_type">␊
                    <option value="">All</option>␊
                    <?php foreach ($types as $type): $val=(string)$type['job_type']; ?>␊
                        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $jobType === $val ? 'selected' : '' ?>><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></option>␊
                    <?php endforeach; ?>␊
                </select>␊
            </label>␊
            <button class="button" type="submit">Filter</button>␊
        </form>␊
        <form class="api-form" method="post" action="/jobs/run_once.php" data-success="Worker run complete.">␊
            <button class="button" type="submit">Run Now</button>␊
        </form>␊
    </article>␊
␊
    <article class="card">␊
        <h2>Recent Jobs</h2>␊
        <?php if ($jobs === []): ?>␊
            <p>No jobs found.</p>␊
        <?php else: ?>␊
            <?php foreach ($jobs as $job): ?>␊
                <div class="card">␊
                    <p><strong>#<?= (int)$job['id'] ?> <?= htmlspecialchars((string)$job['job_type'], ENT_QUOTES, 'UTF-8') ?></strong></p>␊
                    <p>Status: <?= htmlspecialchars((string)$job['status'], ENT_QUOTES, 'UTF-8') ?> | Attempts: <?= (int)$job['attempts'] ?>/<?= (int)$job['max_attempts'] ?></p>␊
                    <p>Created: <?= htmlspecialchars((string)$job['created_at'], ENT_QUOTES, 'UTF-8') ?> | Run After: <?= htmlspecialchars((string)$job['run_after'], ENT_QUOTES, 'UTF-8') ?></p>␊
                    <?php if ((string)$job['last_error'] !== ''): ?>␊
                        <p>Last Error: <?= htmlspecialchars((string)$job['last_error'], ENT_QUOTES, 'UTF-8') ?></p>␊
                    <?php endif; ?>␊
                    <?php if ((string)$job['status'] === 'failed'): ?>␊
                        <form class="api-form" method="post" action="/integrations/api.php" data-success="Job queued for retry.">␊
                            <input type="hidden" name="action" value="job_retry">␊
                            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">␊
                            <button class="button" type="submit">Retry</button>␊
                        </form>␊
                    <?php endif; ?>␊
                    <?php if ((string)$job['status'] === 'queued'): ?>␊
                        <form class="api-form" method="post" action="/integrations/api.php" data-success="Job cancelled.">␊
                            <input type="hidden" name="action" value="job_cancel">␊
                            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">␊
                            <button class="button" type="submit">Cancel</button>␊
                        </form>␊
                    <?php endif; ?>␊
                </div>␊
            <?php endforeach; ?>␊
        <?php endif; ?>␊
    </article>␊
</section>␊
<?php schedule_page_end();