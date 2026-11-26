ALTER TABLE participations
  ADD total_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ADD fee_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ADD fee_credited BIGINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE submissions
  ADD last_attempt_at DATETIME NULL,
  ADD last_error VARCHAR(160) NULL,
  ADD missing_at DATETIME NULL,
  ADD reviewed_by BIGINT UNSIGNED NULL,
  ADD reviewed_at DATETIME NULL,
  ADD INDEX (status),
  ADD CONSTRAINT fk_submission_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE view_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id BIGINT UNSIGNED NOT NULL,
  views BIGINT UNSIGNED NOT NULL,
  likes BIGINT UNSIGNED NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (submission_id, checked_at),
  CONSTRAINT fk_snapshot_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO view_snapshots (submission_id, views, likes, checked_at)
  SELECT id, views, likes, last_checked_at FROM submissions WHERE views IS NOT NULL;
