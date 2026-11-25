CREATE TABLE campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  brief TEXT NOT NULL,
  destination_url VARCHAR(500) NOT NULL,
  rules_json JSON NOT NULL,
  rate_per_1k BIGINT UNSIGNED NOT NULL,
  cap_per_account BIGINT UNSIGNED NOT NULL,
  budget BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE participations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  rate_snapshot BIGINT UNSIGNED NOT NULL,
  cap_snapshot BIGINT UNSIGNED NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_participation (account_id, campaign_id),
  CONSTRAINT fk_participation_account FOREIGN KEY (account_id) REFERENCES threads_accounts(id),
  CONSTRAINT fk_participation_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participation_id BIGINT UNSIGNED NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL UNIQUE,
  post_code VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  excerpt VARCHAR(300) NOT NULL DEFAULT '',
  posted_at DATETIME NOT NULL,
  views BIGINT UNSIGNED NULL,
  likes BIGINT UNSIGNED NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(300) NULL,
  last_checked_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_submission_participation FOREIGN KEY (participation_id) REFERENCES participations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
