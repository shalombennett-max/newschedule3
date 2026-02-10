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
