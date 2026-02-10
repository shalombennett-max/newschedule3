You are Codex working in this repo.

READ FIRST:
- Follow AGENTS.md strictly (no refactors, no removing features, smallest changes only).
- Implement docs/scheduling_spec.md AND docs/scheduling_spec_competitive.md.

TASK:
Build the Scheduling module to exceed HotSchedules-quality:
- Full parity features (availability, time off, swaps, messaging, forecasting hooks, compliance warnings, publish flow)
- PLUS differentiators:
  - Schedule Quality Score + fix suggestions
  - Shift Marketplace (open shifts + ranked pickup)
  - HospiEdge triggers (incidents/temps/audits -> staffing actions + planner tasks)
  - POS adapter pattern with Aloha-ready mapping + actual labor import (Aloha integration can be stubbed with tables + adapter interface if credentials are not present yet)

DELIVERABLES:
- /schedule pages + /schedule/api.php JSON actions
- /migrations SQL for new tables (including competitive tables)
- /docs/README_schedule.md describing setup and manual testing
- Must not crash with empty DB
- Prepared statements only, restaurant scoping everywhere, CSRF for POST

WORKFLOW:
1) Inspect existing repo conventions (db.php, header/nav, session fields).
2) Create migrations first.
3) Implement scheduling parity features.
4) Implement quality scoring + marketplace.
5) Wire triggers into existing incidents/audits/tasks tables if present; if not, create a minimal integration layer without breaking anything.
6) Summarize changes and how to test.
