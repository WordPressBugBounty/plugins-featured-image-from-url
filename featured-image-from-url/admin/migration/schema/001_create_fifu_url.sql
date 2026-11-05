CREATE TABLE IF NOT EXISTS {PREFIX}fifu_url (
    hash CHAR(32) NOT NULL PRIMARY KEY,
    url  TEXT NOT NULL,
    w    SMALLINT UNSIGNED NULL,
    h    SMALLINT UNSIGNED NULL,
    validation_failures    SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    validation_checked_at  DATETIME           NULL,
    validation_queued_at   DATETIME           NULL,
    created_at              DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_validation_queued_at (validation_queued_at)
) ENGINE=InnoDB {CHARSET_COLLATE};
