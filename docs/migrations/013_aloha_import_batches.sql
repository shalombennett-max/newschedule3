CREATE TABLE IF NOT EXISTS aloha_import_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'aloha',
  import_type ENUM('employees','labor','sales') NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  status ENUM('uploaded','mapped','processed','failed') NOT NULL DEFAULT 'uploaded',
  mapping_json TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  error_text TEXT NULL,
  KEY idx_aloha_batches_restaurant (restaurant_id),
  KEY idx_aloha_batches_restaurant_type (restaurant_id, import_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;