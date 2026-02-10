CREATE TABLE IF NOT EXISTS aloha_employees_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  external_employee_id VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  display_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  raw_json TEXT NULL,
  KEY idx_aloha_employees_batch (restaurant_id, batch_id),
  KEY idx_aloha_employees_external (restaurant_id, external_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;