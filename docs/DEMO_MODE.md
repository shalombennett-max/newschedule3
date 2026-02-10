# Demo Mode (Scheduling)

## Overview
Demo mode is scoped per restaurant and is stored in `schedule_settings.demo_mode`. When enabled, you can safely generate sample scheduling data for walkthroughs without touching production data for other restaurants.

## What the seed script creates
The demo seed script generates data only when the required tables exist:
- Default roles (if missing)
- Two weeks of sample shifts (with open shifts)
- 1–2 time-off requests (when real staff records exist)
- 1 announcement
- 1 callout and 1 pending pickup request (when the tables exist)
- Demo-only staff stored in `schedule_demo_staff` (for reference)

All demo rows are tagged in `schedule_demo_tags` so they can be safely deleted later.

## CLI usage
Run from the repo root:

```bash
php scripts/seed_schedule_demo.php --restaurant=<restaurant_id>
```

Optional flags:
- `--reset` (delete demo-tagged rows only)
- `--user=<user_id>` (used for `created_by` fields when seeding)

Example reset:

```bash
php scripts/seed_schedule_demo.php --restaurant=<restaurant_id> --reset
```

## Web usage (manager only)
When logged in as a manager, you can seed or reset demo data from:

- `/schedule/setup.php` → **Demo mode** section

The script validates CSRF tokens and only runs for the current restaurant.

## Safety notes
- Demo data is tagged per restaurant.
- Reset deletes only demo-tagged rows; it does **not** delete existing production data.
- If the staff table schema is unknown, demo staff are stored in `schedule_demo_staff` and no inserts are made into core staff tables.
