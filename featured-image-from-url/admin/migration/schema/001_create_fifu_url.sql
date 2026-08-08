CREATE TABLE IF NOT EXISTS {PREFIX}fifu_url (
    hash CHAR(32) NOT NULL PRIMARY KEY,
    url  TEXT NOT NULL,
    w    SMALLINT UNSIGNED NULL,
    h    SMALLINT UNSIGNED NULL,
    is_valid               TINYINT(1)          NULL DEFAULT NULL,
    validation_attempts    INT UNSIGNED        NOT NULL DEFAULT 0,
    validation_last_attempt DATETIME           NULL,
    created_at              DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB {CHARSET_COLLATE};
