CREATE TABLE view_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id BIGINT UNSIGNED NOT NULL,
  claimed_views BIGINT UNSIGNED NOT NULL,
  approved_views BIGINT UNSIGNED NULL,
  proof_file VARCHAR(40) CHARACTER SET ascii NOT NULL,
  proof_hash CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reason VARCHAR(300) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, id),
  CONSTRAINT fk_report_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE submissions ADD view_source ENUM('threads','insight') NOT NULL DEFAULT 'threads';

ALTER TABLE view_snapshots ADD report_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_snapshot_report FOREIGN KEY (report_id) REFERENCES view_reports(id) ON DELETE CASCADE;
