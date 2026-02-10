CREATE TABLE IF NOT EXISTS shift_pickup_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  shift_id INT NOT NULL,
  staff_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_shift_pickup_requests_restaurant (restaurant_id),
  KEY idx_shift_pickup_requests_restaurant_shift (restaurant_id, shift_id),
  KEY idx_shift_pickup_requests_restaurant_staff (restaurant_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;