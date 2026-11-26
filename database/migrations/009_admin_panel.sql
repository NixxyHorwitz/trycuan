CREATE TABLE announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(2000) NOT NULL,
  level ENUM('info','penting') NOT NULL DEFAULT 'info',
  status ENUM('published','archived') NOT NULL DEFAULT 'published',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, id),
  CONSTRAINT fk_announcement_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD seen_announcement BIGINT UNSIGNED NOT NULL DEFAULT 0;
