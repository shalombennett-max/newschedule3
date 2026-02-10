CREATE TABLE IF NOT EXISTS aloha_labor_punches_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  external_employee_id VARCHAR(100) NOT NULL,
  punch_in_dt DATETIME NOT NULL,
  punch_out_dt DATETIME NULL,
  job_code VARCHAR(100) NULL,
  location_code VARCHAR(100) NULL,
  raw_json TEXT NULL,
  KEY idx_aloha_labor_batch (restaurant_id, batch_id),
  KEY idx_aloha_labor_employee (restaurant_id, external_employee_id, punch_in_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;