<?php
declare(strict_types=1);

/**
 * Handles post-based mappings using the new FIFU tables.
 */
class Fifu_Db2_Post_Repository {
    private wpdb $wpdb;
    private string $tableUrl;
    private string $tableMap;
    private string $tableKey;
    private string $tableAlt;
    private string $tableAltMap;
    private static array $keyIdByTypeCache = [];
    private static array $keyIdTypeMatchCache = [];
    /** @var array<string,array<string,mixed>|null> */
    private array $postMappingCache = [];
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $postMappingsCache = [];
    /** @var array<string,array<string,mixed>|null> */
    private array $postAltMappingCache = [];
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $postAltMappingsCache = [];
    private bool $postReadCacheEnabled = false;

    public function __construct(?wpdb $wpdbInstance = null) {
        if ($wpdbInstance === null) {
            /**
             * phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
             */
            global $wpdb;
            $wpdbInstance = $wpdb;
        }

        $this->wpdb = $wpdbInstance;
        $this->tableUrl = $wpdbInstance->prefix . 'fifu_url';
        $this->tableMap = $wpdbInstance->prefix . 'fifu_map';
        $this->tableKey = $wpdbInstance->prefix . 'fifu_key';
        $this->tableAlt = $wpdbInstance->prefix . 'fifu_alt';
        $this->tableAltMap = $wpdbInstance->prefix . 'fifu_alt_map';
    }

    /**
     * Inserts a URL if it does not already exist.
     */
    public function upsertUrl(string $hash, string $url, ?int $width, ?int $height): bool {
        $hash = trim($hash);
        $url = trim($url);

        if ($hash === '' || !self::isValidUrlForDb2($url)) {
            return false;
        }

        $widthSql = $width === null ? 'NULL' : (string) $width;
        $heightSql = $height === null ? 'NULL' : (string) $height;

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableUrl} (hash, url, w, h) VALUES (%s, %s, {$widthSql}, {$heightSql}) ON DUPLICATE KEY UPDATE url = VALUES(url), w = VALUES(w), h = VALUES(h)",
            $hash,
            $url
        );

        $result = $this->wpdb->query($query);
        if ($result === false) {
            return false;
        }

        $saved = $this->getUrlByHash($hash);
        return is_array($saved) && self::isValidUrlForDb2((string) ($saved['url'] ?? ''));
    }

    /**
     * Returns normalized URL rows keyed by hash for a set of URL hashes.
     *
     * @param array<int, string> $hashes
     *
     * @return array<string, array{hash:string,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}>
     */
    public function getUrlsByHashes(array $hashes): array {
        $normalizedHashes = [];

        foreach ($hashes as $hash) {
            $hash = trim((string) $hash);

            if ($hash === '') {
                continue;
            }

            $normalizedHashes[$hash] = $hash;
        }

        $normalizedHashes = array_values($normalizedHashes);

        if ($normalizedHashes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedHashes), '%s'));

        $query = $this->wpdb->prepare(
            "SELECT hash, url, w, h, is_valid, validation_attempts, validation_last_attempt FROM {$this->tableUrl} WHERE hash IN ({$placeholders})",
            ...$normalizedHashes
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeUrlRow($row);
            $hash = trim((string) ($normalized['hash'] ?? ''));

            if ($hash === '') {
                continue;
            }

            $result[$hash] = $normalized;
        }

        return $result;
    }

    /**
     * Inserts or updates a URL row without re-reading it afterward.
     *
     * Intended for callers that perform their own bulk confirmation.
     */
    public function upsertUrlWithoutRead(string $hash, string $url, ?int $width, ?int $height): bool {
        $hash = trim($hash);
        $url = trim($url);

        if ($hash === '' || !self::isValidUrlForDb2($url)) {
            return false;
        }

        $widthSql = $width === null ? 'NULL' : (string) $width;
        $heightSql = $height === null ? 'NULL' : (string) $height;

        $query = $this->wpdb->prepare(
            "INSERT INTO `{$this->tableUrl}` (hash, url, w, h) VALUES (%s, %s, {$widthSql}, {$heightSql}) ON DUPLICATE KEY UPDATE url = VALUES(url), w = VALUES(w), h = VALUES(h)",
            $hash,
            $url
        );

        $result = $this->wpdb->query($query);

        return $result !== false;
    }

    /**
     * Inserts an ALT text if it does not already exist.
     */
    public function upsertAlt(string $hash, string $alt): bool {
        $hash = trim($hash);
        $alt = trim($alt);

        if ($hash === '' || $alt === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->tableAlt} (hash, alt) VALUES (%s, %s)",
            $hash,
            $alt
        );

        $result = $this->wpdb->query($query);
        return $result !== false;
    }

    /**
     * Inserts or updates the mapping between a post and a URL hash.
     */
    public function upsertPostMapping(int $postId, int $keyId, int $keyIndex, string $hash): bool {
        $hash = trim($hash);

        if ($hash === '') {
            return false;
        }

        $urlRow = $this->getUrlByHash($hash);
        if (!is_array($urlRow) || !self::isValidUrlForDb2((string) ($urlRow['url'] ?? ''))) {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableMap} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $postId,
            $keyId,
            $keyIndex,
            $hash
        );

        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateUrlMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result !== false;
    }

    /**
     * Inserts or updates a post mapping for a URL hash that the caller has already validated.
     *
     * This avoids the per-mapping getUrlByHash() check used by upsertPostMapping().
     */
    public function upsertPostMappingForKnownUrlHash(int $postId, int $keyId, int $keyIndex, string $hash): bool {
        $hash = trim($hash);

        if ($postId <= 0 || $keyId <= 0 || $keyIndex < 0 || $hash === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableMap} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $postId,
            $keyId,
            $keyIndex,
            $hash
        );

        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateUrlMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result !== false;
    }

    /**
     * Inserts or updates the mapping between a post and an ALT hash.
     */
    public function upsertAltPostMapping(int $postId, int $keyId, int $keyIndex, string $hash): bool {
        $hash = trim($hash);

        if ($hash === '') {
            return false;
        }

        $altRow = $this->getAltByHash($hash);
        if (!is_array($altRow) || trim((string) ($altRow['alt'] ?? '')) === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableAltMap} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $postId,
            $keyId,
            $keyIndex,
            $hash
        );

        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateAltMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result !== false;
    }

    public function upsertAltPostMappingForKnownAltHash(int $postId, int $keyId, int $keyIndex, string $hash): bool {
        $hash = trim($hash);

        if ($postId <= 0 || $keyId <= 0 || $keyIndex < 0 || $hash === '') {
            return false;
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableAltMap} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $postId,
            $keyId,
            $keyIndex,
            $hash
        );

        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateAltMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result !== false;
    }

    /**
     * Returns all mappings for a post/key pair.
     *
     * @return array<int, array{hash:string,key_index:int,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}>
     */
    public function findPostMappings(int $postId, int $keyId): array {
        if ($postId <= 0) {
            return [];
        }

        $cacheKey = $this->getPostMappingsCacheKey($postId, $keyId);

        if (
            $this->postReadCacheEnabled
            && array_key_exists($cacheKey, $this->postMappingsCache)
        ) {
            return $this->postMappingsCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                m.key_index,
                u.url,
                u.w,
                u.h,
                u.is_valid,
                u.validation_attempts,
                u.validation_last_attempt
            FROM {$this->tableMap} m
            LEFT JOIN {$this->tableUrl} u ON u.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d
            ORDER BY m.key_index ASC
            ",
            $postId,
            $keyId
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if ($rows === null || $rows === false) {
            if ($this->postReadCacheEnabled) {
                $this->postMappingsCache[$cacheKey] = [];
            }

            return [];
        }

        $result = array_map([$this, 'normalizeMappingRow'], $rows);

        if ($this->postReadCacheEnabled) {
            $this->postMappingsCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Returns all ALT mappings for a post/key pair.
     *
     * @return array<int, array{hash:string,key_index:int,alt:string|null}>
     */
    public function findPostAltMappings(int $postId, int $keyId): array {
        if ($postId <= 0) {
            return [];
        }

        $cacheKey = $this->getPostMappingsCacheKey($postId, $keyId);

        if (
            $this->postReadCacheEnabled
            && array_key_exists($cacheKey, $this->postAltMappingsCache)
        ) {
            return $this->postAltMappingsCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                m.key_index,
                a.alt
            FROM {$this->tableAltMap} m
            LEFT JOIN {$this->tableAlt} a ON a.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d
            ORDER BY m.key_index ASC
            ",
            $postId,
            $keyId
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if ($rows === null || $rows === false) {
            if ($this->postReadCacheEnabled) {
                $this->postAltMappingsCache[$cacheKey] = [];
            }

            return [];
        }

        $result = array_map([$this, 'normalizeAltMappingRow'], $rows);

        if ($this->postReadCacheEnabled) {
            $this->postAltMappingsCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Returns a single mapping for a post/key/key-index tuple.
     *
     * @return array{hash:string,key_index:int,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}|null
     */
    public function findPostMapping(int $postId, int $keyId, int $keyIndex): ?array {
        if ($postId <= 0) {
            return null;
        }

        $cacheKey = $this->getPostMappingCacheKey(
            $postId,
            $keyId,
            $keyIndex
        );

        if (
            $this->postReadCacheEnabled
            && array_key_exists($cacheKey, $this->postMappingCache)
        ) {
            return $this->postMappingCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                m.key_index,
                u.url,
                u.w,
                u.h,
                u.is_valid,
                u.validation_attempts,
                u.validation_last_attempt
            FROM {$this->tableMap} m
            LEFT JOIN {$this->tableUrl} u ON u.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d AND m.key_index = %d
            LIMIT 1
            ",
            $postId,
            $keyId,
            $keyIndex
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            if ($this->postReadCacheEnabled) {
                $this->postMappingCache[$cacheKey] = null;
            }

            return null;
        }

        $result = $this->normalizeMappingRow($row);

        if ($this->postReadCacheEnabled) {
            $this->postMappingCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Returns URL and ALT mapping for a post/key/key-index tuple using one DB read.
     *
     * @return array{post_id:int,key_type:string,key_index:int,url:string,url_hash:string,alt:string,alt_hash:string}|null
     */
    public function findPostImageAndAltMapping(int $postId, int $keyId, string $keyType, int $keyIndex): ?array {
        $query = $this->wpdb->prepare(
                "
                SELECT
                    url_map.post_id AS post_id,
                    url_map.key_index AS key_index,
                    url_map.hash AS url_hash,
                    url.url AS url,
                    alt_map.hash AS alt_hash,
                    alt.alt AS alt
                FROM {$this->tableMap} url_map
                LEFT JOIN {$this->tableUrl} url ON url.hash = url_map.hash
                LEFT JOIN {$this->tableAltMap} alt_map
                    ON alt_map.post_id = url_map.post_id
                    AND alt_map.key_id = url_map.key_id
                    AND alt_map.key_index = url_map.key_index
                LEFT JOIN {$this->tableAlt} alt ON alt.hash = alt_map.hash
                WHERE url_map.post_id = %d
                  AND url_map.key_id = %d
                  AND url_map.key_index = %d
                LIMIT 1
                ",
                $postId,
                $keyId,
                $keyIndex
            );

            $row = $this->wpdb->get_row($query, ARRAY_A);
            if (!is_array($row)) {
                return null;
            }

            $url = trim((string) ($row['url'] ?? ''));
            $alt = trim((string) ($row['alt'] ?? ''));
            $urlHash = trim((string) ($row['url_hash'] ?? ''));
            $altHash = trim((string) ($row['alt_hash'] ?? ''));

            if ($url === '' || $alt === '' || $urlHash === '' || $altHash === '') {
                return null;
            }

        return [
            'post_id' => (int) ($row['post_id'] ?? $postId),
            'key_type' => $keyType,
            'key_index' => (int) ($row['key_index'] ?? $keyIndex),
            'url' => $url,
            'url_hash' => $urlHash,
            'alt' => $alt,
            'alt_hash' => $altHash,
        ];
    }

    /**
     * Returns only the mapping hash for a post/key/key-index tuple.
     */
    public function findPostMappingHash(int $postId, int $keyId, int $keyIndex): ?string {
        $query = $this->wpdb->prepare(
            "
            SELECT m.hash
            FROM {$this->tableMap} m
            INNER JOIN {$this->tableUrl} u ON u.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d AND m.key_index = %d
            LIMIT 1
            ",
            $postId,
            $keyId,
            $keyIndex
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            return null;
        }

        $hash = trim((string) ($row['hash'] ?? ''));
        return $hash === '' ? null : $hash;
    }

    /**
     * Returns a single ALT mapping for a post/key/key-index tuple.
     */
    public function findPostAltMapping(int $postId, int $keyId, int $keyIndex): ?array {
        if ($postId <= 0) {
            return null;
        }

        $cacheKey = $this->getPostMappingCacheKey(
            $postId,
            $keyId,
            $keyIndex
        );

        if (
            $this->postReadCacheEnabled
            && array_key_exists($cacheKey, $this->postAltMappingCache)
        ) {
            return $this->postAltMappingCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "
            SELECT
                m.hash,
                m.key_index,
                a.alt
            FROM {$this->tableAltMap} m
            LEFT JOIN {$this->tableAlt} a ON a.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d AND m.key_index = %d
            LIMIT 1
            ",
            $postId,
            $keyId,
            $keyIndex
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            if ($this->postReadCacheEnabled) {
                $this->postAltMappingCache[$cacheKey] = null;
            }

            return null;
        }

        $result = $this->normalizeAltMappingRow($row);

        if ($this->postReadCacheEnabled) {
            $this->postAltMappingCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Returns only the ALT hash for a post/key/key-index tuple.
     */
    public function findPostAltMappingHash(int $postId, int $keyId, int $keyIndex): ?string {
        $query = $this->wpdb->prepare(
            "
            SELECT m.hash
            FROM {$this->tableAltMap} m
            INNER JOIN {$this->tableAlt} a ON a.hash = m.hash
            WHERE m.post_id = %d AND m.key_id = %d AND m.key_index = %d
            LIMIT 1
            ",
            $postId,
            $keyId,
            $keyIndex
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            return null;
        }

        $hash = trim((string) ($row['hash'] ?? ''));
        return $hash === '' ? null : $hash;
    }

    public function deletePostMappings(int $postId, int $keyId, ?int $keyIndex = null): int {
        $sql = "DELETE FROM {$this->tableMap} WHERE post_id = %d AND key_id = %d";
        $args = [$postId, $keyId];

        if ($keyIndex !== null) {
            $sql .= " AND key_index = %d";
            $args[] = $keyIndex;
        }

        $query = $this->wpdb->prepare($sql, ...$args);
        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateUrlMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result === false ? 0 : $result;
    }

    public function deletePostAltMappings(int $postId, int $keyId, ?int $keyIndex = null): int {
        $sql = "DELETE FROM {$this->tableAltMap} WHERE post_id = %d AND key_id = %d";
        $args = [$postId, $keyId];

        if ($keyIndex !== null) {
            $sql .= " AND key_index = %d";
            $args[] = $keyIndex;
        }

        $query = $this->wpdb->prepare($sql, ...$args);
        $result = $this->wpdb->query($query);
        if ($result !== false) {
            $this->invalidateAltMappingCaches(
                $postId,
                $keyId,
                $keyIndex
            );
        }

        return $result === false ? 0 : $result;
    }

    /**
     * Returns the normalized URL record for a hash.
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}|null
     */
    public function getUrlByHash(string $hash): ?array {
        $query = $this->wpdb->prepare(
            "SELECT hash, url, w, h, is_valid, validation_attempts, validation_last_attempt FROM {$this->tableUrl} WHERE hash = %s LIMIT 1",
            $hash
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            return null;
        }

        return $this->normalizeUrlRow($row);
    }

    public function getAltByHash(string $hash): ?array {
        $query = $this->wpdb->prepare(
            "SELECT hash, alt FROM {$this->tableAlt} WHERE hash = %s LIMIT 1",
            $hash
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        if ($row === null || $row === false) {
            return null;
        }

        return $this->normalizeAltRow($row);
    }

    public function getKeyIdByType(string $keyType): ?int {
        $cacheKey = (string) $keyType;
        if (array_key_exists($cacheKey, self::$keyIdByTypeCache)) {
            return self::$keyIdByTypeCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "SELECT key_id FROM {$this->tableKey} WHERE key_type = %s LIMIT 1",
            $keyType
        );

        $keyId = $this->wpdb->get_var($query);
        if ($keyId === null) {
            self::$keyIdByTypeCache[$cacheKey] = null;
            return null;
        }

        self::$keyIdByTypeCache[$cacheKey] = (int) $keyId;
        return self::$keyIdByTypeCache[$cacheKey];
    }

    public function ensureKeyType(string $keyType): ?int {
        $keyId = $this->getKeyIdByType($keyType);
        if ($keyId !== null) {
            return $keyId;
        }

        $insert = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->tableKey} (key_type) VALUES (%s)",
            $keyType
        );

        $this->wpdb->query($insert);
        return $this->getKeyIdByType($keyType);
    }

    public function keyIdMatchesType(int $keyId, string $keyType): bool {
        $cacheKey = (int) $keyId . '|' . (string) $keyType;
        if (array_key_exists($cacheKey, self::$keyIdTypeMatchCache)) {
            return self::$keyIdTypeMatchCache[$cacheKey];
        }

        $query = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->tableKey} WHERE key_id = %d AND key_type = %s LIMIT 1",
            $keyId,
            $keyType
        );

        $result = $this->wpdb->get_var($query);
        self::$keyIdTypeMatchCache[$cacheKey] = $result !== null;
        return self::$keyIdTypeMatchCache[$cacheKey];
    }

    /**
     * Resets per-request key lookup caches. Intended for tests.
     */
    public static function clearRequestCacheForTests(): void {
        self::$keyIdByTypeCache = [];
        self::$keyIdTypeMatchCache = [];
    }

    public function beginPostReadCacheScope(): void {
        $this->clearPostReadCaches();
        $this->postReadCacheEnabled = true;
    }

    public function endPostReadCacheScope(): void {
        $this->clearPostReadCaches();
        $this->postReadCacheEnabled = false;
    }

    public function clearPostReadCaches(): void {
        $this->postMappingCache = [];
        $this->postMappingsCache = [];
        $this->postAltMappingCache = [];
        $this->postAltMappingsCache = [];
    }

    private function getPostMappingCacheKey(
        int $postId,
        int $keyId,
        int $keyIndex
    ): string {
        return $postId . '|' . $keyId . '|' . $keyIndex;
    }

    private function getPostMappingsCacheKey(
        int $postId,
        int $keyId
    ): string {
        return $postId . '|' . $keyId;
    }

    private function invalidateUrlMappingCaches(
        int $postId,
        int $keyId,
        ?int $keyIndex = null
    ): void {
        $listKey =
            $this->getPostMappingsCacheKey($postId, $keyId);

        unset($this->postMappingsCache[$listKey]);

        if ($keyIndex !== null) {
            unset(
                $this->postMappingCache[
                    $this->getPostMappingCacheKey(
                        $postId,
                        $keyId,
                        $keyIndex
                    )
                ]
            );

            return;
        }

        $prefix = $postId . '|' . $keyId . '|';

        foreach (array_keys($this->postMappingCache) as $cacheKey) {
            if (str_starts_with($cacheKey, $prefix)) {
                unset($this->postMappingCache[$cacheKey]);
            }
        }
    }

    private function invalidateAltMappingCaches(
        int $postId,
        int $keyId,
        ?int $keyIndex = null
    ): void {
        $listKey =
            $this->getPostMappingsCacheKey($postId, $keyId);

        unset($this->postAltMappingsCache[$listKey]);

        if ($keyIndex !== null) {
            unset(
                $this->postAltMappingCache[
                    $this->getPostMappingCacheKey(
                        $postId,
                        $keyId,
                        $keyIndex
                    )
                ]
            );

            return;
        }

        $prefix = $postId . '|' . $keyId . '|';

        foreach (
            array_keys($this->postAltMappingCache)
            as $cacheKey
        ) {
            if (str_starts_with($cacheKey, $prefix)) {
                unset($this->postAltMappingCache[$cacheKey]);
            }
        }
    }

    /**
     * Normalize DB rows to keep consistent types.
     *
     * @param array<string, mixed> $row
     *
     * @return array{hash:string,key_index:int,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}
     */
    private function normalizeMappingRow(array $row): array {
        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'key_index' => isset($row['key_index']) ? (int) $row['key_index'] : 0,
            'url' => isset($row['url']) ? (string) $row['url'] : null,
            'width' => isset($row['w']) ? ((string) $row['w'] === '' ? null : (int) $row['w']) : null,
            'height' => isset($row['h']) ? ((string) $row['h'] === '' ? null : (int) $row['h']) : null,
            'is_valid' => $this->normalizeNullableBool($row['is_valid'] ?? null),
            'validation_attempts' => $this->normalizeValidationAttempts($row['validation_attempts'] ?? null),
            'validation_last_attempt' => $this->normalizeNullableDatetime($row['validation_last_attempt'] ?? null),
        ];
    }

    /**
     * Normalize ALT mapping rows to keep consistent types.
     *
     * @param array<string, mixed> $row
     *
     * @return array{hash:string,key_index:int,alt:string|null}
     */
    private function normalizeAltMappingRow(array $row): array {
        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'key_index' => isset($row['key_index']) ? (int) $row['key_index'] : 0,
            'alt' => isset($row['alt']) ? (string) $row['alt'] : null,
        ];
    }

    /**
     * Normalize URL rows to match the public API.
     *
     * @param array<string, mixed> $row
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}
     */
    private function normalizeUrlRow(array $row): array {
        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'url' => isset($row['url']) ? (string) $row['url'] : null,
            'width' => isset($row['w']) ? ((string) $row['w'] === '' ? null : (int) $row['w']) : null,
            'height' => isset($row['h']) ? ((string) $row['h'] === '' ? null : (int) $row['h']) : null,
            'is_valid' => $this->normalizeNullableBool($row['is_valid'] ?? null),
            'validation_attempts' => $this->normalizeValidationAttempts($row['validation_attempts'] ?? null),
            'validation_last_attempt' => $this->normalizeNullableDatetime($row['validation_last_attempt'] ?? null),
        ];
    }

    private function normalizeNullableBool($value): ?bool {
        if ($value === null || $value === '') {
            return null;
        }

        return (bool) ((int) $value);
    }

    private function normalizeValidationAttempts($value): int {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
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
}
