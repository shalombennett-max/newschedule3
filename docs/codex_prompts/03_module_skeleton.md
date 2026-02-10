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
