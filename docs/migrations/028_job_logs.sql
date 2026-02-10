CREATE TABLE IF NOT EXISTS schedule_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  policy_set_id INT NOT NULL,
  policy_key VARCHAR(64) NOT NULL,
  enabled TINYINT NOT NULL DEFAULT 1,
  mode ENUM('warn','block') NOT NULL DEFAULT 'warn',
  params_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_schedule_policies_restaurant_set (restaurant_id, policy_set_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;