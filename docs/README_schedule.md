## Migrations

Run migrations in this order:
1. `migrations/001_roles.sql`
2. `migrations/002_staff_availability.sql`
3. `migrations/003_shifts.sql`
4. `migrations/004_time_off_requests.sql`
5. `migrations/005_staff_skills.sql`
6. `migrations/006_staff_pay_rates.sql`
7. `migrations/007_shift_trade_requests.sql`
8. `migrations/008_shift_pickup_requests.sql`
9. `migrations/009_announcements.sql`
10. `migrations/010_schedule_quality.sql`
11. `migrations/011_pos_connections.sql`
12. `migrations/012_pos_mappings.sql`

### Running in phpMyAdmin
- Open each SQL file in order and use the SQL tab to execute its contents.

### Running via CLI
- If you have a migration runner, add these files to its execution list in the order above.
- Without a runner, you can use the MySQL client and copy/paste each file:
  - `mysql -u <user> -p <database> < migrations/001_roles.sql`

## Empty-table safety
The Scheduling module must not crash when these tables are empty; all features should handle zero rows gracefully.

## Optional planner/task integration
On publish, the schedule module will attempt to create follow-up tasks if a compatible planner/task table exists.
It looks for either a `planner_tasks` or `tasks` table with at minimum `restaurant_id` and `title` (or `name`) columns.
If the table or columns are missing, it safely skips task creation. This integration never creates new tables.