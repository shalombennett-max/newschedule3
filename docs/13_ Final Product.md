# Final Product

Generated from all files under `docs/` on 2026-02-10T09:01:08Z.

## Files Included
- `docs/CODEX_PROMPT_SCHEDULING_COMPETITIVE.md`
- `docs/DEMO_MODE.md`
- `docs/DEPLOY_schedule_checklist.md`
- `docs/README_schedule.md`
- `docs/_notes/dwsync.xml`
- `docs/codex_prompts/01_repo_scan_execplan.md`
- `docs/codex_prompts/02_migrations.md`
- `docs/codex_prompts/03_module_skeleton.md`
- `docs/codex_prompts/04_mvp_parity.md`
- `docs/codex_prompts/05_differentiators.md`
- `docs/codex_prompts/06_pos_adapter_stub.md`
- `docs/codex_prompts/07_security_permissions.md.txt`
- `docs/codex_prompts/08_verification.md`
- `docs/codex_prompts/09_Labor Rules Engine_ Compliance_Enforcement Signals.md`
- `docs/codex_prompts/10_Security_Permissions.md`
- `docs/codex_prompts/10_Security_Permissions.txt`
- `docs/codex_prompts/11_Release_Packaging_Setup_Wizard.txt`
- `docs/codex_prompts/12_Release_Packaging_Setup_Wizard.txt`
- `docs/codex_prompts/_notes/dwsync.xml`
- `docs/execplans/001_roles.sql`
- `docs/execplans/_notes/dwsync.xml`
- `docs/execplans/scheduling_execplan.md`
- `docs/migrations/001_roles.sql`
- `docs/migrations/002_staff_availability.sql`
- `docs/migrations/003_shifts.sql`
- `docs/migrations/004_time_off_requests.sql`
- `docs/migrations/005_staff_skills.sql`
- `docs/migrations/006_staff_pay_rates.sql`
- `docs/migrations/008_shift_pickup_requests.sql`
- `docs/migrations/009_announcements.sql`
- `docs/migrations/010_schedule_quality.sql`
- `docs/migrations/011_pos_connections.sql`
- `docs/migrations/012_pos_mappings.sql`
- `docs/migrations/013_aloha_import_batches.sql`
- `docs/migrations/014_aloha_employees_stage.sql`
- `docs/migrations/015_aloha_labor_punches_stage.sql`
- `docs/migrations/016_aloha_sales_daily_stage.sql`
- `docs/migrations/017_job_logs.sql`
- `docs/migrations/018_job_logs.sql`
- `docs/migrations/019_job_logs.sql`
- `docs/migrations/020_job_logs.sql`
- `docs/migrations/021_job_logs.sql`
- `docs/migrations/022_job_logs.sql`
- `docs/migrations/023_job_logs.sql`
- `docs/migrations/024_job_logs.sql`
- `docs/migrations/025_job_logs.sql`
- `docs/migrations/026_job_logs.sql`
- `docs/migrations/027_job_logs.sql`
- `docs/migrations/028_job_logs.sql`
- `docs/migrations/029_job_logs.sql`
- `docs/migrations/030_job_logs.sql`
- `docs/migrations/031_job_logs.sql`
- `docs/migrations/032_job_logs.sql`
- `docs/migrations/033_job_logs.sql`
- `docs/migrations/034_job_logs.sql`
- `docs/migrations/035_job_logs.sql`
- `docs/migrations/036_job_logs.sql`
- `docs/migrations/037_job_logs.sql`
- `docs/migrations/09_codex_review.md.txt`
- `docs/migrations/_notes/dwsync.xml`
- `docs/scheduling_spec_competitive.md`

## docs/CODEX_PROMPT_SCHEDULING_COMPETITIVE.md

```
You are Codex working in this repo.

READ FIRST:
- Follow AGENTS.md strictly (no refactors, no removing features, smallest changes only).
- Implement docs/scheduling_spec.md AND docs/scheduling_spec_competitive.md.

TASK:
Build the Scheduling module to exceed HotSchedules-quality:
- Full parity features (availability, time off, swaps, messaging, forecasting hooks, compliance warnings, publish flow)
- PLUS differentiators:
  - Schedule Quality Score + fix suggestions
  - Shift Marketplace (open shifts + ranked pickup)
  - HospiEdge triggers (incidents/temps/audits -> staffing actions + planner tasks)
  - POS adapter pattern with Aloha-ready mapping + actual labor import (Aloha integration can be stubbed with tables + adapter interface if credentials are not present yet)

DELIVERABLES:
- /schedule pages + /schedule/api.php JSON actions
- /migrations SQL for new tables (including competitive tables)
- /docs/README_schedule.md describing setup and manual testing
- Must not crash with empty DB
- Prepared statements only, restaurant scoping everywhere, CSRF for POST

WORKFLOW:
1) Inspect existing repo conventions (db.php, header/nav, session fields).
2) Create migrations first.
3) Implement scheduling parity features.
4) Implement quality scoring + marketplace.
5) Wire triggers into existing incidents/audits/tasks tables if present; if not, create a minimal integration layer without breaking anything.
6) Summarize changes and how to test.

```

## docs/DEMO_MODE.md

```
# Demo Mode (Scheduling)

## Overview
Demo mode is scoped per restaurant and is stored in `schedule_settings.demo_mode`. When enabled, you can safely generate sample scheduling data for walkthroughs without touching production data for other restaurants.

## What the seed script creates
The demo seed script generates data only when the required tables exist:
- Default roles (if missing)
- Two weeks of sample shifts (with open shifts)
- 1–2 time-off requests (when real staff records exist)
- 1 announcement
- 1 callout and 1 pending pickup request (when the tables exist)
- Demo-only staff stored in `schedule_demo_staff` (for reference)

All demo rows are tagged in `schedule_demo_tags` so they can be safely deleted later.

## CLI usage
Run from the repo root:

```bash
php scripts/seed_schedule_demo.php --restaurant=<restaurant_id>
```

Optional flags:
- `--reset` (delete demo-tagged rows only)
- `--user=<user_id>` (used for `created_by` fields when seeding)

Example reset:

```bash
php scripts/seed_schedule_demo.php --restaurant=<restaurant_id> --reset
```

## Web usage (manager only)
When logged in as a manager, you can seed or reset demo data from:

- `/schedule/setup.php` → **Demo mode** section

The script validates CSRF tokens and only runs for the current restaurant.

## Safety notes
- Demo data is tagged per restaurant.
- Reset deletes only demo-tagged rows; it does **not** delete existing production data.
- If the staff table schema is unknown, demo staff are stored in `schedule_demo_staff` and no inserts are made into core staff tables.

```

## docs/DEPLOY_schedule_checklist.md

```
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

```

## docs/README_schedule.md

```
## Migrations

Run migrations in this order:
1. `migrations/001_roles.sql`
2. `migrations/002_staff_availability.sql`
3. `migrations/003_shifts.sql`
4. `migrations/004_time_off_requests.sql`
5. `migrations/005_staff_skills.sql`
6. `migrations/006_staff_pay_rates.sql`
7. `migrations/007_shift_trade_requests.sql`
8. `migrations/008_shift_pickup_requests.sql`
9. `migrations/009_announcements.sql`
10. `migrations/010_schedule_quality.sql`
11. `migrations/011_pos_connections.sql`
12. `migrations/012_pos_mappings.sql`

### Running in phpMyAdmin
- Open each SQL file in order and use the SQL tab to execute its contents.

### Running via CLI
- If you have a migration runner, add these files to its execution list in the order above.
- Without a runner, you can use the MySQL client and copy/paste each file:
  - `mysql -u <user> -p <database> < migrations/001_roles.sql`

## Empty-table safety
The Scheduling module must not crash when these tables are empty; all features should handle zero rows gracefully.

## Optional planner/task integration
On publish, the schedule module will attempt to create follow-up tasks if a compatible planner/task table exists.
It looks for either a `planner_tasks` or `tasks` table with at minimum `restaurant_id` and `title` (or `name`) columns.
If the table or columns are missing, it safely skips task creation. This integration never creates new tables.
```

## docs/_notes/dwsync.xml

```
<?xml version="1.0" encoding="utf-8" ?>
<dwsync>
<file name="CODEX_PROMPT_SCHEDULING_COMPETITIVE.md" server="66.29.132.156/schedule/" local="134150665171755600" remote="134151781200000000" Dst="1" />
<file name="DEMO_MODE.md" server="66.29.132.156/schedule/" local="134150851185613435" remote="134151781200000000" Dst="1" />
<file name="DEPLOY_schedule_checklist.md" server="66.29.132.156/schedule/" local="134150852820830035" remote="134151781200000000" Dst="1" />
<file name="README_schedule.md" server="66.29.132.156/schedule/" local="134150726781286812" remote="134151781200000000" Dst="1" />
<file name="scheduling_spec_competitive.md" server="66.29.132.156/schedule/" local="134150665171745589" remote="134151781200000000" Dst="1" />
</dwsync>
```

## docs/codex_prompts/01_repo_scan_execplan.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Read docs/scheduling_spec.md and docs/scheduling_spec_competitive.md (if present).

TASK (NO FEATURE CODE YET):
1) Inspect the repo structure and identify:
   - auth/session conventions (user_id, res_id, any role/permission fields)
   - db connection conventions (db.php, PDO patterns)
   - existing staff table(s) and fields (staff_members or similar)
   - existing UI includes (header.php, nav.php)
   - any existing planner/tasks/incidents/audits tables that we can hook into later

2) Create an execution plan file:
   - Create /docs/execplans/scheduling_execplan.md
   - Include:
     a) file list you will create under /schedule
     b) migrations you will add under /migrations
     c) what you will integrate vs stub (POS adapters)
     d) acceptance criteria checklist
     e) how you will verify (php -l, manual test steps)

3) Output:
   - Only add /docs/execplans/scheduling_execplan.md
   - Do NOT modify any PHP files in this step.
Stop after the plan file is created.

```

## docs/codex_prompts/02_migrations.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Follow /docs/execplans/scheduling_execplan.md.

TASK:
Implement SQL migrations under /migrations for the scheduling module:
- roles
- staff_availability
- shifts
- time_off_requests
AND the competitive tables:
- staff_skills
- staff_pay_rates
- shift_trade_requests
- shift_pickup_requests
- announcements
- schedule_quality

REQUIREMENTS:
- Must be scoped by restaurant_id (every table).
- Add indexes for restaurant_id and date range queries.
- Use idempotent patterns where possible (IF NOT EXISTS).
- Do not break existing schema.
- Add a short /docs/README_schedule.md section "Migrations" with run order.

STOP:
When migrations + README section are created.

```

## docs/codex_prompts/03_module_skeleton.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly (no refactors, smallest changes only).
- Read /docs/execplans/scheduling_execplan.md.
- Use the existing DB schema already created (tables include: roles, staff_availability, shifts, time_off_requests, shift_pickup_requests, schedule_quality, announcements, staff_skills, staff_pay_rates, pos_connections, pos_mappings).

TASK (SKELETON ONLY — do not implement full features yet):
Create a new folder /schedule with the minimal pages and a JSON endpoint that load safely.

Create these files:
- /schedule/index.php         (Manager week view skeleton)
- /schedule/my.php            (Staff schedule skeleton)
- /schedule/availability.php  (Availability skeleton)
- /schedule/time_off.php      (Time-off skeleton)
- /schedule/roles.php         (Roles skeleton)
- /schedule/api.php           (JSON POST endpoint skeleton)
- Optional (only if helpful): /schedule/assets/schedule.js and /schedule/assets/schedule.css

REQUIREMENTS (NON-NEGOTIABLE):
1) Auth + restaurant scope:
   - Every page must start session safely.
   - Must require logged-in user_id and res_id (restaurant_id scope).
   - Unauthorized:
     - HTML pages -> redirect to /login.php (preserve next if repo does that)
     - api.php -> JSON { "error": "Unauthorized" } with 401

2) DB:
   - Use existing db.php PDO conventions from this repo.
   - No query should ever run without restaurant_id filter for restaurant-scoped tables.

3) CSRF:
   - If the repo already has csrf_token in session, use it.
   - If not, generate $_SESSION['csrf_token'] once.
   - api.php must require a CSRF token for all POST actions except a harmless "ping".
   - Return JSON { "error": "Bad CSRF" } with 403 when invalid.

4) Must not crash:
   - Pages must load even if tables are empty (0 staff, 0 roles, 0 shifts).
   - Avoid PHP notices/warnings by defensive checks.

5) UI skeleton:
   - Keep mobile-first simple HTML.
   - Provide a small top nav within /schedule pages linking:
     - Schedule (index.php)
     - My Schedule (my.php)
     - Availability (availability.php)
     - Time Off (time_off.php)
     - Roles (roles.php) [only show link if manager-level; otherwise hide]
   - Include a week selector UI on index.php (prev/next week buttons + visible week range label).
   - Do NOT implement drag/drop or complex calendar yet.

6) api.php actions (skeleton stubs only):
   - Accept POST with "action".
   - Implement:
     - action=ping -> { "success": true }
     - action=list_roles -> return roles for restaurant (empty array ok)
     - action=list_shifts -> accept week_start (YYYY-MM-DD) and return shifts in that range (empty ok)
     - action=list_time_off -> return time_off_requests in date range (empty ok)
   - For now, DO NOT implement create/update/delete. Just list endpoints + ping.
   - All list queries must be restaurant-scoped and safe.

7) Manager vs staff:
   - Detect manager capability using existing repo conventions (ex: session role flag, permission table, etc).
   - If no permission system exists, add a minimal local helper that treats everyone as manager FOR NOW,
     but isolate it in one function so we can harden later without refactoring.
   - roles.php should show "Manager only" message if not manager.

VERIFICATION:
- Run php -l on each new PHP file you created.
- Confirm pages render without fatal errors.
- Confirm api.php returns JSON and correct HTTP status for unauthorized/CSRF.

STOP CONDITION:
- Only create new /schedule files (and optional /schedule/assets files).
- Do not modify existing production files in this step unless absolutely required for includes.
- Provide a summary of created files and how to manually open the pages in browser to verify.

```

## docs/codex_prompts/04_mvp_parity.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve the new premium UI styling (schedule.css, any shared UI include like /schedule/_ui.php).
- DO NOT redesign styles in this task.
- Implement functionality using existing tables already created in the new database.

GOAL:
Implement MVP scheduling parity features end-to-end in /schedule module so it competes with HotSchedules-class tools on core workflow.

SCOPE: /schedule only (plus tiny permission helper if absolutely needed).
Do not refactor unrelated files.

FEATURES TO IMPLEMENT (END-TO-END):

1) ROLES CRUD (Manager-only)
- roles.php UI: list roles, add role, edit role (name/color/sort/is_active), deactivate/reactivate.
- /schedule/api.php actions:
  - create_role
  - update_role
  - toggle_role_active
  - delete_role (optional; prefer soft deactivation, not hard delete)
- Constraints:
  - unique (restaurant_id, name) (handle duplicate gracefully)

2) AVAILABILITY (Staff + Manager view)
- availability.php:
  - Staff: edit their own availability for each day 0–6
  - Manager: ability to switch viewing staff (read-only is OK, edit is optional)
- /schedule/api.php:
  - save_availability (upsert per staff_id + day_of_week)
  - list_availability (for viewing)
- Rules:
  - Validate times; end_time > start_time if both present
  - Allow "unavailable" status

3) MANAGER SCHEDULING (Create/Edit/Delete shifts, Week view)
- index.php:
  - Show week view with days grouped (mobile-first list).
  - Show shifts as cards with time range + role + staff (or "Open shift") + status badge.
  - Add shift form (modal or inline):
    - date, start time, end time, role, staff (optional), break minutes, notes
  - Edit shift:
    - allow changing start/end, role, staff, notes, break minutes
  - Delete shift: soft delete (status='deleted') with confirm
  - Publish week: button sets shifts in that week to published (except deleted)
- /schedule/api.php actions:
  - create_shift
  - update_shift
  - delete_shift (soft)
  - publish_week
  - list_shifts (already exists; ensure it returns both draft/published for managers; staff should see published only)

4) STAFF MY SCHEDULE
- my.php:
  - Show only published shifts assigned to the logged-in staff member
  - Upcoming default (today onward) plus optional week selector

5) TIME OFF REQUESTS (Staff create, Manager approve/deny)
- time_off.php:
  - Staff: create request (start_dt, end_dt, reason)
  - Staff: see their requests and statuses
  - Manager: list all requests (filters: pending/approved/denied)
  - Manager: approve/deny with optional note
- /schedule/api.php actions:
  - create_time_off
  - review_time_off (approve/deny)
  - list_time_off (already exists; ensure scope and filters work)

NON-NEGOTIABLE RULES:

A) Security / Scope
- Every POST action requires valid CSRF (except ping).
- Every query must be restaurant_id scoped.
- Do not trust staff_id or shift_id from client; verify it belongs to restaurant_id.
- Staff can only modify:
  - their own availability
  - their own time-off requests (create/cancel if you add cancel)
- Managers can modify schedules, roles, and approve/deny time off.

B) Data validation
- For shifts: end_dt must be after start_dt.
- Normalize inputs: date + start/end times -> datetime.
- block overlaps:
  - If staff_id is set (assigned shift), prevent overlap with another non-deleted shift for same staff within restaurant.
  - Overlap check must exclude the shift being updated.
- block shifts during approved time off:
  - if a time-off request is approved and overlaps the shift interval for that staff member, block creation/update (return readable error).

C) Publishing logic
- publish_week should:
  - accept week_start (YYYY-MM-DD)
  - compute week_end = week_start + 7 days
  - update shifts where start_dt >= week_start 00:00 and < week_end 00:00 and status != 'deleted'
  - set status='published'
- Staff views should only show status='published'.

D) UI/UX (keep premium styling)
- Use existing classes/components from schedule.css.
- Use consistent cards, buttons, and empty states.
- Add user-friendly error/success feedback:
  - Use toast/alert component (existing or minimal).
  - API errors should be displayed clearly without breaking layout.

E) Performance
- Week list queries must be indexed by restaurant_id + start_dt.
- Avoid N+1 queries; fetch shifts for the week in one query and group in PHP.

API RESPONSE FORMAT:
- Success: { "success": true, "data": ... }
- Error:   { "error": "Human readable message" }
- Use HTTP status codes:
  - 401 Unauthorized
  - 403 CSRF or forbidden
  - 422 validation errors
  - 500 unexpected

VERIFICATION (REQUIRED):
1) Run php -l on all edited /schedule/*.php files.
2) Manual test flows:
   - Empty DB: pages load, show empty states, no warnings.
   - Create role -> shows in list.
   - Set availability -> persists.
   - Create draft shift -> appears under correct day.
   - Overlap prevention blocks overlapping assigned shifts.
   - Publish week -> staff can see shifts in my.php.
   - Time-off request -> manager approves -> shift creation overlapping is blocked.
3) Confirm header/safe-area is not covering content (PWA/iPhone).

STOP CONDITION:
- All features above work end-to-end.
- Styling remains premium and consistent (no redesign).
- No unrelated refactors.
- Summarize changes and list manual test steps performed.

```

## docs/codex_prompts/05_differentiators.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- DO NOT change existing styling system (schedule.css / any shared UI include).
- Keep the premium UI and components; only add new UI elements using existing classes.
- Do NOT refactor business logic that already works from MVP parity.
- Use existing DB tables already created (schedule_quality, shift_pickup_requests, staff_skills, staff_pay_rates, announcements, shifts, time_off_requests, staff_availability, roles).

GOAL:
Add the features that make this scheduling module better than HotSchedules-class tools:
1) Schedule Quality Score + reasons + suggestions (manager view)
2) Shift Marketplace (open shifts + ranked pickup + approvals)
3) Smart operational triggers (safe stubs, only if target tables exist)

========================================================
1) SCHEDULE QUALITY SCORE (Manager-only)
========================================================
A) Data
- Use schedule_quality table:
  - restaurant_id
  - week_start_date (DATE)
  - score INT (0–100)
  - reasons_json TEXT (JSON string)
  - generated_at DATETIME
  - generated_by INT

B) Compute score for a selected week (week_start_date):
- Add to /schedule/api.php:
  - action=generate_quality_score (POST, manager-only)
  - action=get_quality_score (POST, manager-only) -> return stored score if exists

C) Scoring (keep it deterministic + explainable; no external AI calls in this step)
Score starts at 100 and subtract points with clear reasons:
- Coverage sanity:
  - If any day has 0 shifts: -15 (reason: uncovered_day)
- Clopen risk:
  - If a staff member has a shift ending after 10pm AND next day starts before 10am: -10 each (cap -25)
- Overtime risk:
  - Calculate staff weekly hours from assigned shifts; if > 40: -10 each (cap -30)
- Availability conflict:
  - If assigned shift overlaps staff_availability status=unavailable: -10 each (cap -30)
- Time-off conflict:
  - If assigned shift overlaps an approved time_off_request: -25 each (cap -50)
- Role coverage (lightweight):
  - If roles exist but week has no shifts with any role_id set: -10
Clamp final score to 0..100.

Create a reasons array like:
[
  { "key":"clopen_risk", "count":2, "impact":-20, "examples":[...], "suggestions":[...] },
  ...
]
Store as JSON string in reasons_json.

D) UI
- Update /schedule/index.php:
  - Add a “Schedule Quality” card at top showing:
    - Score badge (0–100)
    - Top 3 issues with counts
    - Button “Recalculate” -> calls generate_quality_score via AJAX
  - Add a “Suggestions” section:
    - Plain language suggestions like:
      - “Consider moving John’s 7am shift later to avoid clopen.”
      - “Two shifts overlap approved time off—remove assignment or reschedule.”
- Must look premium and consistent using existing CSS components.

========================================================
2) SHIFT MARKETPLACE (Open Shifts + Pickup Requests)
========================================================
A) Concepts
- An “Open shift” is a shift with staff_id NULL and status draft/published.
- Staff can request to pick it up.
- Manager approves to assign staff_id and closes requests.

B) API actions to add in /schedule/api.php:
- action=mark_shift_open (manager-only)
  - sets staff_id=NULL (keep role_id, time, notes)
  - rejects if shift is deleted
- action=request_pickup (staff-only)
  - creates row in shift_pickup_requests (restaurant_id, shift_id, staff_id, status='pending', created_at)
  - prevent duplicates for same shift+staff if pending already
- action=list_open_shifts (staff-only)
  - return open shifts for a date range (default upcoming 14 days)
- action=list_pickup_requests (manager-only)
  - list requests with shift info + staff info
- action=approve_pickup (manager-only)
  - validates:
    - shift belongs to restaurant
    - shift is open (staff_id is NULL)
    - request is pending
    - staff has no overlap with existing non-deleted shifts
    - staff is not on approved time off overlapping the shift
  - then:
    - assign shift.staff_id = request.staff_id
    - set request.status='approved'
    - set other pending requests for that shift -> status='denied'
- action=deny_pickup (manager-only)
  - set request.status='denied'

C) Ranking candidates (Manager view)
- On index.php, when viewing an open shift:
  - Add “Suggested Staff” list (top 3) based on:
    1) availability match (available window overlaps shift) -> +2
    2) skill match: staff_skills.skill_key matches role name normalized -> +2
    3) overtime risk: weekly hours + shift hours <= 40 -> +1, else -2
  - This can be computed server-side in PHP for the open shift display.
  - If data missing, degrade gracefully (still show open shift without suggestions).

D) UI
- Staff:
  - Add a “Open Shifts” section to /schedule/my.php (or a new tab inside it)
  - Each open shift card has “Request Pickup” button and shows status if already requested.
- Manager:
  - On /schedule/index.php, open shifts show:
    - “Open shift” badge
    - “View requests” button (simple modal/list panel)
    - Approve/deny controls
- Keep everything mobile-first and consistent.

========================================================
3) SMART TRIGGERS (SAFE INTEGRATION ONLY)
========================================================
Goal: hook scheduling actions to your broader HospiEdge system without breaking anything.

On publish_week:
- If a planner/tasks table exists in this repo (detect via information_schema or safe try/catch query),
  create a task like:
  - “Review Schedule Quality Issues” if score < 85
  - “Resolve Time-Off Conflicts” if any conflict exists
- If the target table DOES NOT exist:
  - do nothing, and do not create new tables in this prompt.
  - add a small code comment + README note describing what table was expected.

IMPORTANT:
- This step must never break publish_week.
- Any trigger insertion must be wrapped in try/catch and skipped if table missing.

========================================================
NON-NEGOTIABLES
========================================================
Security:
- CSRF required for all POST actions (except ping).
- Restaurant scoping on every query.
- Staff can only request pickup for themselves.
- Managers only approve/deny/mark open/generate quality score.

Robustness:
- Empty DB must not crash.
- If staff_skills or staff_availability has no rows, ranking still works with fallbacks.

Performance:
- Limit open shift queries to a reasonable range (e.g., 14–30 days).
- Avoid N+1 when listing shifts; join staff/roles in one query where practical.

Verification (REQUIRED):
1) php -l all edited PHP files.
2) Manual checks:
   - Quality score generates and persists; refresh shows saved score.
   - Open shift appears for staff; staff requests pickup; manager approves; shift is assigned.
   - Overlap/time-off rules still block bad assignments.
   - publish_week still works even if trigger tables don’t exist.

STOP CONDITION:
- Quality score + reasons + suggestions visible on index.php
- Open shift pickup workflow works end-to-end
- Smart triggers are safe and non-breaking
- Premium styling preserved
- No unrelated refactors

```

## docs/codex_prompts/06_pos_adapter_stub.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve the premium UI system already created (schedule.css and any shared UI include).
- Do NOT refactor existing scheduling logic.
- This task adds Aloha POS integration foundations WITHOUT needing immediate NCR credentials.
- Build for shared hosting reality: no long-running requests; avoid heavy dependencies.

GOAL:
Add an Aloha POS integration that makes scheduling better than competitors by enabling:
- employee import/mapping (Aloha -> HospiEdge staff_members)
- job/role mapping (Aloha job codes -> HospiEdge roles)
- actual labor import (punches/timecards) so we can show Scheduled vs Actual
- optional sales import (daily totals) to compute labor % and demand context later
Do this first via a robust CSV import workflow (works for Aloha TS/on-prem and many exports),
and design the adapter layer so we can later add API/agent sync without rewriting.

SCOPE:
- Create new files under /pos and /integrations (or /schedule if needed).
- Small surgical edits allowed in /schedule/labor_actuals.php (create if missing) and /schedule nav to link to it.
- Do NOT touch unrelated parts of the app.

========================================================
1) POS ADAPTER LAYER (foundation)
========================================================
Create:
- /pos/PosAdapterInterface.php
- /pos/AlohaAdapter.php

Interface should define methods like:
- getProviderKey(): string  // "aloha"
- isConfigured(int $restaurantId): bool
- getConnection(int $restaurantId): array|null
- listLocations(...) optional stub
- syncEmployees(...) stub
- syncLaborActuals(...) stub
- syncSales(...) stub

AlohaAdapter v1 will NOT do real API calls.
It should read from imported/staged tables (see section 2).

Use existing DB tables (already migrated):
- pos_connections
- pos_mappings

========================================================
2) CSV IMPORT WORKFLOW (Aloha v1, no credentials required)
========================================================
We will implement a flexible import wizard because Aloha exports vary.

Add NEW migrations (safe, additive) under /migrations:
- 013_aloha_import_batches.sql
- 014_aloha_employees_stage.sql
- 015_aloha_labor_punches_stage.sql
- 016_aloha_sales_daily_stage.sql

Tables:
A) aloha_import_batches
- id PK
- restaurant_id
- provider (default "aloha")
- import_type enum ('employees','labor','sales')
- original_filename
- status enum ('uploaded','mapped','processed','failed')
- mapping_json TEXT (stores header->field mapping)
- created_by
- created_at
- processed_at nullable
- error_text nullable

B) aloha_employees_stage
- id PK
- restaurant_id
- batch_id
- external_employee_id (varchar)
- first_name, last_name, display_name
- email nullable
- is_active tinyint
- raw_json TEXT (optional)
- indexes (restaurant_id, batch_id), (restaurant_id, external_employee_id)

C) aloha_labor_punches_stage
- id PK
- restaurant_id
- batch_id
- external_employee_id (varchar)
- punch_in_dt datetime
- punch_out_dt datetime nullable
- job_code varchar nullable
- location_code varchar nullable
- raw_json TEXT optional
- indexes (restaurant_id, batch_id), (restaurant_id, external_employee_id, punch_in_dt)

D) aloha_sales_daily_stage
- id PK
- restaurant_id
- batch_id
- business_date date
- gross_sales decimal(12,2)
- net_sales decimal(12,2) nullable
- orders_count int nullable
- raw_json TEXT optional
- unique (restaurant_id, business_date)
- index (restaurant_id, business_date)

NOTE:
Do not assume specific Aloha column names. We will map CSV headers.

========================================================
3) INTEGRATIONS UI (connect + import + mapping + processing)
========================================================
Create a new page:
- /integrations/aloha.php  (or /pos/aloha.php if integrations folder doesn't exist)

UI Requirements (premium, mobile-first):
- Section 1: "Aloha Connection"
  - Shows status: Connected/Not Connected using pos_connections row for provider="aloha"
  - For v1, "Connected" means enabled + import workflow available.
  - Button: Enable Aloha (creates/updates pos_connections with status="enabled" and credentials_json with mode="csv_import")

- Section 2: "Import Data" with 3 cards:
  1) Import Employees CSV
  2) Import Labor Punches CSV
  3) Import Sales Daily CSV (optional but build now)
  Each card -> upload CSV -> next step mapping -> process -> show summary.

Implement mapping step:
- After upload, read the first row headers.
- Show dropdown mapping for required fields.

Required mappings:
Employees import must map:
- external_employee_id (required)
- display_name OR (first_name + last_name) (required one of)
- is_active (optional)

Labor punches import must map:
- external_employee_id (required)
- punch_in_dt (required)
- punch_out_dt (optional)
- job_code (optional)

Sales daily import must map:
- business_date (required)
- gross_sales (required)
- net_sales (optional)
- orders_count (optional)

Processing rules:
- Parse CSV safely with fgetcsv, handle UTF-8, trim BOM.
- Store raw row as JSON optional for debug.
- Validate dates and numbers; if invalid row -> skip and count errors.
- Write a batch summary:
  - rows_total, rows_imported, rows_skipped, errors_count, top 5 errors

Add a small "Recent Imports" list with batch status and summary.

Security:
- Manager-only access to /integrations/aloha.php and all import actions.
- CSRF protect all POSTs.

========================================================
4) MAPPING ALOHA -> HOSPIEDGE (employees and roles)
========================================================
After employees import is processed:
- Show a "Map Employees" section:
  - For each staged employee:
    - If a pos_mapping exists (type='employee') show linked staff member
    - Else allow manager to:
      a) link to an existing staff_members row, OR
      b) create a new staff_members row (if staff_members table exists and fields allow)
- Store mapping in pos_mappings:
  - type='employee'
  - external_id = external_employee_id
  - internal_id = staff_members.id

Job/role mapping:
- Provide a "Map Job Codes to Roles" section:
  - Derive distinct job_code values from aloha_labor_punches_stage for recent batches.
  - Allow mapping each job_code -> roles.id
  - Store in pos_mappings with type='role' (external_id=job_code, internal_id=roles.id)

IMPORTANT:
Do not break if staff_members schema differs.
If staff_members fields are unknown, do a repo scan and adapt minimally.
If creating staff_members rows isn't safe, then allow mapping-only to existing staff members and document it.

========================================================
5) SCHEDULED VS ACTUAL REPORT (the payoff)
========================================================
Create or update:
- /schedule/labor_actuals.php

It should show:
- Week selector (same as schedule index)
- A table/list by staff:
  - Scheduled hours (from shifts assigned to staff_id, draft/published selectable)
  - Actual hours (from aloha_labor_punches_stage joined via pos_mappings employee mapping)
  - Variance (actual - scheduled)
- Also show daily totals summary:
  - scheduled hours per day
  - actual hours per day
Optionally show labor % if sales daily imported:
  - labor_hours * avg_rate (if available) vs gross_sales
But if rates not available, show hours-only and keep it clean.

Rules:
- Actual hours computed as sum(punch_out - punch_in) within the week.
- If punch_out is NULL, ignore or treat as open punch and show warning count.
- Always restaurant-scoped.

UI:
- Must match premium schedule UI.
- Empty states:
  - "No Aloha labor data imported yet"
  - "No employee mappings completed"

========================================================
6) /schedule/api.php additions (minimal)
========================================================
Add actions (manager-only, CSRF required):
- aloha_enable (upsert pos_connections)
- aloha_upload_csv (stores file, creates batch row, reads headers)
- aloha_save_mapping (stores mapping_json to batch)
- aloha_process_batch (parses CSV into stage tables)
- aloha_list_batches (for Recent Imports UI)
- aloha_list_jobcodes (distinct job codes)
- aloha_save_pos_mapping (employee/jobcode mappings)

Implement uploads safely:
- validate file extension .csv
- size check (reasonable, configurable)
- store in a private uploads folder or db (prefer filesystem with random name)
- never execute uploaded content
- do not store outside repo webroot if possible
If storage location is unclear, create /storage/imports with .htaccess deny, or store above web root if repo supports.

========================================================
VERIFICATION (REQUIRED)
========================================================
1) php -l all new/edited PHP files.
2) Manual test:
- Enable Aloha (pos_connections row exists)
- Upload a small CSV for employees -> mapping step -> process -> stage table populated
- Map at least 1 employee to a staff member -> pos_mappings saved
- Upload a labor punches CSV -> process -> labor_actuals shows actual hours for mapped employee
- Upload sales CSV (optional) -> verify it stores by business_date
3) Confirm all pages load when DB has 0 rows (empty state), no warnings.

STOP CONDITION:
- Aloha integration page exists and works (enable + import + mapping + summaries).
- Labor actuals report works using imported data.
- Adapter layer exists for future API/agent sync.
- Premium UI preserved. No unrelated refactors.

```

## docs/codex_prompts/07_security_permissions.md.txt

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve premium UI (schedule.css etc).
- Do NOT refactor existing working scheduling logic.
- This task adds a background job queue so heavy work (imports/sync) runs via cron safely.

GOAL:
Implement a lightweight job queue system designed for shared hosting:
- enqueue jobs quickly from web requests
- process jobs in a cron runner (every 5 minutes or manual trigger)
- locking to prevent two workers running the same job
- retries with backoff
- job logs + basic admin view
Then wire Aloha import processing to run through the queue (so uploading/mapping stays fast).

SCOPE:
- Add new DB migration(s)
- Add new files under /jobs (or /cron)
- Add a small jobs admin page under /integrations (optional but recommended)
- Small surgical edits in /schedule/api.php and /integrations/aloha.php to enqueue rather than process inline
- No unrelated refactors

========================================================
1) DATABASE: JOB QUEUE TABLES
========================================================
Create migration files:
- /migrations/017_job_queue.sql
- /migrations/018_job_logs.sql
- /migrations/019_job_locks.sql

Tables:

A) job_queue
- id PK
- restaurant_id nullable (some jobs global)
- job_type varchar(64) NOT NULL  (e.g. 'aloha_process_batch', 'aloha_rebuild_mappings')
- payload_json TEXT NOT NULL
- status enum('queued','running','succeeded','failed','cancelled') NOT NULL default 'queued'
- priority int NOT NULL default 100 (lower = higher priority)
- run_after datetime NOT NULL default CURRENT_TIMESTAMP
- attempts int NOT NULL default 0
- max_attempts int NOT NULL default 5
- last_error TEXT nullable
- created_by int nullable
- created_at datetime
- started_at datetime nullable
- finished_at datetime nullable
Indexes:
- (status, run_after, priority)
- (restaurant_id, status, run_after)

B) job_logs
- id PK
- job_id
- log_level enum('info','warn','error') default 'info'
- message text
- created_at datetime
Indexes:
- (job_id, created_at)

C) job_locks
- lock_key varchar(128) PK   (e.g. 'job_worker_global', 'aloha_batch_123')
- locked_at datetime
- expires_at datetime
- owner varchar(64) nullable
Purpose: prevent concurrent workers and prevent duplicate batch processing.

All tables InnoDB utf8mb4.

========================================================
2) JOB QUEUE LIBRARY (PHP)
========================================================
Create:
- /jobs/job_lib.php

Functions:
- jq_enqueue(PDO $pdo, string $jobType, array $payload, int $priority=100, ?int $restaurantId=null, ?int $createdBy=null, int $delaySeconds=0): int
- jq_log(PDO $pdo, int $jobId, string $level, string $message): void
- jq_acquire_lock(PDO $pdo, string $lockKey, int $ttlSeconds=240, string $owner='worker'): bool
- jq_release_lock(PDO $pdo, string $lockKey): void

Notes:
- Use prepared statements only.
- Lock acquisition should be atomic:
  - If lock_key exists and expires_at > now => fail
  - Else insert/replace with new expires_at
- Keep it simple and safe.

========================================================
3) CRON WORKER
========================================================
Create:
- /jobs/worker.php  (CLI entrypoint, safe to run via cron)
- /jobs/run_once.php (optional: for web manual trigger, manager-only, heavily locked)

Worker behavior:
- Acquire a global lock 'job_worker_global' (ttl ~240s). If can't, exit.
- Fetch up to N jobs (e.g. 10) where:
  status='queued' AND run_after <= NOW()
  ordered by priority asc, id asc
- For each job:
  - set status='running', started_at=NOW(), attempts += 1
  - call the handler based on job_type
  - on success: status='succeeded', finished_at=NOW()
  - on failure:
    - status back to 'queued' if attempts < max_attempts, else 'failed'
    - set last_error
    - set run_after to NOW() + backoff (e.g. attempts^2 minutes)
- Always release global lock.

Handlers to implement NOW:
- job_type='aloha_process_batch'
  payload: { "batch_id": 123, "restaurant_id": 5, "import_type":"employees|labor|sales" }
  The handler should:
   - load batch row (aloha_import_batches) and confirm status is uploaded/mapped (or appropriate)
   - process CSV into stage tables using the SAME logic as current inline processor
   - update aloha_import_batches.status to 'processed' (or 'failed') with summary + errors
   - write job_logs events for progress

IMPORTANT:
- Make processing idempotent:
  - If batch is already processed, handler should succeed quickly and do nothing.
  - If re-processing is allowed, clear stage rows for that batch_id before re-inserting.

========================================================
4) WIRE ALOHA IMPORT TO QUEUE
========================================================
Modify /integrations/aloha.php and/or /schedule/api.php Aloha actions:
- Keep upload + header mapping step synchronous (fast).
- Replace any heavy CSV parsing inside a web request with:
  - enqueue aloha_process_batch job
  - mark batch status 'uploaded' or 'mapped'
  - return UI message: "Import queued—check Recent Imports in a moment."
- Add API action:
  - action=aloha_queue_process_batch (manager-only, CSRF required)
    - enqueues job and returns job_id

Also add "Run Now" button in UI (manager-only) that:
- calls /jobs/run_once.php which:
  - acquires lock
  - runs worker for 1–2 jobs max
  - returns summary JSON
This is for environments where cron isn't set up yet.

========================================================
5) JOBS ADMIN VIEW (recommended)
========================================================
Create:
- /integrations/jobs.php

Manager-only page:
- shows last 50 jobs (queued/running/failed)
- filter by status and job_type
- shows last_error, attempts, created_at, run_after
- button:
  - retry failed job (sets status=queued, run_after=NOW())
  - cancel queued job

All actions CSRF protected and handled via /schedule/api.php OR a new /integrations/api.php.
Prefer keeping it minimal and safe.

========================================================
6) VERIFICATION (REQUIRED)
========================================================
1) php -l on all new/edited PHP files.
2) Manual test:
- Upload an Aloha CSV batch; ensure UI returns quickly and batch shows "queued".
- Run worker manually:
  - php /path/to/jobs/worker.php
  OR hit the Run Now button (run_once.php) if implemented.
- Confirm:
  - stage tables populated
  - batch status becomes 'processed'
  - job shows 'succeeded'
- Test a failure:
  - upload an invalid CSV mapping to force errors
  - confirm job retries then marks failed and logs last_error.

STOP CONDITION:
- Jobs queue + worker works.
- Aloha batch processing can run via queue without blocking web request.
- Admin view exists or minimal visibility is provided via Recent Imports + job logs.
- Premium UI preserved. No unrelated refactors.

```

## docs/codex_prompts/08_verification.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve the premium schedule UI (schedule.css, shared UI includes).
- Do NOT refactor existing working scheduling logic.
- This task focuses on notifications, announcements, and shift swaps/call-outs with audit trail.

GOAL:
Make the scheduling module feel better than top apps by adding:
1) In-app notifications center + toast UX
2) Announcements/broadcast messaging (manager -> staff)
3) Shift swap workflow (staff-initiated, manager approval)
4) Call-out + coverage workflow (staff calls out, marketplace broadcast)
All with clear audit trail and mobile-first UI.

SCOPE:
- /schedule pages + /schedule/api.php
- Add minimal new tables if needed (migrate safely)
- Use existing announcements table if present
- Use shift_trade_requests table if present

========================================================
1) IN-APP NOTIFICATIONS (Core)
========================================================
Add new migrations:
- /migrations/020_notifications.sql

Create table notifications:
- id PK
- restaurant_id
- user_id (recipient)
- type varchar(64)  (e.g. 'shift_assigned','shift_changed','swap_requested','swap_approved','pickup_approved','announcement')
- title varchar(140)
- body text
- link_url varchar(255) nullable (e.g. '/schedule/my.php?week=...')
- is_read tinyint default 0
- created_at datetime
Indexes:
- (restaurant_id, user_id, is_read, created_at)

Create page:
- /schedule/notifications.php
Mobile-first list of notifications with:
- unread badge count
- tap -> opens link_url if present and marks read
- "Mark all read" button

Add to schedule nav:
- Notifications link with unread count badge.

API actions in /schedule/api.php:
- list_notifications
- mark_notification_read
- mark_all_notifications_read

Trigger notifications on key events (manager/staff actions):
- publish_week:
  - notify staff assigned to shifts in that week: "Schedule published"
- create_shift / update_shift:
  - if assigned staff_id: notify that staff
- approve_pickup:
  - notify staff who got shift
  - notify others denied (optional)
- create_time_off + review_time_off:
  - notify staff on decision
- swap request/approval (below)

Implementation detail:
- Create helper function notify_user($pdo, $restaurantId, $userId, $type, $title, $body, $linkUrl=null)
- Keep it lightweight and synchronous (insert row only). No external SMS/email yet.

========================================================
2) ANNOUNCEMENTS / BROADCAST (Manager -> Staff)
========================================================
Use existing announcements table if present.
If missing, create it (but check first):
- announcements: restaurant_id, title, body, audience, starts_at, ends_at, created_by, created_at

Create page:
- /schedule/announcements.php (manager-only create/edit; staff can view)
Features:
- Manager can create announcement with:
  - title, body
  - audience: all / managers / staff / role:<role_id>
  - start/end time optional
- Staff view shows active announcements for them

API actions:
- create_announcement
- update_announcement
- list_announcements
- delete_announcement (soft delete if you prefer, or hard delete OK if scoped)

Also create notifications for:
- each active recipient when a new announcement is posted
(If too heavy, store notification only when user opens announcements page; but prefer direct insert if audience size is manageable.)

========================================================
3) SHIFT SWAP WORKFLOW (Staff -> Manager approval)
========================================================
Use shift_trade_requests table if present.
If not present, add migration /migrations/021_shift_trade_requests.sql.

Swap model (simple):
- A swap request targets ONE shift (shift_id) and proposes:
  - to_staff_id (optional) OR open marketplace swap
- statuses: pending, approved, denied, cancelled

Staff UI:
- In /schedule/my.php each upcoming shift has:
  - "Request Swap" button
- Swap request form:
  - pick a staff member (optional) or "Anyone"
  - notes

Manager UI:
- In /schedule/index.php or a new /schedule/swaps.php:
  - list pending swap requests with shift details
  - approve/deny

Approval rules:
- If to_staff_id is specified:
  - ensure no overlap for to_staff_id
  - ensure not on approved time off
  - assign shift.staff_id = to_staff_id
- If "Anyone":
  - convert shift to open shift and send to marketplace pickup flow OR
  - allow manager to choose from candidates ranked list (reuse ranking logic)
- Mark request approved/denied and create notifications.

API actions:
- create_swap_request (staff)
- cancel_swap_request (staff)
- list_swap_requests (manager/staff views scoped)
- approve_swap_request (manager)
- deny_swap_request (manager)

Audit trail:
- When a swap is approved, store reviewed_by and reviewed_at if columns exist; if not, add them in migration.

========================================================
4) CALL-OUT + COVERAGE WORKFLOW (Best-in-class)
========================================================
Add migration:
- /migrations/022_callouts.sql

Table callouts:
- id PK
- restaurant_id
- shift_id
- staff_id (who called out)
- reason text nullable
- status enum('reported','coverage_requested','covered','manager_closed')
- created_at
- updated_at nullable

Flow:
- Staff on /schedule/my.php can click "Call Out" on an upcoming shift:
  - creates callout row
  - sets shift status remains published, but flagged
  - notifies managers
- Manager sees callouts in /schedule/index.php (top alerts) and can click:
  - "Request Coverage" -> marks the shift open and broadcasts to marketplace
  - optionally "Assign Coverage" -> picks a staff member and assigns immediately
- When covered:
  - callout.status='covered'
  - notifications sent

API actions:
- create_callout (staff)
- list_callouts (manager)
- request_coverage (manager) -> marks shift open + creates an announcement/notification
- close_callout (manager)

========================================================
5) UX REQUIREMENTS (Premium)
========================================================
- Keep all new pages consistent with schedule.css and existing components.
- Add small badge chips:
  - Unread notifications count
  - Callout alert
  - Pending swaps count
- Provide clear empty states.

========================================================
SECURITY (Non-negotiable)
========================================================
- CSRF required for all POST actions.
- Restaurant scoping everywhere.
- Staff can only request swap/callout for their own shifts.
- Managers only approve/deny swaps, create announcements for everyone.

========================================================
VERIFICATION (REQUIRED)
========================================================
1) php -l on all touched PHP files.
2) Manual tests:
- Publish week -> staff gets notification
- Manager edits a shift -> staff gets notification
- Manager posts announcement -> staff can see + gets notification
- Staff requests swap -> manager approves -> shift reassigns -> both notified
- Staff calls out -> manager requests coverage -> open shift appears -> staff picks up -> covered status set

STOP CONDITION:
- Notifications center works.
- Announcements work.
- Swap flow works end-to-end.
- Call-out + coverage flow works end-to-end.
- Premium UI preserved; no unrelated refactors.

```

## docs/codex_prompts/09_Labor Rules Engine_ Compliance_Enforcement Signals.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve premium UI system (schedule.css and shared UI includes).
- Do NOT refactor existing working scheduling logic.
- This task adds a labor rules/compliance engine + UI, and integrates checks into shift create/update/publish.

GOAL:
Add a “Rules Engine” that:
1) prevents illegal/non-compliant schedules (block or warn)
2) provides a Compliance Dashboard (manager view) with explainable violations
3) generates “enforcement signals” for POS actuals (Aloha-ready) like early punch / unscheduled punch alerts
4) never requires future styling rework (use existing UI components)

SCOPE:
- /schedule pages + /schedule/api.php
- New migrations (additive)
- Small surgical edits to shift create/update/publish to run compliance checks
- Minor additions to labor_actuals.php to show punch exceptions

========================================================
1) DB: POLICY + VIOLATIONS TABLES
========================================================
Add migrations:
- /migrations/023_schedule_policies.sql
- /migrations/024_schedule_policy_sets.sql
- /migrations/025_schedule_violations.sql
- /migrations/026_schedule_enforcement_events.sql

Tables:

A) schedule_policy_sets
- id PK
- restaurant_id
- name varchar(80) (e.g. "NY Default", "Company Standard")
- is_active tinyint default 1
- is_default tinyint default 0
- created_at datetime
Unique: (restaurant_id, name)

B) schedule_policies
- id PK
- restaurant_id
- policy_set_id
- policy_key varchar(64) NOT NULL
- enabled tinyint default 1
- mode enum('warn','block') default 'warn'
- params_json TEXT NOT NULL (JSON for policy thresholds)
- created_at datetime
Index: (restaurant_id, policy_set_id)

Policy keys to support in v1:
- "max_weekly_hours" params: { "hours": 40 }
- "max_daily_hours" params: { "hours": 12 }
- "min_rest_between_shifts" params: { "hours": 10 }   // anti-clopen
- "break_required_after_hours" params: { "hours_worked": 6, "break_minutes": 30 }
- "minor_rules" params: { "enabled": true, "max_daily_hours": 8, "max_weekly_hours": 20, "latest_end_hour": 22 }
- "availability_conflict" params: { }  // assign during unavailable
- "timeoff_conflict" params: { }       // assign during approved time off

C) schedule_violations
- id PK
- restaurant_id
- week_start_date date
- shift_id nullable
- staff_id nullable
- policy_key varchar(64)
- severity enum('info','warn','block')  // match policy mode; block=hard stop
- message varchar(255)
- details_json TEXT nullable
- created_at datetime
Indexes:
- (restaurant_id, week_start_date)
- (restaurant_id, staff_id, week_start_date)
- (restaurant_id, policy_key, week_start_date)

D) schedule_enforcement_events
- id PK
- restaurant_id
- event_type varchar(64) // 'unscheduled_punch','early_punch','late_punch','missed_break','overtime_risk'
- staff_id nullable
- shift_id nullable
- external_employee_id varchar(64) nullable
- event_dt datetime
- message varchar(255)
- details_json TEXT nullable
- created_at datetime
Indexes:
- (restaurant_id, event_type, event_dt)
- (restaurant_id, staff_id, event_dt)

========================================================
2) STAFF ATTRIBUTES NEEDED (Minor flag, etc.)
========================================================
Check if staff_members table has fields for minor/birthdate.
If not, add a minimal additive migration:
- /migrations/027_staff_labor_profile.sql

Table staff_labor_profile (avoid altering staff_members if risky):
- id PK
- restaurant_id
- staff_id unique within restaurant
- is_minor tinyint default 0
- birthdate date nullable
- max_weekly_hours_override decimal(5,2) nullable
- notes varchar(255) nullable
- created_at datetime
Index: (restaurant_id, staff_id)

(Use this table for minor rules and overrides.)

========================================================
3) RULES ENGINE IMPLEMENTATION (Explainable + Deterministic)
========================================================
Create:
- /schedule/rules_engine.php

It must provide functions:
- se_get_active_policy_set_id(PDO $pdo, int $restaurantId): int
- se_load_policies(PDO $pdo, int $restaurantId, int $policySetId): array
- se_check_shift(PDO $pdo, int $restaurantId, array $shift, array $policies): array
  returns list of violations (each with policy_key, severity, message, details)
- se_check_week(PDO $pdo, int $restaurantId, string $weekStart, array $policies): array
  checks week-wide constraints (weekly hours, minors weekly, etc.)

Implementation notes:
- Keep it fast: only query needed data for that staff/week.
- Use existing overlap checks but do not refactor them—wrap or reuse.
- Severity:
  - If policy enabled and mode=block -> generate severity='block'
  - If warn -> severity='warn'
- Make messages human readable and specific.

Checks required in v1:
A) timeoff_conflict (block by default)
- assigned shift overlaps approved time off

B) availability_conflict (warn by default)
- assigned shift overlaps a day marked unavailable

C) min_rest_between_shifts (warn/block)
- rest gap < X hours between end of one shift and start of next for same staff

D) max_daily_hours (warn/block)
- sum assigned shift hours per staff per day > threshold

E) max_weekly_hours (warn/block)
- sum assigned shift hours per staff per week > threshold
- allow override in staff_labor_profile

F) break_required_after_hours (warn)
- if shift duration exceeds hours_worked threshold AND break_minutes < break_minutes required -> warn

G) minor_rules (warn/block)
- if staff_labor_profile.is_minor=1 then enforce:
  - max daily hours
  - max weekly hours
  - latest end hour (e.g., 22)
(Use local restaurant timezone assumptions; keep simple.)

========================================================
4) UI: RULES SETTINGS + COMPLIANCE DASHBOARD
========================================================
Create pages (premium UI, mobile-first):
- /schedule/rules.php (manager-only)
- /schedule/compliance.php (manager-only)
- Add links in schedule nav for managers:
  - Rules
  - Compliance

/schedule/rules.php requirements:
- Policy Set selector (default set)
- Toggle each policy enabled
- Set mode warn/block
- Edit parameters via simple form inputs (numbers, time)
- Save via API action update_policy_set
- Seed a default policy set automatically if none exists:
  - max_weekly_hours=40 warn
  - max_daily_hours=12 warn
  - min_rest_between_shifts=10 warn
  - break_required_after_hours={6,30} warn
  - timeoff_conflict block
  - availability_conflict warn
  - minor_rules warn (enabled=false by default or enabled=true—choose safest)

Include “Reset to defaults” (manager confirm) which rewrites policies for that set.

Create /schedule/compliance.php requirements:
- Week selector (same as schedule index)
- Summary cards:
  - Blockers count
  - Warnings count
  - Top policy issues
- List violations grouped by day/staff with filters:
  - show all / only blockers / only warnings
- Link from each violation to the relevant shift (scroll or highlight if possible)

========================================================
5) Integrate checks into scheduling actions (create/update/publish)
========================================================
Modify /schedule/api.php actions:
- create_shift
- update_shift
- publish_week
- approve_pickup
- approve_swap_request (if implemented)

Behavior:
- On create/update/approve assignment:
  - run se_check_shift
  - if any violation severity='block' then return 422 with error message and details
  - if warnings exist:
    - allow save but return { success:true, warnings:[...] } and show warnings in UI
- On publish_week:
  - run se_check_week + per-shift checks
  - write violations into schedule_violations table for that week (delete old ones for week first, scoped by restaurant)
  - If blockers exist and policy mode is block:
    - prevent publish and return 422 with blockers summary
  - If only warnings:
    - allow publish and store warnings in schedule_violations

IMPORTANT:
- Do not break existing publish_week flow.
- Violations storage must be restaurant-scoped and week-scoped.

========================================================
6) Enforcement Signals from Actuals (Aloha-ready)
========================================================
Update /schedule/labor_actuals.php (premium UI):
- For mapped employees with punches:
  Generate exceptions and insert into schedule_enforcement_events (optional; or compute on read).
Exceptions to support:
A) unscheduled_punch:
- punch in/out exists, but no scheduled shift overlaps punch_in_dt within +/- 30 minutes

B) early_punch / late_punch:
- punch_in_dt more than 10 minutes early vs scheduled start -> early_punch
- punch_in_dt more than 10 minutes late vs scheduled start -> late_punch

Show:
- A “Punch Exceptions” section with counts and list
- Filter by type

API action:
- generate_enforcement_events (manager-only) for a selected week
  - clears existing events for week (optional) and regenerates
  - stores into schedule_enforcement_events

This prepares the future "schedule enforcement" integration without needing direct POS control yet.

========================================================
7) VERIFICATION (REQUIRED)
========================================================
1) php -l on all new/edited PHP files.
2) Manual tests:
- rules.php loads and saves policy values
- create a shift that violates a blocking policy -> save is blocked with readable error
- create a shift that triggers a warning -> save succeeds with warnings displayed
- publish_week:
  - stores violations into schedule_violations
  - blocks publishing if blockers exist
- compliance.php shows correct week counts and lists violations
- labor_actuals shows punch exceptions (if punches exist; otherwise empty state)
3) Confirm empty DB does not crash any page.

STOP CONDITION:
- Rules settings page works.
- Compliance dashboard works.
- Blocking/warning behavior works in schedule actions.
- Enforcement event signals are generated and visible.
- Premium UI preserved; no unrelated refactors.

```

## docs/codex_prompts/10_Security_Permissions.md

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve premium UI system (schedule.css and shared UI includes).
- DO NOT refactor working logic; only surgical fixes and hardening.
- Focus only on the scheduling + integrations components created so far.

GOAL:
Do a production-grade hardening and proof pass:
1) permissions + IDOR protection everywhere
2) CSRF coverage + consistent JSON error behavior
3) audit trail of sensitive actions
4) performance improvements (indexes + query tuning) for shared hosting
5) deliver QA + security checklists so we can trust it’s built correctly

SCOPE:
- /schedule/*.php
- /integrations/*.php (Aloha/jobs pages)
- /jobs/* (worker/queue)
- migrations (additive only)
- docs (QA + security + perf)

========================================================
1) PERMISSIONS / ACCESS CONTROL (NO GAPS)
========================================================
Task:
- Identify how this repo determines “manager” vs “staff”.
- Enforce it consistently on:
  - roles CRUD
  - shift create/update/delete/publish
  - approvals (time off, pickups, swaps)
  - Aloha imports/mappings
  - jobs admin / run_once
  - rules/compliance pages and actions (if present)
- Staff must only be able to modify:
  - their own availability
  - their own time-off requests (create/cancel if supported)
  - their own swap/callout/pickup requests
- Managers can act across restaurant scope, but NEVER across restaurants.

If the repo lacks a clear permission model:
- Add a minimal table via migration:
  /migrations/028_schedule_permissions.sql
  schedule_permissions:
    - id PK
    - restaurant_id
    - user_id
    - can_manage_schedule tinyint default 0
    - can_manage_integrations tinyint default 0
    - created_at
  Enforce it through a single helper function in /schedule/_auth.php (create if missing).
Keep changes minimal and isolated.

========================================================
2) IDOR PROTECTION (CRITICAL)
========================================================
For every action that accepts IDs (shift_id, role_id, staff_id, request_id, batch_id, job_id):
- Verify the object belongs to the SAME restaurant_id before read/write.
- Verify staff ownership where appropriate (staff editing their own request).
- Return 403/404 safely, never leak cross-restaurant existence.

========================================================
3) CSRF + JSON RESPONSE CONSISTENCY
========================================================
- Confirm EVERY POST action except ping requires CSRF.
- Ensure CSRF token is included in all forms and all fetch/AJAX calls.
- Standardize JSON response + HTTP codes:
  - 200 success
  - 401 unauthorized
  - 403 forbidden / csrf
  - 422 validation
  - 500 unexpected
- Ensure HTML pages redirect to login on unauthorized, but APIs return JSON.

========================================================
4) AUDIT LOG (ACCOUNTABILITY)
========================================================
Add migration:
- /migrations/029_audit_log.sql

Table audit_log:
- id PK
- restaurant_id
- user_id
- action varchar(64)  (e.g. 'shift_create','shift_update','shift_publish','role_update','timeoff_approve','aloha_import_process','job_retry')
- entity_type varchar(64) (e.g. 'shift','role','time_off','batch','job')
- entity_id varchar(64)
- before_json TEXT nullable
- after_json TEXT nullable
- ip varchar(64) nullable
- user_agent varchar(255) nullable
- created_at datetime
Indexes:
- (restaurant_id, created_at)
- (restaurant_id, action, created_at)

Implement helper:
- /schedule/audit.php (or /lib/audit.php)
  audit_log($pdo, $restaurantId, $userId, $action, $entityType, $entityId, $beforeArr, $afterArr)

Add audit logging to sensitive actions:
- create/update/delete/publish shift
- approve/deny time off
- approve/deny pickups/swaps
- create/update role
- Aloha import: upload/mapping/process
- job retry/cancel (if admin page exists)

Keep payload sizes reasonable (truncate JSON if huge).

========================================================
5) PERFORMANCE PASS (SHARED HOSTING READY)
========================================================
- Review queries in:
  - schedule week view
  - my schedule
  - labor_actuals
  - compliance/quality score generation
  - open shifts marketplace lists
  - jobs list / imports list
- Ensure indexes exist for the query patterns.
If missing, add additive migrations:
  - /migrations/030_perf_indexes.sql
Include indexes like:
  - shifts (restaurant_id, start_dt)
  - shifts (restaurant_id, staff_id, start_dt)
  - time_off_requests (restaurant_id, staff_id, start_dt)
  - shift_pickup_requests (restaurant_id, shift_id, status)
  - schedule_violations (restaurant_id, week_start_date)
  - notifications (restaurant_id, user_id, is_read, created_at)
  - job_queue (status, run_after, priority)

Avoid N+1: join staff/roles once where practical.

========================================================
6) UX / RELIABILITY POLISH (NO RESTYLE)
========================================================
- Confirm header safe-area padding works on iPhone/PWA.
- Confirm bottom nav never blocks page content (add padding-bottom using safe-area vars).
- Add consistent empty states and error toasts (using existing CSS).
- Ensure forms are resilient:
  - show validation errors returned from API
  - do not lose user inputs on error where possible
- Add “Loading…” state on actions that might take 1–3 seconds.

========================================================
7) QA + SECURITY DOCUMENTATION (PROVE IT)
========================================================
Create docs:
- /docs/QA_schedule_regression.md
  Include step-by-step tests for:
  - empty DB behavior
  - roles CRUD
  - availability save
  - create/update/delete shift
  - overlap prevention
  - publish week
  - my schedule shows only published
  - time-off request approve/deny and blocking conflicts
  - open shift pickup flow
  - swap flow
  - callout + coverage flow
  - notifications + announcements
  - quality score generation
  - rules/compliance pages (if present)
  - Aloha import + mapping + labor_actuals report
  - job worker runs and locks correctly

- /docs/SECURITY_schedule_checklist.md
  Must include:
  - CSRF verified on every POST route
  - IDOR checks for every entity ID
  - permission checks for every manager-only action
  - restaurant scoping on every query
  - file upload safety rules (CSV only, size limit, storage not executable)
  - worker lock behavior & job retry safety

- /docs/PERF_schedule_notes.md
  Summarize:
  - biggest queries
  - indexes required
  - limits applied (e.g. open shifts date range)
  - cron frequency recommendations

========================================================
8) VERIFICATION (REQUIRED)
========================================================
- Run php -l on every edited PHP file.
- Confirm no PHP warnings/notices on empty DB.
- Confirm unauthorized actions fail correctly (401/403).
- Confirm cross-restaurant ID tests fail safely (404/403).

STOP CONDITION:
- Permissions and IDOR are airtight.
- Audit log is in place for sensitive actions.
- Performance indexes added where needed.
- QA + Security + Perf docs exist and are actionable.
- Premium UI preserved; no unrelated refactors.

```

## docs/codex_prompts/10_Security_Permissions.txt

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve premium UI system (schedule.css and shared UI includes).
- DO NOT refactor working logic; only surgical fixes and hardening.
- Focus only on the scheduling + integrations components created so far.

GOAL:
Do a production-grade hardening and proof pass:
1) permissions + IDOR protection everywhere
2) CSRF coverage + consistent JSON error behavior
3) audit trail of sensitive actions
4) performance improvements (indexes + query tuning) for shared hosting
5) deliver QA + security checklists so we can trust it’s built correctly

SCOPE:
- /schedule/*.php
- /integrations/*.php (Aloha/jobs pages)
- /jobs/* (worker/queue)
- migrations (additive only)
- docs (QA + security + perf)

========================================================
1) PERMISSIONS / ACCESS CONTROL (NO GAPS)
========================================================
Task:
- Identify how this repo determines “manager” vs “staff”.
- Enforce it consistently on:
  - roles CRUD
  - shift create/update/delete/publish
  - approvals (time off, pickups, swaps)
  - Aloha imports/mappings
  - jobs admin / run_once
  - rules/compliance pages and actions (if present)
- Staff must only be able to modify:
  - their own availability
  - their own time-off requests (create/cancel if supported)
  - their own swap/callout/pickup requests
- Managers can act across restaurant scope, but NEVER across restaurants.

If the repo lacks a clear permission model:
- Add a minimal table via migration:
  /migrations/028_schedule_permissions.sql
  schedule_permissions:
    - id PK
    - restaurant_id
    - user_id
    - can_manage_schedule tinyint default 0
    - can_manage_integrations tinyint default 0
    - created_at
  Enforce it through a single helper function in /schedule/_auth.php (create if missing).
Keep changes minimal and isolated.

========================================================
2) IDOR PROTECTION (CRITICAL)
========================================================
For every action that accepts IDs (shift_id, role_id, staff_id, request_id, batch_id, job_id):
- Verify the object belongs to the SAME restaurant_id before read/write.
- Verify staff ownership where appropriate (staff editing their own request).
- Return 403/404 safely, never leak cross-restaurant existence.

========================================================
3) CSRF + JSON RESPONSE CONSISTENCY
========================================================
- Confirm EVERY POST action except ping requires CSRF.
- Ensure CSRF token is included in all forms and all fetch/AJAX calls.
- Standardize JSON response + HTTP codes:
  - 200 success
  - 401 unauthorized
  - 403 forbidden / csrf
  - 422 validation
  - 500 unexpected
- Ensure HTML pages redirect to login on unauthorized, but APIs return JSON.

========================================================
4) AUDIT LOG (ACCOUNTABILITY)
========================================================
Add migration:
- /migrations/029_audit_log.sql

Table audit_log:
- id PK
- restaurant_id
- user_id
- action varchar(64)  (e.g. 'shift_create','shift_update','shift_publish','role_update','timeoff_approve','aloha_import_process','job_retry')
- entity_type varchar(64) (e.g. 'shift','role','time_off','batch','job')
- entity_id varchar(64)
- before_json TEXT nullable
- after_json TEXT nullable
- ip varchar(64) nullable
- user_agent varchar(255) nullable
- created_at datetime
Indexes:
- (restaurant_id, created_at)
- (restaurant_id, action, created_at)

Implement helper:
- /schedule/audit.php (or /lib/audit.php)
  audit_log($pdo, $restaurantId, $userId, $action, $entityType, $entityId, $beforeArr, $afterArr)

Add audit logging to sensitive actions:
- create/update/delete/publish shift
- approve/deny time off
- approve/deny pickups/swaps
- create/update role
- Aloha import: upload/mapping/process
- job retry/cancel (if admin page exists)

Keep payload sizes reasonable (truncate JSON if huge).

========================================================
5) PERFORMANCE PASS (SHARED HOSTING READY)
========================================================
- Review queries in:
  - schedule week view
  - my schedule
  - labor_actuals
  - compliance/quality score generation
  - open shifts marketplace lists
  - jobs list / imports list
- Ensure indexes exist for the query patterns.
If missing, add additive migrations:
  - /migrations/030_perf_indexes.sql
Include indexes like:
  - shifts (restaurant_id, start_dt)
  - shifts (restaurant_id, staff_id, start_dt)
  - time_off_requests (restaurant_id, staff_id, start_dt)
  - shift_pickup_requests (restaurant_id, shift_id, status)
  - schedule_violations (restaurant_id, week_start_date)
  - notifications (restaurant_id, user_id, is_read, created_at)
  - job_queue (status, run_after, priority)

Avoid N+1: join staff/roles once where practical.

========================================================
6) UX / RELIABILITY POLISH (NO RESTYLE)
========================================================
- Confirm header safe-area padding works on iPhone/PWA.
- Confirm bottom nav never blocks page content (add padding-bottom using safe-area vars).
- Add consistent empty states and error toasts (using existing CSS).
- Ensure forms are resilient:
  - show validation errors returned from API
  - do not lose user inputs on error where possible
- Add “Loading…” state on actions that might take 1–3 seconds.

========================================================
7) QA + SECURITY DOCUMENTATION (PROVE IT)
========================================================
Create docs:
- /docs/QA_schedule_regression.md
  Include step-by-step tests for:
  - empty DB behavior
  - roles CRUD
  - availability save
  - create/update/delete shift
  - overlap prevention
  - publish week
  - my schedule shows only published
  - time-off request approve/deny and blocking conflicts
  - open shift pickup flow
  - swap flow
  - callout + coverage flow
  - notifications + announcements
  - quality score generation
  - rules/compliance pages (if present)
  - Aloha import + mapping + labor_actuals report
  - job worker runs and locks correctly

- /docs/SECURITY_schedule_checklist.md
  Must include:
  - CSRF verified on every POST route
  - IDOR checks for every entity ID
  - permission checks for every manager-only action
  - restaurant scoping on every query
  - file upload safety rules (CSV only, size limit, storage not executable)
  - worker lock behavior & job retry safety

- /docs/PERF_schedule_notes.md
  Summarize:
  - biggest queries
  - indexes required
  - limits applied (e.g. open shifts date range)
  - cron frequency recommendations

========================================================
8) VERIFICATION (REQUIRED)
========================================================
- Run php -l on every edited PHP file.
- Confirm no PHP warnings/notices on empty DB.
- Confirm unauthorized actions fail correctly (401/403).
- Confirm cross-restaurant ID tests fail safely (404/403).

STOP CONDITION:
- Permissions and IDOR are airtight.
- Audit log is in place for sensitive actions.
- Performance indexes added where needed.
- QA + Security + Perf docs exist and are actionable.
- Premium UI preserved; no unrelated refactors.

```

## docs/codex_prompts/11_Release_Packaging_Setup_Wizard.txt

```
You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Preserve premium UI system (schedule.css and shared UI includes).
- Do NOT refactor working logic.
- This task is packaging + onboarding + demo tooling + docs, not core logic changes.

GOAL:
Make the scheduling module “ready to sell” and easy to deploy:
1) Setup Wizard (first-run) so new restaurants can start scheduling in minutes
2) Demo Mode + seed data (optional, safe)
3) Sales/Compare page (feature matrix + proof artifacts)
4) Deployment checklist for shared hosting + cron + storage hardening
5) Zero future styling rework — use existing UI components everywhere

SCOPE:
- Add new files under /schedule, /docs, /scripts (or /tools)
- Minimal surgical edits to add nav links
- Add migrations only if needed (additive only)

========================================================
1) SETUP WIZARD (FIRST-RUN EXPERIENCE)
========================================================
Create: /schedule/setup.php (manager-only)

Purpose:
- Detect if scheduling is “not configured” for this restaurant:
  - no roles exist OR no policy set exists OR permissions missing
- Provide a guided setup:
  Step 1: Confirm restaurant timezone (store in an existing settings table if present;
          otherwise create a tiny table schedule_settings).
  Step 2: Create default roles/stations (FOH/BOH templates):
          - Server, Host, Bartender, Expo, Runner, Dishwasher, Line Cook, Prep Cook, Manager
          User can add/remove.
  Step 3: Create default policy set (rules engine) and set as default
  Step 4: Grant schedule permissions:
          - make current user a schedule manager for this restaurant
  Step 5: Optional: enable Aloha integration mode (CSV import) and show link

Requirements:
- Must not crash if settings tables are missing.
- Use CSRF on forms.
- Premium UI using schedule.css.
- Do not assume global admin; just restaurant scope.

If a settings table does not exist, add migration:
- /migrations/031_schedule_settings.sql
schedule_settings:
  - id PK
  - restaurant_id unique
  - timezone varchar(64) default 'America/New_York'
  - demo_mode tinyint default 0
  - created_at datetime

========================================================
2) DEMO MODE + SEED DATA (SAFE, OPTIONAL)
========================================================
Create:
- /docs/DEMO_MODE.md
- /scripts/seed_schedule_demo.php  (CLI and/or web-trigger safe)
- Optional: /migrations/032_demo_seed_optional.sql (ONLY if your repo uses migrations for seed; otherwise script only)

Demo mode rules:
- Demo mode must be PER-RESTAURANT (schedule_settings.demo_mode=1).
- The seed script should:
  - Create a few roles if missing
  - Create a few staff members ONLY if safe:
    - If staff_members schema is uncertain, do NOT insert there.
    - Instead create demo staff in a new table schedule_demo_staff and map internally only for demo.
  - Create 2 weeks of shifts (some open shifts)
  - Create 1–2 time-off requests
  - Create 1 announcement
  - Create 1 callout and 1 pending pickup request if those tables exist
- Add “Reset Demo Data” option (danger confirm) that deletes only demo-tagged rows for that restaurant.

Implementation approach:
- Add a column is_demo tinyint default 0 to your schedule-owned tables ONLY IF safe and additive.
  If altering many tables is risky, use a separate demo_tag table:
  - demo_tags: restaurant_id, entity_type, entity_id
  and delete based on tags.
Prefer minimal-risk approach.

========================================================
3) SALES/COMPARE PAGE (PROOF + MATRIX)
========================================================
Create: /schedule/compare.php (manager-only; or owner-only if roles exist)

This is NOT a marketing lie page; it is a structured checklist:
- Section A: “Core Scheduling Parity” (templates, swaps, time-off, messaging, notifications, labor actuals)
- Section B: “Better Than Typical Schedulers” (quality score, marketplace, triggers, compliance dashboard, punch exceptions)
- Section C: “Proof” buttons/links:
  - link to Compliance page, Quality score, Labor actuals, Notifications
  - show counts for last 7 days: swaps, callouts, pickup approvals, violations (if tables exist)

Comparison matrix design:
- Columns:
  - HospiEdge Scheduler (Yes)
  - Typical Scheduling Tools (Often / Sometimes / Rarely)
DO NOT name specific competitors to avoid inaccurate claims.
Keep text conservative and factual:
- “Quality score tied to compliance + audits” -> Rarely
- “Incident/temp triggers to staffing actions” -> Rarely
- “Audit-ready compliance evidence pack” -> Rarely
If unsure, label as “Differentiator” instead of claiming competitor absence.

Premium UI requirement.

========================================================
4) DEPLOYMENT CHECKLIST (Namecheap shared hosting friendly)
========================================================
Create docs:
- /docs/DEPLOY_schedule_checklist.md
Include:
- Required PHP version notes (whatever repo uses)
- Running migrations (phpMyAdmin instructions)
- Cron setup:
  - run jobs/worker.php every 5 minutes
  - optional manual run_once
- Storage hardening:
  - where CSV uploads are stored
  - ensure folder is not executable and not publicly browsable
  - include .htaccess deny rules if Apache
Create:
- /storage/imports/.htaccess (if Apache) with Deny from all or equivalent.
If Nginx environment is possible, document alternative.

========================================================
5) FINAL NAV + UX TOUCHES (NO RESTYLE)
========================================================
- Add links (manager-only) in schedule nav:
  - Setup
  - Compare
  - Jobs (if jobs admin exists)
- Add a small “System Status” card somewhere (Setup or Compare):
  - Cron worker last run time (if stored)
  - Aloha enabled yes/no
  - Demo mode yes/no
If no “last run time” exists, add in schedule_settings:
  - last_worker_run_at datetime nullable
and update in worker.php when it runs successfully (tiny edit).

========================================================
6) VERIFICATION (REQUIRED)
========================================================
- php -l on every new/edited PHP file.
- Manual checks:
  - setup.php runs on empty DB and creates defaults safely
  - compare.php loads and shows feature checklist and proof links
  - demo seed can run (or is safely blocked if schema unknown)
  - deploy checklist is accurate for this repo structure
  - storage folder is protected (not web accessible)

STOP CONDITION:
- Setup wizard exists and gets a new restaurant ready fast.
- Demo mode exists and is safe (no breaking assumptions).
- Compare page exists and is conservative + helpful.
- Deployment checklist exists.
- Premium UI preserved; no unrelated refactors.

```

## docs/codex_prompts/12_Release_Packaging_Setup_Wizard.txt

```
You are Codex working in this repository (the scheduler is a standalone PHP app with its own database).

READ FIRST:
- Follow AGENTS.md strictly if present.
- Do NOT refactor unrelated code. Prefer new files.
- Use PDO prepared statements only.
- All auth must be secure: password_hash/password_verify, CSRF, rate limiting, one-time reset tokens.

GOAL:
Implement a complete login system for this standalone scheduler app:
- manager + team member roles
- registration (manager creates the restaurant)
- team member invite flow
- login/logout
- forgot password + password reset (email link)
- basic access control helpers
- premium, consistent UI (reuse existing schedule.css if present)

ASSUMPTIONS:
- This app has its own db.php pointing to the scheduler database.
- This app is hosted under HTTPS.
- Email sending can start with PHP mail() and be swapped later (wrap it in one function).

========================================================
1) DATABASE MIGRATIONS (additive)
========================================================
Create migrations under /migrations:

/migrations/040_auth_core.sql
- users:
  - id PK
  - email varchar(190) unique
  - name varchar(120)
  - password_hash varchar(255)
  - is_active tinyint default 1
  - email_verified tinyint default 0
  - last_login_at datetime nullable
  - failed_logins int default 0
  - locked_until datetime nullable
  - created_at datetime
  - updated_at datetime nullable
  Index: (email)

/migrations/041_restaurants_memberships.sql
- restaurants:
  - id PK
  - name varchar(140)
  - created_by int (users.id)
  - created_at datetime
- user_restaurants:
  - id PK
  - restaurant_id
  - user_id
  - role enum('manager','team') default 'team'
  - is_active tinyint default 1
  - created_at datetime
  Unique: (restaurant_id, user_id)
  Index: (user_id), (restaurant_id)

/migrations/042_password_resets.sql
- password_resets:
  - id PK
  - user_id
  - token_hash char(64)   -- sha256 hex of raw token
  - expires_at datetime
  - used_at datetime nullable
  - request_ip varchar(64) nullable
  - created_at datetime
  Index: (user_id), (expires_at), (token_hash)

/migrations/043_invites.sql
- invites:
  - id PK
  - restaurant_id
  - email varchar(190)
  - role enum('manager','team') default 'team'
  - token_hash char(64)
  - expires_at datetime
  - accepted_at datetime nullable
  - created_by int
  - created_at datetime
  Unique: (restaurant_id, email, accepted_at)  -- allow multiple invites over time
  Index: (restaurant_id), (email), (token_hash)

If schedule tables use restaurant_id already, keep consistent naming.

========================================================
2) CONFIG + MAILER (single place)
========================================================
Create:
- /config.php
  - APP_NAME
  - APP_URL (base URL, used to build links)
  - MAIL_FROM, MAIL_FROM_NAME
  - SUPPORT_EMAIL

Create:
- /lib/mailer.php
  - function app_send_mail($to, $subject, $htmlBody, $textBody=''): bool
  Use mail() for now with proper headers; return true/false.

Create:
- /lib/security.php
  - csrf helpers:
    - csrf_get_token()
    - csrf_field_html()
    - csrf_validate_or_die()
  - rate limit helpers:
    - login_lock_check($userRow)
    - login_fail_record($userId)
    - login_success_record($userId)
  - token helpers:
    - make_token_raw() using random_bytes(32) -> bin2hex
    - hash_token($raw) -> hash('sha256', $raw)

========================================================
3) AUTH HELPERS + ACCESS CONTROL
========================================================
Create:
- /lib/auth.php
Functions:
- require_login(): redirects to /auth/login.php?next=...
- current_user(): returns user row or null
- current_restaurant_id(): from session
- is_manager(): true if membership role=manager for current restaurant
- require_manager(): 403 page or redirect

Session structure:
- $_SESSION['user_id']
- $_SESSION['restaurant_id']
- regenerate session id on login

========================================================
4) AUTH PAGES (premium UI, mobile-first)
========================================================
Create folder: /auth/

Pages:
- /auth/login.php
  - email + password
  - link to forgot password
  - POST -> verify -> set session -> redirect next or /schedule/index.php
  - on too many fails -> lock account temporarily (e.g. 10 min)

- /auth/register.php  (Manager onboarding)
  - restaurant name
  - manager name
  - email
  - password
  - creates:
    - users row
    - restaurants row
    - user_restaurants row with role='manager'
    - logs in automatically
  - optional: set email_verified=1 (for MVP) but structure code so we can turn on verification later

- /auth/logout.php
  - destroys session, redirects login

- /auth/forgot.php
  - email input
  - always show generic success message (do not reveal if account exists)
  - if user exists:
    - create password_resets row with token_hash, expires_at (60 minutes)
    - email reset link: APP_URL . "/auth/reset.php?token=RAW"
  - rate-limit reset requests (per email/ip basic)

- /auth/reset.php
  - token in query
  - user sets new password
  - verify token:
    - hash token and match unused, unexpired record
  - update users.password_hash
  - set used_at on reset row
  - force logout other sessions by regenerating session id on next login (minimal)
  - redirect to login with success

- /auth/invite_accept.php
  - token in query
  - asks for name + password
  - creates user if not exists (or links if exists)
  - creates membership in user_restaurants with role from invite
  - marks invite accepted_at
  - logs user in and sets restaurant_id

UI requirements:
- Use existing schedule.css if present for consistent premium look.
- Forms: readable, big inputs, clear errors, no clutter.
- Must work on mobile.
- Include CSRF tokens in all POST forms.

========================================================
5) MANAGER TEAM MANAGEMENT (invites)
========================================================
Create:
- /team/index.php (manager-only)
Features:
- list current team members for restaurant
- invite form (email + role dropdown [manager/team])
- create invite:
  - token_raw + token_hash
  - store invite row with expires_at (7 days)
  - send email with accept link: /auth/invite_accept.php?token=RAW
- deactivate member (set user_restaurants.is_active=0)

Add link to this page in schedule nav for managers.

========================================================
6) INTEGRATE WITH SCHEDULE PAGES
========================================================
Update /schedule/*.php:
- include require_login() at top
- set $restaurantId = current_restaurant_id()
- use require_manager() for manager pages (roles, compliance, rules, integrations imports, etc.)
Do NOT change schedule logic besides replacing direct session checks with helper calls, and only if necessary.

========================================================
7) ERROR PAGES (clean)
========================================================
Create:
- /403.php and /404.php (simple premium UI)
Use them for forbidden or missing resources.

========================================================
8) VERIFICATION (REQUIRED)
========================================================
- php -l on all new/edited PHP files.
- Manual tests:
  1) Register manager -> creates restaurant -> redirects to schedule
  2) Logout/login works
  3) Forgot password sends email (or logs the link if mail disabled) and reset works
  4) Manager invites team -> team accepts -> logs in -> sees my schedule
  5) Team member cannot access manager pages (roles, publish, invites)
  6) Rate limiting: repeated wrong login locks temporarily

STOP CONDITION:
- Auth system works end-to-end.
- Roles (manager/team) enforced.
- Reset + invite flows work.
- Premium UI applied.
- No unrelated refactors.

```

## docs/codex_prompts/_notes/dwsync.xml

```
<?xml version="1.0" encoding="utf-8" ?>
<dwsync>
<file name="01_repo_scan_execplan.md" server="66.29.132.156/schedule/" local="134150665171775571" remote="134151781800000000" Dst="1" />
<file name="02_migrations.md" server="66.29.132.156/schedule/" local="134150665171785593" remote="134151781800000000" Dst="1" />
<file name="03_module_skeleton.md" server="66.29.132.156/schedule/" local="134150673606452149" remote="134151781800000000" Dst="1" />
<file name="04_mvp_parity.md" server="66.29.132.156/schedule/" local="134150699390988107" remote="134151781800000000" Dst="1" />
<file name="05_differentiators.md" server="66.29.132.156/schedule/" local="134150712461199564" remote="134151781800000000" Dst="1" />
<file name="06_pos_adapter_stub.md" server="66.29.132.156/schedule/" local="134150713537580671" remote="134151781800000000" Dst="1" />
<file name="08_verification.md" server="66.29.132.156/schedule/" local="134150714690773829" remote="134151781800000000" Dst="1" />
<file name="07_security_permissions.md.txt" server="66.29.132.156/schedule/" local="134150714207797072" remote="134151781800000000" Dst="1" />
<file name="09_Labor Rules Engine_ Compliance_Enforcement Signals.md" server="66.29.132.156/schedule/" local="134150717119681421" remote="134151781800000000" Dst="1" />
<file name="10_Security_Permissions.md" server="66.29.132.156/schedule/" local="134150718916337971" remote="134151781800000000" Dst="1" />
<file name="10_Security_Permissions.txt" server="66.29.132.156/schedule/" local="134150718412197143" remote="134151781800000000" Dst="1" />
<file name="11_Release_Packaging_Setup_Wizard.txt" server="66.29.132.156/schedule/" local="134150719732510913" remote="134151781800000000" Dst="1" />
<file name="12_Release_Packaging_Setup_Wizard.txt" server="66.29.132.156/schedule/" local="134150861662697588" remote="134151781800000000" Dst="1" />
</dwsync>
```

## docs/execplans/001_roles.sql

```
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_roles_restaurant_name (restaurant_id, name),
  KEY idx_roles_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/execplans/_notes/dwsync.xml

```
<?xml version="1.0" encoding="utf-8" ?>
<dwsync>
<file name="001_roles.sql" server="66.29.132.156/schedule/" local="134150665171975572" remote="134151781800000000" Dst="1" />
<file name="scheduling_execplan.md" server="66.29.132.156/schedule/" local="134150665171945587" remote="134151781800000000" Dst="1" />
</dwsync>
```

## docs/execplans/scheduling_execplan.md

```
# Scheduling Module Execution Plan

## 1) Repo inspection findings (current state)
- **Auth/session conventions:** No PHP source tree present in this repo; no session/auth files found.
- **DB connection conventions:** No `db.php`/PDO patterns found (no PHP files in repo).
- **Staff tables:** No schema or migrations present to identify staff-related tables.
- **UI includes:** No `header.php`, `nav.php`, or other include templates present.
- **Planner/tasks/incidents/audits tables:** No existing schema or migrations found.

> Note: The repository currently only contains documentation under `/docs`.

## 2) Planned file list to create under `/schedule`
(Exact paths may adjust once the main PHP app structure is confirmed.)
- `/schedule/index.php` (landing / schedule grid)
- `/schedule/week_view.php` (weekly schedule builder UI)
- `/schedule/requests.php` (availability, time off, shift swap)
- `/schedule/marketplace.php` (open shifts + pickup requests)
- `/schedule/quality.php` (schedule quality score + suggestions)
- `/schedule/announcements.php` (team messaging)
- `/schedule/partials/`
  - `header.php` (if app uses shared includes)
  - `nav.php`
  - `schedule_grid.php`
  - `shift_modal.php`

## 3) Planned migrations to add under `/migrations`
- `create_staff_skills.sql`
- `create_staff_pay_rates.sql`
- `create_shift_trade_requests.sql`
- `create_shift_pickup_requests.sql`
- `create_announcements.sql`
- `create_schedule_quality.sql`
- `create_schedule_shifts.sql` (if no existing shifts table)
- `create_schedule_availability.sql`
- `create_time_off_requests.sql`

## 4) Integration vs stub (POS adapters)
- **Integrate (first-pass):**
  - Data models + service interfaces for POS sync
  - Configuration-driven mapping for employee/role mapping
- **Stub (initially):**
  - Aloha actuals sync endpoints (returning placeholder data)
  - Schedule enforcement / early punch alerts (logged only)

## 5) Acceptance criteria checklist
- [ ] Weekly schedule builder with templates scoped by restaurant_id
- [ ] Shift swaps with manager approval
- [ ] Availability + time off requests
- [ ] Team messaging + announcements
- [ ] Notifications (in-app; push/SMS optional)
- [ ] Forecasting support + labor cost tracking placeholders
- [ ] Compliance warnings (breaks, max hours, minors)
- [ ] Time & attendance (punches/edit alerts) OR POS actuals import
- [ ] Schedule Quality Score with reasons + fix suggestions
- [ ] Shift Marketplace (open shifts + ranked pickup requests)
- [ ] HospiEdge triggers (incidents/temps/audit failures -> staffing tasks)
- [ ] CSRF protection on mutating actions

## 6) Verification plan
- **Static checks:**
  - `php -l` on all new/updated PHP files
- **Manual test steps:**
  1. Log in as manager and create a weekly schedule template.
  2. Publish schedule and verify staff can view week view.
  3. Submit shift trade request and approve as manager.
  4. Submit time-off request; verify schedule updates warnings.
  5. Post announcement and verify staff visibility.
  6. View quality score and suggestions for a week.
  7. Trigger marketplace open shift and verify pickup request flow.
  8. Validate planner/task creation from audit/incident inputs.

```

## docs/migrations/001_roles.sql

```
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_roles_restaurant_name (restaurant_id, name),
  KEY idx_roles_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/002_staff_availability.sql

```
CREATE TABLE IF NOT EXISTS staff_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  day_of_week TINYINT NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status VARCHAR(20) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_availability_restaurant (restaurant_id),
  KEY idx_staff_availability_restaurant_staff_day (restaurant_id, staff_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/003_shifts.sql

```
CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NULL,
  role_id INT NULL,
  start_dt DATETIME NOT NULL,
  end_dt DATETIME NOT NULL,
  break_minutes INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  status ENUM('draft','published','deleted') NOT NULL DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_shifts_restaurant_start (restaurant_id, start_dt),
  KEY idx_shifts_restaurant_staff_start (restaurant_id, staff_id, start_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/004_time_off_requests.sql

```
CREATE TABLE IF NOT EXISTS time_off_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  start_dt DATETIME NOT NULL,
  end_dt DATETIME NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_time_off_requests_restaurant_staff_start (restaurant_id, staff_id, start_dt),
  KEY idx_time_off_requests_restaurant_status (restaurant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/005_staff_skills.sql

```
CREATE TABLE IF NOT EXISTS staff_skills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  skill_key VARCHAR(100) NOT NULL,
  level TINYINT NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_skills_restaurant (restaurant_id),
  KEY idx_staff_skills_restaurant_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/006_staff_pay_rates.sql

```
CREATE TABLE IF NOT EXISTS staff_pay_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  role_id INT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_pay_rates_restaurant (restaurant_id),
  KEY idx_staff_pay_rates_restaurant_staff_from (restaurant_id, staff_id, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/008_shift_pickup_requests.sql

```
CREATE TABLE IF NOT EXISTS shift_pickup_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  staff_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_shift_pickup_requests_restaurant (restaurant_id),
  KEY idx_shift_pickup_requests_restaurant_shift (restaurant_id, shift_id),
  KEY idx_shift_pickup_requests_restaurant_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/009_announcements.sql

```
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  audience VARCHAR(100) NOT NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_announcements_restaurant (restaurant_id),
  KEY idx_announcements_restaurant_starts (restaurant_id, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/010_schedule_quality.sql

```
CREATE TABLE IF NOT EXISTS schedule_quality (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  week_start_date DATE NOT NULL,
  score INT NOT NULL,
  reasons_json TEXT NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  generated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_schedule_quality_restaurant_week (restaurant_id, week_start_date),
  KEY idx_schedule_quality_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/011_pos_connections.sql

```
CREATE TABLE IF NOT EXISTS pos_connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  credentials_json TEXT NOT NULL,
  last_sync_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pos_connections_restaurant_provider (restaurant_id, provider),
  KEY idx_pos_connections_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/012_pos_mappings.sql

```
CREATE TABLE IF NOT EXISTS pos_mappings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL,
  external_id VARCHAR(100) NOT NULL,
  internal_id VARCHAR(100) NOT NULL,
  type VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pos_mappings_restaurant_provider_type_external (restaurant_id, provider, type, external_id),
  KEY idx_pos_mappings_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/013_aloha_import_batches.sql

```
CREATE TABLE IF NOT EXISTS aloha_import_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'aloha',
  import_type ENUM('employees','labor','sales') NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  status ENUM('uploaded','mapped','processed','failed') NOT NULL DEFAULT 'uploaded',
  mapping_json TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  error_text TEXT NULL,
  KEY idx_aloha_batches_restaurant (restaurant_id),
  KEY idx_aloha_batches_restaurant_type (restaurant_id, import_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/014_aloha_employees_stage.sql

```
CREATE TABLE IF NOT EXISTS aloha_employees_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  external_employee_id VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  display_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  raw_json TEXT NULL,
  KEY idx_aloha_employees_batch (restaurant_id, batch_id),
  KEY idx_aloha_employees_external (restaurant_id, external_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/015_aloha_labor_punches_stage.sql

```
CREATE TABLE IF NOT EXISTS aloha_labor_punches_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  external_employee_id VARCHAR(100) NOT NULL,
  punch_in_dt DATETIME NOT NULL,
  punch_out_dt DATETIME NULL,
  job_code VARCHAR(100) NULL,
  location_code VARCHAR(100) NULL,
  raw_json TEXT NULL,
  KEY idx_aloha_labor_batch (restaurant_id, batch_id),
  KEY idx_aloha_labor_employee (restaurant_id, external_employee_id, punch_in_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/016_aloha_sales_daily_stage.sql

```
CREATE TABLE IF NOT EXISTS aloha_sales_daily_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  business_date DATE NOT NULL,
  gross_sales DECIMAL(12,2) NOT NULL,
  net_sales DECIMAL(12,2) NULL,
  orders_count INT NULL,
  raw_json TEXT NULL,
  UNIQUE KEY uniq_aloha_sales_day (restaurant_id, business_date),
  KEY idx_aloha_sales_day (restaurant_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/017_job_logs.sql

```
CREATE TABLE IF NOT EXISTS job_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NULL,
  job_type VARCHAR(64) NOT NULL,
  payload_json TEXT NOT NULL,
  status ENUM('queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
  priority INT NOT NULL DEFAULT 100,
  run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 5,
  last_error TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  KEY idx_job_queue_status_run (status, run_after, priority),
  KEY idx_job_queue_restaurant_status (restaurant_id, status, run_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/018_job_logs.sql

```
CREATE TABLE IF NOT EXISTS job_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  log_level ENUM('info','warn','error') NOT NULL DEFAULT 'info',
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_job_logs_job_created (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/019_job_logs.sql

```
CREATE TABLE IF NOT EXISTS job_locks (
  lock_key VARCHAR(128) NOT NULL PRIMARY KEY,
  locked_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  owner VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/020_job_logs.sql

```
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  user_id INT NOT NULL,
  type VARCHAR(64) NOT NULL,
  title VARCHAR(140) NOT NULL,
  body TEXT NOT NULL,
  link_url VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_restaurant_user (restaurant_id, user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/021_job_logs.sql

```
CREATE TABLE IF NOT EXISTS shift_trade_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  from_staff_id INT NOT NULL,
  to_staff_id INT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_shift_trade_requests_restaurant (restaurant_id),
  KEY idx_shift_trade_requests_restaurant_shift (restaurant_id, shift_id),
  KEY idx_shift_trade_requests_restaurant_status (restaurant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/022_job_logs.sql

```
CREATE TABLE IF NOT EXISTS callouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  staff_id INT NOT NULL,
  reason TEXT NULL,
  status ENUM('reported','coverage_requested','covered','manager_closed') NOT NULL DEFAULT 'reported',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_callouts_restaurant (restaurant_id),
  KEY idx_callouts_restaurant_shift (restaurant_id, shift_id),
  KEY idx_callouts_restaurant_status (restaurant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## docs/migrations/023_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  policy_set_id INT NOT NULL,
  policy_key VARCHAR(64) NOT NULL,
  enabled TINYINT NOT NULL DEFAULT 1,
  mode ENUM('warn','block') NOT NULL DEFAULT 'warn',
  params_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_policies_restaurant_set (restaurant_id, policy_set_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/024_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_policy_sets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  is_default TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_schedule_policy_sets_restaurant_name (restaurant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/025_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_violations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  week_start_date DATE NOT NULL,
  shift_id INT NULL,
  staff_id INT NULL,
  policy_key VARCHAR(64) NOT NULL,
  severity ENUM('info','warn','block') NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_violations_week (restaurant_id, week_start_date),
  KEY idx_schedule_violations_staff_week (restaurant_id, staff_id, week_start_date),
  KEY idx_schedule_violations_policy_week (restaurant_id, policy_key, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## docs/migrations/026_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_enforcement_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  staff_id INT NULL,
  shift_id INT NULL,
  external_employee_id VARCHAR(64) NULL,
  event_dt DATETIME NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_enforcement_type_dt (restaurant_id, event_type, event_dt),
  KEY idx_schedule_enforcement_staff_dt (restaurant_id, staff_id, event_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## docs/migrations/027_job_logs.sql

```
CREATE TABLE IF NOT EXISTS staff_labor_profile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  is_minor TINYINT NOT NULL DEFAULT 0,
  birthdate DATE NULL,
  max_weekly_hours_override DECIMAL(5,2) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_staff_labor_profile_restaurant_staff (restaurant_id, staff_id),
  KEY idx_staff_labor_profile_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/028_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  policy_set_id INT NOT NULL,
  policy_key VARCHAR(64) NOT NULL,
  enabled TINYINT NOT NULL DEFAULT 1,
  mode ENUM('warn','block') NOT NULL DEFAULT 'warn',
  params_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_policies_restaurant_set (restaurant_id, policy_set_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/029_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_policy_sets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  is_default TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_schedule_policy_sets_restaurant_name (restaurant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/030_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_violations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  week_start_date DATE NOT NULL,
  shift_id INT NULL,
  staff_id INT NULL,
  policy_key VARCHAR(64) NOT NULL,
  severity ENUM('info','warn','block') NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_violations_week (restaurant_id, week_start_date),
  KEY idx_schedule_violations_staff_week (restaurant_id, staff_id, week_start_date),
  KEY idx_schedule_violations_policy_week (restaurant_id, policy_key, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/031_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_enforcement_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  staff_id INT NULL,
  shift_id INT NULL,
  external_employee_id VARCHAR(64) NULL,
  event_dt DATETIME NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_enforcement_type_dt (restaurant_id, event_type, event_dt),
  KEY idx_schedule_enforcement_staff_dt (restaurant_id, staff_id, event_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/032_job_logs.sql

```
CREATE TABLE IF NOT EXISTS staff_labor_profile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  is_minor TINYINT NOT NULL DEFAULT 0,
  birthdate DATE NULL,
  max_weekly_hours_override DECIMAL(5,2) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_staff_labor_profile_restaurant_staff (restaurant_id, staff_id),
  KEY idx_staff_labor_profile_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## docs/migrations/033_job_logs.sql

```
CREATE TABLE IF NOT EXISTS schedule_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL UNIQUE,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
  demo_mode TINYINT(1) NOT NULL DEFAULT 0,
  last_worker_run_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## docs/migrations/034_job_logs.sql

```
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  failed_logins INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## docs/migrations/035_job_logs.sql

```
CREATE TABLE IF NOT EXISTS restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_restaurants_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('manager','team') NOT NULL DEFAULT 'team',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_restaurant_user (restaurant_id, user_id),
  INDEX (user_id),
  INDEX (restaurant_id),
  CONSTRAINT fk_user_restaurants_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id),
  CONSTRAINT fk_user_restaurants_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## docs/migrations/036_job_logs.sql

```
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  request_ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  INDEX (user_id),
  INDEX (expires_at),
  INDEX (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## docs/migrations/037_job_logs.sql

```
CREATE TABLE IF NOT EXISTS invites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  role ENUM('manager','team') NOT NULL DEFAULT 'team',
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_invite_email (restaurant_id, email, accepted_at),
  INDEX (restaurant_id),
  INDEX (email),
  INDEX (token_hash),
  CONSTRAINT fk_invites_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id),
  CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## docs/migrations/09_codex_review.md.txt

```
09_codex_review.md
```

## docs/migrations/_notes/dwsync.xml

```
<?xml version="1.0" encoding="utf-8" ?>
<dwsync>
<file name="001_roles.sql" server="66.29.132.156/schedule/" local="134150670177100527" remote="134151781800000000" Dst="1" />
<file name="002_staff_availability.sql" server="66.29.132.156/schedule/" local="134150665171985581" remote="134151781800000000" Dst="1" />
<file name="003_shifts.sql" server="66.29.132.156/schedule/" local="134150665172005613" remote="134151781800000000" Dst="1" />
<file name="004_time_off_requests.sql" server="66.29.132.156/schedule/" local="134150665172025604" remote="134151781800000000" Dst="1" />
<file name="005_staff_skills.sql" server="66.29.132.156/schedule/" local="134150665300598757" remote="134151781800000000" Dst="1" />
<file name="006_staff_pay_rates.sql" server="66.29.132.156/schedule/" local="134150666136535844" remote="134151781800000000" Dst="1" />
<file name="008_shift_pickup_requests.sql" server="66.29.132.156/schedule/" local="134150666839536929" remote="134151781800000000" Dst="1" />
<file name="009_announcements.sql" server="66.29.132.156/schedule/" local="134150667458677614" remote="134151781800000000" Dst="1" />
<file name="010_schedule_quality.sql" server="66.29.132.156/schedule/" local="134150668183821928" remote="134151781200000000" Dst="1" />
<file name="011_pos_connections.sql" server="66.29.132.156/schedule/" local="134150668791526670" remote="134151781200000000" Dst="1" />
<file name="012_pos_mappings.sql" server="66.29.132.156/schedule/" local="134150669412645157" remote="134151781200000000" Dst="1" />
<file name="013_aloha_import_batches.sql" server="66.29.132.156/schedule/" local="134150738669589835" remote="134151781200000000" Dst="1" />
<file name="014_aloha_employees_stage.sql" server="66.29.132.156/schedule/" local="134150739554289659" remote="134151781200000000" Dst="1" />
<file name="015_aloha_labor_punches_stage.sql" server="66.29.132.156/schedule/" local="134150740898377068" remote="134151781200000000" Dst="1" />
<file name="016_aloha_sales_daily_stage.sql" server="66.29.132.156/schedule/" local="134150741410181770" remote="134151781200000000" Dst="1" />
<file name="017_job_logs.sql" server="66.29.132.156/schedule/" local="134150777621804909" remote="134151781200000000" Dst="1" />
<file name="018_job_logs.sql" server="66.29.132.156/schedule/" local="134150777243065387" remote="134151781200000000" Dst="1" />
<file name="019_job_logs.sql" server="66.29.132.156/schedule/" local="134150778166139488" remote="134151781200000000" Dst="1" />
<file name="020_job_logs.sql" server="66.29.132.156/schedule/" local="134150798802976696" remote="134151781200000000" Dst="1" />
<file name="021_job_logs.sql" server="66.29.132.156/schedule/" local="134150799881909568" remote="134151781200000000" Dst="1" />
<file name="022_job_logs.sql" server="66.29.132.156/schedule/" local="134150800472881821" remote="134151781200000000" Dst="1" />
<file name="023_job_logs.sql" server="66.29.132.156/schedule/" local="134150831428766925" remote="134151781200000000" Dst="1" />
<file name="024_job_logs.sql" server="66.29.132.156/schedule/" local="134150831896123194" remote="134151781200000000" Dst="1" />
<file name="025_job_logs.sql" server="66.29.132.156/schedule/" local="134150832411404572" remote="134151781200000000" Dst="1" />
<file name="026_job_logs.sql" server="66.29.132.156/schedule/" local="134150832941212302" remote="134151781200000000" Dst="1" />
<file name="027_job_logs.sql" server="66.29.132.156/schedule/" local="134150833414478527" remote="134151781200000000" Dst="1" />
<file name="028_job_logs.sql" server="66.29.132.156/schedule/" local="134150835972891111" remote="134151781200000000" Dst="1" />
<file name="029_job_logs.sql" server="66.29.132.156/schedule/" local="134150836394283889" remote="134151781200000000" Dst="1" />
<file name="030_job_logs.sql" server="66.29.132.156/schedule/" local="134150836819501195" remote="134151781200000000" Dst="1" />
<file name="032_job_logs.sql" server="66.29.132.156/schedule/" local="134150837866699663" remote="134151781200000000" Dst="1" />
<file name="031_job_logs.sql" server="66.29.132.156/schedule/" local="134150837406674038" remote="134151781200000000" Dst="1" />
<file name="033_job_logs.sql" server="66.29.132.156/schedule/" local="134150853467174926" remote="134151781200000000" Dst="1" />
<file name="034_job_logs.sql" server="66.29.132.156/schedule/" local="134150895833236979" remote="134151781200000000" Dst="1" />
<file name="035_job_logs.sql" server="66.29.132.156/schedule/" local="134150896486524853" remote="134151781200000000" Dst="1" />
<file name="036_job_logs.sql" server="66.29.132.156/schedule/" local="134150897119100128" remote="134151781200000000" Dst="1" />
<file name="037_job_logs.sql" server="66.29.132.156/schedule/" local="134150897940683882" remote="134151781200000000" Dst="1" />
<file name="09_codex_review.md.txt" server="66.29.132.156/schedule/" local="134150665401039479" remote="134151781200000000" Dst="1" />
</dwsync>
```

## docs/scheduling_spec_competitive.md

```
# Scheduling Module — Competitive Spec (Beat HotSchedules)

GOAL:
Build scheduling that matches HotSchedules / 7shifts / Homebase / When I Work / Deputy parity
AND adds HospiEdge-only intelligence (audits/incidents/temps -> staffing actions).

NON-NEGOTIABLE PARITY:
- Weekly schedule builder with templates
- Shift swaps/transactions w/ manager approval
- Availability + time off requests
- Team messaging + announcements
- Notifications (in-app + push; optional SMS)
- Forecasting support + labor cost tracking
- Compliance warnings (break rules, max hours, minors)
- Time & attendance (punches, edits, irregularity alerts) OR import actuals from POS

DIFFERENTIATORS (REQUIRED):
1) Schedule Quality Score (0-100) + reasons:
   - Coverage vs forecast (daypart)
   - Skill match / cert coverage
   - Overtime risk + clopen risk
   - Compliance risk
   Include “Fix Suggestions” and optionally “Apply Fix” actions.

2) Shift Marketplace:
   - Open shifts board
   - Pickup requests
   - Auto-ranked candidates (availability, skills, OT risk)
   - Broadcast coverage (push/SMS) to filtered staff

3) HospiEdge Triggers:
   - Incidents/temps/audit failures generate staffing recommendations + planner tasks:
     - call extra coverage
     - schedule follow-up audits
     - assign training tasks

4) POS (Aloha) Support:
   - Employee + role mapping
   - Sales + labor actuals sync (planned vs actual)
   - Optional: schedule enforcement / early punch alerts

DATA MODEL (ADD TO MVP):
- staff_skills (staff_id, skill_key, level, expires_at)
- staff_pay_rates (staff_id, role_id, hourly_rate)
- shift_trade_requests (shift_id, from_staff_id, to_staff_id, status)
- shift_pickup_requests (shift_id, staff_id, status)
- announcements (restaurant_id, title, body, audience, created_at)
- schedule_quality (week_start, score, json_reasons)

DONE = parity + differentiators working end-to-end, scoped by restaurant_id, CSRF-protected.

```
