# Scheduling Deployment Checklist (Shared Hosting)

## 1) PHP version
- **Recommended:** PHP 7.4+ (8.x preferred).
- Ensure PDO + MySQL extensions are enabled.

## 2) Database migrations
Run the schedule migrations in order. In this repo the SQL files live in:

- `/docs/migrations/` (core schedule tables)
- `/migrations/031_schedule_settings.sql` (schedule settings)

### phpMyAdmin steps (Namecheap/cPanel)
1. Open **phpMyAdmin** for the database.
2. Execute each SQL file in order via the **SQL** tab.
3. Confirm new tables exist: `roles`, `shifts`, `schedule_policy_sets`, `schedule_settings`, etc.

## 3) Cron worker
Create a cron job that runs every 5 minutes:

```bash
php /path/to/schedule/jobs/worker.php
```

Optional manual run-once command (for troubleshooting):

```bash
php /path/to/schedule/jobs/run_once.php
```

> The worker updates `schedule_settings.last_worker_run_at` after a successful run.

## 4) Storage hardening (CSV imports)
If you store Aloha CSV imports under `/storage/imports`, ensure this folder is **not** web-accessible.

### Apache (.htaccess)
Create `/storage/imports/.htaccess`:

```
Deny from all
```

### Nginx (example)
Add a location block to deny access:

```
location ^~ /storage/imports/ {
  deny all;
  return 403;
}
```

## 5) Smoke checks
- Log in as a manager and open `/schedule/setup.php`.
- Confirm timezone, roles, policy set, and manager permissions.
- Run the demo seed if desired (`/schedule/setup.php` → Demo mode).
- Open `/schedule/compare.php` to review proof links and activity counts.
- Confirm cron runs and system status reflects the last run time.
