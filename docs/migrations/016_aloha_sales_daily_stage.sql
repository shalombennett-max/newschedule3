CREATE TABLE IF NOT EXISTS aloha_sales_daily_stage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  batch_id INT NOT NULL,
  business_date DATE NOT NULL,
  gross_sales DECIMAL(12,2) NOT NULL,
  net_sales DECIMAL(12,2) NULL,
  orders_count INT NULL,
  raw_json TEXT NULL,
  UNIQUE KEY uniq_aloha_sales_day (restaurant_id, business_date),
  KEY idx_aloha_sales_day (restaurant_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;