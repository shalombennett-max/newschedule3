CREATE TABLE IF NOT EXISTS invites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  role ENUM('manager','team') NOT NULL DEFAULT 'team',
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_invite_email (restaurant_id, email, accepted_at),
  INDEX (restaurant_id),
  INDEX (email),
  INDEX (token_hash),
  CONSTRAINT fk_invites_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id),
  CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;