<?php
declare(strict_types=1);

require_once __DIR__ . '/class-fifu-db2-url-validation-schema.php';

/**
 * Handles term-based mappings using the new FIFU tables.
 */
if (!class_exists('Fifu_Db2_Term_Repository', false)) {
    class Fifu_Db2_Term_Repository {
    private wpdb $wpdb;
    private string $tableTermMap;
    private string $tableUrl;
    private string $tableAlt;
    private string $tableAltTermMap;

    public function __construct(?wpdb $wpdbInstance = null) {
        if ($wpdbInstance === null) {
            /**
             * phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
             */
            global $wpdb;
            $wpdbInstance = $wpdb;
        }

        $this->wpdb = $wpdbInstance;
        $this->tableTermMap = $wpdbInstance->prefix . 'fifu_term_map';
        $this->tableUrl = $wpdbInstance->prefix . 'fifu_url';
        $this->tableAlt = $wpdbInstance->prefix . 'fifu_alt';
        $this->tableAltTermMap = $wpdbInstance->prefix . 'fifu_alt_term_map';
    }

    /**
     * Inserts or updates the mapping between a term and a URL hash.
     */
    public function upsertTermMapping(int $termId, int $keyId, string $hash): bool {
        $hash = trim($hash);
        if ($hash === '') {
            return false;
        }

        $urlRow = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT hash, url FROM {$this->tableUrl} WHERE hash = %s LIMIT 1",
                $hash
            ),
            ARRAY_A
        );
        if (!is_array($urlRow) || !self::isValidUrlForDb2((string) ($urlRow['url'] ?? ''))) {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableTermMap} (term_id, key_id, hash) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $termId,
            $keyId,
            $hash
        );

        $result = $this->wpdb->query($query);
        return $result !== false;
    }

    /**
     * Inserts or updates the mapping between a term and an ALT hash.
     */
    public function upsertAltTermMapping(int $termId, int $keyId, string $hash): bool {
        if (!$this->altHashExistsAndIsEffective($hash)) {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableAltTermMap} (term_id, key_id, hash) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $termId,
            $keyId,
            $hash
        );

        $result = $this->wpdb->query($query);
        return $result !== false;
    }

    /**
     * Returns the mapping for the given term/key tuple.
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,validation_failures:int,validation_checked_at:string|null,validation_queued_at:string|null}|null
     */
    public function findTermMapping(int $termId, int $keyId): ?array {
        $buildQuery = function (bool $defaultsOnly = false) use ($termId, $keyId): string {
            $validationColumns = Fifu_Db2_Url_Validation_Schema::select_columns(
                $this->wpdb,
                $this->tableUrl,
                'u',
                $defaultsOnly
            );

            return $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                u.url,
                u.w,
                u.h,
                {$validationColumns}
            FROM {$this->tableTermMap} m
            LEFT JOIN {$this->tableUrl} u ON u.hash = m.hash
            WHERE m.term_id = %d AND m.key_id = %d
            LIMIT 1
            ",
            $termId,
            $keyId
            );
        };

        $row = $this->wpdb->get_row($buildQuery(), ARRAY_A);
        if ( Fifu_Db2_Url_Validation_Schema::is_missing_validation_column_error( $this->wpdb ) ) {
            Fifu_Db2_Url_Validation_Schema::forget( $this->wpdb, $this->tableUrl );
            $row = $this->wpdb->get_row($buildQuery(true), ARRAY_A);
        }
        if ($row === null || $row === false) {
            return null;
        }

        return $this->normalizeUrlRow($row);
    }

    /**
     * Returns the ALT mapping for the given term/key tuple.
     *
     * @return array{hash:string,alt:string|null}|null
     */
    public function findTermAltMapping(int $termId, int $keyId): ?array {
        $query = $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                a.alt
            FROM {$this->tableAltTermMap} m
            LEFT JOIN {$this->tableAlt} a ON a.hash = m.hash
            WHERE m.term_id = %d AND m.key_id = %d
            LIMIT 1
            ",
            $termId,
            $keyId
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            return null;
        }

        return $this->normalizeAltRow($row);
    }

    /**
     * Removes term mappings for the provided key.
     */
    public function deleteTermMappings(int $termId, int $keyId): int {
        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->tableTermMap} WHERE term_id = %d AND key_id = %d",
            $termId,
            $keyId
        );

        $result = $this->wpdb->query($query);
        return $result === false ? 0 : $result;
    }

    /**
     * Removes term ALT mappings for the provided key.
     */
    public function deleteTermAltMappings(int $termId, int $keyId): int {
        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->tableAltTermMap} WHERE term_id = %d AND key_id = %d",
            $termId,
            $keyId
        );

        $result = $this->wpdb->query($query);
        return $result === false ? 0 : $result;
    }

    /**
     * Normalize URL rows to match the public API.
     *
     * @param array<string, mixed> $row
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,validation_failures:int,validation_checked_at:string|null,validation_queued_at:string|null}
     */
    private function normalizeUrlRow(array $row): array {
        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'url' => isset($row['url']) ? (string) $row['url'] : null,
            'width' => isset($row['w']) ? ((string) $row['w'] === '' ? null : (int) $row['w']) : null,
            'height' => isset($row['h']) ? ((string) $row['h'] === '' ? null : (int) $row['h']) : null,
            'validation_failures' => max(0, (int) ($row['validation_failures'] ?? 0)),
            'validation_checked_at' => $this->normalizeNullableDatetime($row['validation_checked_at'] ?? null),
            'validation_queued_at' => $this->normalizeNullableDatetime($row['validation_queued_at'] ?? null),
        ];
    }

    private function normalizeNullableDatetime($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function isValidUrlForDb2(string $url): bool {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (preg_match('/^(null|undefined)$/i', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Normalize ALT rows to match the public API.
     *
     * @param array<string, mixed> $row
     *
     * @return array{hash:string,alt:string|null}
     */
    private function normalizeAltRow(array $row): array {
        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'alt' => isset($row['alt']) ? (string) $row['alt'] : null,
        ];
    }

    private function altHashExistsAndIsEffective(string $hash): bool {
        $hash = trim($hash);

        if ($hash === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            "SELECT alt FROM {$this->tableAlt} WHERE hash = %s LIMIT 1",
            $hash
        );

        $alt = $this->wpdb->get_var($query);
        return $alt !== null && trim((string) $alt) !== '';
    }
}

}
