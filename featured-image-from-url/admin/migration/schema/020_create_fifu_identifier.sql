CREATE TABLE IF NOT EXISTS {PREFIX}fifu_identifier (
  id         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,

  post_id    BIGINT UNSIGNED  NOT NULL,
  type_id    TINYINT UNSIGNED NOT NULL,
  id_value   VARCHAR(32)      NOT NULL,

  invalid    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  not_found  TINYINT UNSIGNED NOT NULL DEFAULT 0,

  created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_post_identifier (post_id, type_id),
  KEY idx_type_value (type_id, id_value),
  KEY idx_post_id (post_id)
) ENGINE=InnoDB {CHARSET_COLLATE};
