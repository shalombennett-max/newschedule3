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
