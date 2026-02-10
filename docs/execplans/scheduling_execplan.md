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
