You are Codex working in this repository.

READ FIRST:
- Follow AGENTS.md strictly.
- Read docs/scheduling_spec.md and docs/scheduling_spec_competitive.md (if present).

TASK (NO FEATURE CODE YET):
1) Inspect the repo structure and identify:
   - auth/session conventions (user_id, res_id, any role/permission fields)
   - db connection conventions (db.php, PDO patterns)
   - existing staff table(s) and fields (staff_members or similar)
   - existing UI includes (header.php, nav.php)
   - any existing planner/tasks/incidents/audits tables that we can hook into later

2) Create an execution plan file:
   - Create /docs/execplans/scheduling_execplan.md
   - Include:
     a) file list you will create under /schedule
     b) migrations you will add under /migrations
     c) what you will integrate vs stub (POS adapters)
     d) acceptance criteria checklist
     e) how you will verify (php -l, manual test steps)

3) Output:
   - Only add /docs/execplans/scheduling_execplan.md
   - Do NOT modify any PHP files in this step.
Stop after the plan file is created.
