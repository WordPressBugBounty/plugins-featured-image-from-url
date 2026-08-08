CREATE TABLE IF NOT EXISTS {PREFIX}fifu_identifier_type (
  type_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type_key  VARCHAR(16)      NOT NULL UNIQUE
) ENGINE=InnoDB {CHARSET_COLLATE};

INSERT INTO {PREFIX}fifu_identifier_type (type_key)
SELECT 'asin'
WHERE NOT EXISTS (
  SELECT 1 FROM {PREFIX}fifu_identifier_type WHERE type_key = 'asin'
);

INSERT INTO {PREFIX}fifu_identifier_type (type_key)
SELECT 'isbn'
WHERE NOT EXISTS (
  SELECT 1 FROM {PREFIX}fifu_identifier_type WHERE type_key = 'isbn'
);
