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
