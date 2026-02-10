CREATE TABLE IF NOT EXISTS schedule_violations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  week_start_date DATE NOT NULL,
  shift_id INT NULL,
  staff_id INT NULL,
  policy_key VARCHAR(64) NOT NULL,
  severity ENUM('info','warn','block') NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_violations_week (restaurant_id, week_start_date),
  KEY idx_schedule_violations_staff_week (restaurant_id, staff_id, week_start_date),
  KEY idx_schedule_violations_policy_week (restaurant_id, policy_key, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;