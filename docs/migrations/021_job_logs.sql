CREATE TABLE IF NOT EXISTS shift_trade_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  from_staff_id INT NOT NULL,
  to_staff_id INT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_shift_trade_requests_restaurant (restaurant_id),
  KEY idx_shift_trade_requests_restaurant_shift (restaurant_id, shift_id),
  KEY idx_shift_trade_requests_restaurant_status (restaurant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;