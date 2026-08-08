CREATE TABLE IF NOT EXISTS {PREFIX}fifu_map (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    post_id   BIGINT UNSIGNED NOT NULL,
    key_id    TINYINT UNSIGNED NOT NULL,
    key_index SMALLINT UNSIGNED NOT NULL,

    hash      CHAR(32) NOT NULL,

    UNIQUE KEY uniq_post_meta (post_id, key_id, key_index),
    KEY idx_fifu_map_key_id (key_id),
    KEY idx_fifu_map_hash (hash)
) ENGINE=InnoDB {CHARSET_COLLATE};
