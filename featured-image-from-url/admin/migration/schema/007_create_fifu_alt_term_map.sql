
CREATE TABLE IF NOT EXISTS {PREFIX}fifu_alt_term_map (
    id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    term_id BIGINT UNSIGNED NOT NULL,
    key_id  TINYINT UNSIGNED NOT NULL,

    hash    CHAR(32) NOT NULL,

    UNIQUE KEY uniq_term_meta (term_id, key_id),
    KEY idx_fifu_alt_term_map_key_id (key_id),
    KEY idx_fifu_alt_term_map_hash (hash)
) ENGINE=InnoDB {CHARSET_COLLATE};
