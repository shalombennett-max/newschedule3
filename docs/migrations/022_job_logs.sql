CREATE TABLE IF NOT EXISTS callouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  staff_id INT NOT NULL,
  reason TEXT NULL,
  status ENUM('reported','coverage_requested','covered','manager_closed') NOT NULL DEFAULT 'reported',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_callouts_restaurant (restaurant_id),
  KEY idx_callouts_restaurant_shift (restaurant_id, shift_id),
  KEY idx_callouts_restaurant_status (restaurant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
