ALTER TABLE campaigns ADD target_countries VARCHAR(60) NOT NULL DEFAULT 'ID' AFTER cap_per_account;

CREATE TABLE short_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL UNIQUE,
  code CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  link_verified_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  lost_at DATETIME NULL,
  CONSTRAINT fk_link_account FOREIGN KEY (account_id) REFERENCES threads_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cookie_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_devices (
  user_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  last_login_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, device_id),
  INDEX (device_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE click_tokens (
  nonce CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clicks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  short_link_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  subnet_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  trait_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  country CHAR(2) NULL,
  device_type VARCHAR(10) NOT NULL,
  risk_score SMALLINT UNSIGNED NOT NULL,
  status ENUM('valid','held','invalid') NOT NULL,
  reasons_json JSON NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  INDEX (short_link_id, created_at),
  INDEX (short_link_id, campaign_id, status),
  INDEX (device_id, created_at),
  INDEX (created_at),
  CONSTRAINT fk_click_link FOREIGN KEY (short_link_id) REFERENCES short_links(id),
  CONSTRAINT fk_click_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_click_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE valid_click_keys (
  short_link_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (short_link_id, campaign_id, device_id),
  CONSTRAINT fk_key_link FOREIGN KEY (short_link_id) REFERENCES short_links(id),
  CONSTRAINT fk_key_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_key_device FOREIGN KEY (device_id) REFERENCES devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
