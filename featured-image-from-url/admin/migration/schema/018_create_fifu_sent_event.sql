CREATE TABLE IF NOT EXISTS {PREFIX}fifu_sent_event (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_key (event_key)
) ENGINE=InnoDB {CHARSET_COLLATE};

INSERT IGNORE INTO {PREFIX}fifu_sent_event (event_key) VALUES
('metadataterm'),
('metadatapost'),
('isbn'),
('asin'),

('tags'),
('importpost'),
('importterm');
