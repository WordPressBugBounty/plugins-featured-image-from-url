CREATE TABLE IF NOT EXISTS {PREFIX}fifu_sent (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  object_type VARCHAR(8) NOT NULL,
  object_id BIGINT UNSIGNED NOT NULL,
  event_id SMALLINT UNSIGNED NOT NULL,
  sent TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_sent_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_object_event (object_type, object_id, event_id),
  KEY idx_event_sent (event_id, sent),
  KEY idx_object (object_type, object_id),
  KEY idx_last_sent (last_sent_at)
) ENGINE=InnoDB {CHARSET_COLLATE};
