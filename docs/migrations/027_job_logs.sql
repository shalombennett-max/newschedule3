CREATE TABLE IF NOT EXISTS staff_labor_profile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NOT NULL,
  is_minor TINYINT NOT NULL DEFAULT 0,
  birthdate DATE NULL,
  max_weekly_hours_override DECIMAL(5,2) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_staff_labor_profile_restaurant_staff (restaurant_id, staff_id),
  KEY idx_staff_labor_profile_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;