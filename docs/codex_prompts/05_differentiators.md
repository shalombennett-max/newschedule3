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
