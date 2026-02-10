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
