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
