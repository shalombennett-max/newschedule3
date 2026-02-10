# Scheduling Execution Plan

## 1) Repo Scan Findings (Step 1 Output)

### Auth/session conventions (current evidence)
- No PHP application files are present in this repository snapshot, so there are no directly verifiable `$_SESSION` helper patterns or `db.php` includes yet.
- Schema indicates identity and access are modeled with:
  - `users` (`id`, `email`, `password_hash`, `is_active`, etc.)
  - `user_restaurants` (`restaurant_id`, `user_id`, `role`, `is_active`) with `role` enum `manager|team`.
- Scoping convention across scheduling tables is consistently `restaurant_id`.
- User linkage appears as `user_id` in membership/notification/auth-related tables and `created_by` / `reviewed_by` in workflow tables.

### DB connection conventions (current evidence)
- No runtime PHP files (including `db.php`) are present to confirm actual PDO wrapper style.
- Project docs explicitly reference using an existing `db.php` and PDO prepared statements later in implementation; this plan assumes that convention when feature code begins.

### Existing staff table(s) and fields
- There is no `staff_members` table in the SQL dump.
- Staff-related references currently use integer `staff_id` fields in scheduling tables.
- Existing staffing-related tables include:
  - `staff_availability`
  - `staff_labor_profile`
  - `staff_pay_rates`
  - `staff_skills`
- Additional legacy/adjacent table present: `availability` (`user_id`-based availability).

### Existing UI includes (`header.php`, `nav.php`)
- No `header.php` / `nav.php` files are present in this repository snapshot.
- Include strategy will be determined when the actual PHP app tree is available; for now we plan schedule-local partials.

### Planner/tasks/incidents/audits hook points
- Confirmed schedule/workflow tables we can hook into later:
  - `job_queue`, `job_logs`, `job_locks`
  - `notifications`
  - `schedule_quality`, `schedule_violations`, `schedule_enforcement_events`
  - `callouts`
- Docs state optional publish-time integration to either `planner_tasks` or `tasks` if present, but those tables are not present in current SQL dump.
- No explicit `incidents` or `audits` tables are present in current dump; integration will require adapter-style detection and graceful no-op fallback.

---

## 2) Planned files to create under `/schedule`

> This is the planned implementation set for the next coding phase (not created in this step).

- `/schedule/index.php` — schedule dashboard/entry
- `/schedule/weekly.php` — weekly builder grid
- `/schedule/roles.php` — role management
- `/schedule/availability.php` — availability management
- `/schedule/timeoff.php` — time-off workflow
- `/schedule/marketplace.php` — open shifts + pickup/trade workflows
- `/schedule/quality.php` — quality scoring view + reasons
- `/schedule/announcements.php` — team announcements
- `/schedule/api.php` — JSON endpoints for schedule actions
- `/schedule/lib/`
  - `authz.php` — permission/session helpers aligned to app conventions
  - `csrf.php` — CSRF helpers
  - `schedule_repo.php` — PDO data access layer
  - `quality_service.php` — scoring logic
  - `planner_adapter.php` — planner/task hook abstraction
  - `pos_adapter.php` — POS abstraction entrypoint
- `/schedule/partials/`
  - `schedule_nav.php`
  - `schedule_header.php`
  - `flash.php`

---

## 3) Planned migrations under `/migrations`

> Current dump already contains most core scheduling tables. Migrations below are for additive hardening/integration only, after confirming production state.

- `migrations/038_shift_trade_requests.sql`
  - Add/normalize indexes and FK-like constraints for `shift_trade_requests`.
- `migrations/039_schedule_publish_audit.sql`
  - Add lightweight publish audit table if no central audit table exists.
- `migrations/040_planner_task_bridge.sql`
  - Add bridge metadata table for schedule→planner linkage when external planner table schema is unknown.
- `migrations/041_schedule_notifications_hardening.sql`
  - Add/adjust notification indexes for restaurant/user/read-state queries.
- `migrations/042_pos_sync_cursor.sql`
  - Add sync cursor/checkpoint table for POS incremental imports.

If schema drift is detected, migrations will be adjusted to idempotent `IF NOT EXISTS` style and narrowed to missing objects only.

---

## 4) Integration vs Stub Plan (POS adapters)

### Integrate in early phase
- Use existing POS foundation tables directly:
  - `pos_connections`
  - `pos_mappings`
  - `aloha_import_batches`
  - `aloha_employees_stage`
  - `aloha_labor_punches_stage`
  - `aloha_sales_daily_stage`
- Implement adapter interface and read-only sync status views using actual staged data.

### Stub initially
- Write-time POS push/enforcement actions (e.g., immediate punch blocking) as no-op service methods with structured logs.
- Auto-remediation actions from POS anomalies (early punch alerts -> automatic schedule edits) will remain disabled behind feature flag until validation is complete.

---

## 5) Acceptance Criteria Checklist

- [ ] All schedule pages enforce authenticated user and restaurant scoping.
- [ ] Manager/team capability checks are applied consistently (at least `manager|team` semantics from membership role).
- [ ] Weekly schedule CRUD works using existing `shifts` + `roles` tables.
- [ ] Availability and time-off workflows use `staff_availability` and `time_off_requests` safely.
- [ ] Shift marketplace supports pickup/trade request lifecycle using existing request tables.
- [ ] Quality scoring reads/writes `schedule_quality` and surfaces reasons.
- [ ] Announcements and notifications are scoped by `restaurant_id`.
- [ ] Planner/task integration is optional and safely skipped when `planner_tasks`/`tasks` are absent.
- [ ] POS adapter reads existing Aloha staging tables and reports sync status.
- [ ] All mutating endpoints are CSRF-protected.
- [ ] All SQL access uses parameterized PDO queries.

---

## 6) Verification Plan

### Static / lint checks
- `php -l schedule/index.php`
- `php -l schedule/weekly.php`
- `php -l schedule/roles.php`
- `php -l schedule/availability.php`
- `php -l schedule/timeoff.php`
- `php -l schedule/marketplace.php`
- `php -l schedule/quality.php`
- `php -l schedule/announcements.php`
- `php -l schedule/api.php`
- `php -l schedule/lib/*.php`

### Data validation checks
- Smoke SQL checks on target DB:
  - Verify table existence for all required schedule tables.
  - Verify `restaurant_id` filters on every read/write query path.
  - Verify planned optional hooks (`planner_tasks`/`tasks`) detect-and-skip behavior.

### Manual QA flow
1. Log in as manager and open schedule dashboard.
2. Create/edit/publish week shifts; verify persistence and restaurant scoping.
3. Submit availability and time-off as team user; approve/deny as manager.
4. Open a shift; submit pickup/trade requests; approve one path; verify status transitions.
5. Post announcement; verify team visibility and notification row creation.
6. Run quality scoring; verify score + reasons stored and rendered.
7. Validate POS sync status page against staging data (or empty-state handling).
8. Trigger publish with/without planner table presence and verify graceful behavior in both cases.
