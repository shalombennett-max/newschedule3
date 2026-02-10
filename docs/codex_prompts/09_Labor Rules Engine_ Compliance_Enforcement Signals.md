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
