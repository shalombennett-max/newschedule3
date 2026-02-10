CREATE TABLE IF NOT EXISTS pos_mappings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL,
  external_id VARCHAR(100) NOT NULL,
  internal_id VARCHAR(100) NOT NULL,
  type VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pos_mappings_restaurant_provider_type_external (restaurant_id, provider, type, external_id),
  KEY idx_pos_mappings_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;