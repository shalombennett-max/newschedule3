CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  staff_id INT NULL,
  role_id INT NULL,
  start_dt DATETIME NOT NULL,
  end_dt DATETIME NOT NULL,
  break_minutes INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  status ENUM('draft','published','deleted') NOT NULL DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_shifts_restaurant_start (restaurant_id, start_dt),
  KEY idx_shifts_restaurant_staff_start (restaurant_id, staff_id, start_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;