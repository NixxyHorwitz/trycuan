ALTER TABLE participations ADD trust_status ENUM('review','rejected') NOT NULL DEFAULT 'review';

CREATE TABLE fee_holds (
  participation_id BIGINT UNSIGNED NOT NULL,
  level BIGINT UNSIGNED NOT NULL,
  reached_at DATETIME NOT NULL,
  PRIMARY KEY (participation_id, level),
  CONSTRAINT fk_hold_participation FOREIGN KEY (participation_id) REFERENCES participations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fee_holds (participation_id, level, reached_at) SELECT id, fee_total, NOW() FROM participations WHERE fee_total > 0;

CREATE TABLE payout_methods (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  provider VARCHAR(20) NOT NULL,
  account_name VARCHAR(80) NOT NULL,
  account_enc VARBINARY(255) NOT NULL,
  account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (account_hash),
  CONSTRAINT fk_method_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE withdrawals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  request_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL,
  account_name VARCHAR(80) NOT NULL,
  account_enc VARBINARY(255) NOT NULL,
  account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  reason VARCHAR(300) NULL,
  proof_file VARCHAR(60) NULL,
  processed_by BIGINT UNSIGNED NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_request (user_id, request_token),
  INDEX (status),
  INDEX (account_hash),
  CONSTRAINT fk_withdrawal_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_withdrawal_admin FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type ENUM('fee','withdrawal','refund') NOT NULL,
  amount BIGINT NOT NULL,
  participation_id BIGINT UNSIGNED NULL,
  withdrawal_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, id),
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ledger_participation FOREIGN KEY (participation_id) REFERENCES participations(id),
  CONSTRAINT fk_ledger_withdrawal FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
