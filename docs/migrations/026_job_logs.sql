CREATE TABLE IF NOT EXISTS schedule_enforcement_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  staff_id INT NULL,
  shift_id INT NULL,
  external_employee_id VARCHAR(64) NULL,
  event_dt DATETIME NOT NULL,
  message VARCHAR(255) NOT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_enforcement_type_dt (restaurant_id, event_type, event_dt),
  KEY idx_schedule_enforcement_staff_dt (restaurant_id, staff_id, event_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
