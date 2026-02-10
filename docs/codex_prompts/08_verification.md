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
