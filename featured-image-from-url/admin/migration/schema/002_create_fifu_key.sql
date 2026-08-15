CREATE TABLE IF NOT EXISTS {PREFIX}fifu_key (
    key_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    key_type VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB {CHARSET_COLLATE};

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 1, 'image'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'image'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 1
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 2, 'slider'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'slider'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 2
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 3, 'video'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'video'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 3
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 4, 'audio'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'audio'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 4
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 5, 'iframe'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'iframe'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 5
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 6, 'custom_video'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'custom_video'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 6
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 7, 'finder'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'finder'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 7
);

INSERT INTO {PREFIX}fifu_key (key_id, key_type)
SELECT 8, 'redirect'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_type = 'redirect'
)
AND NOT EXISTS (
    SELECT 1 FROM {PREFIX}fifu_key WHERE key_id = 8
);

ALTER TABLE {PREFIX}fifu_key AUTO_INCREMENT = 9;
