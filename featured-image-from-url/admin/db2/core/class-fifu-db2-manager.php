<?php
declare(strict_types=1);

/**
 * Main façade for the new FIFU db2 layer.
 */
class Fifu_Db2_Manager {
    private wpdb $wpdb;
    private Fifu_Db2_Post_Repository $postRepository;
    private Fifu_Db2_Term_Repository $termRepository;

    private const SUPPORTED_KEY_TYPES = [
        'image',
        'iframe',
        'finder',
    ];

    private const WRITABLE_KEY_TYPES = [
        'image',
        'finder',
    ];

    /** @var array<string, int|null> */
    private array $keyIdCache = [];

    private bool $runtimeSchemaReadinessChecked = false;

    public function __construct(
        wpdb $wpdb,
        Fifu_Db2_Post_Repository $postRepository,
        Fifu_Db2_Term_Repository $termRepository
    ) {
        $this->wpdb = $wpdb;
        $this->postRepository = $postRepository;
        $this->termRepository = $termRepository;
    }

    /**
     * Provides the managed WPDB instance to callers that need it.
     */
    public function getWpdb(): wpdb {
        return $this->wpdb;
    }

    public function beginPostReadCacheScope(): void {
        $this->postRepository->beginPostReadCacheScope();
    }

    public function endPostReadCacheScope(): void {
        $this->postRepository->endPostReadCacheScope();
    }

    /**
     * Persist a post mapping together with its normalized URL record.
     */
    public function savePostUrl(
        int $postId,
        string $keyType,
        int $keyIndex,
        string $url,
        ?int $width = null,
        ?int $height = null
    ): bool {
        if (!$this->canMutatePostKey($keyType, $keyIndex)) {
            return false;
        }
        $keyId = $this->resolveKeyId($keyType);
        $url = self::normalizeUrlForDb2($url);

        if ($keyId === null || $url === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $hash = $this->hashUrl($url);
        if (!$this->postRepository->upsertUrl($hash, $url, $width, $height)) {
            return false;
        }

        $urlDetails = $this->postRepository->getUrlByHash($hash);
        if (
            !is_array($urlDetails)
            || self::normalizeUrlForDb2((string) ($urlDetails['url'] ?? '')) !== $url
        ) {
            return false;
        }

        return $this->postRepository->upsertPostMapping($postId, $keyId, $keyIndex, $hash);
    }

    /**
     * Persist a term mapping together with its normalized URL record.
     */
    public function saveTermUrl(
        int $termId,
        string $keyType,
        string $url,
        ?int $width = null,
        ?int $height = null
    ): bool {
        $keyId = $this->resolveKeyId($keyType);
        $url = self::normalizeUrlForDb2($url);

        if ($keyId === null || $url === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $hash = $this->hashUrl($url);
        if (!$this->postRepository->upsertUrl($hash, $url, $width, $height)) {
            return false;
        }

        $urlDetails = $this->postRepository->getUrlByHash($hash);
        if (
            !is_array($urlDetails)
            || self::normalizeUrlForDb2((string) ($urlDetails['url'] ?? '')) !== $url
        ) {
            return false;
        }

        return $this->termRepository->upsertTermMapping($termId, $keyId, $hash);
    }

    /**
     * Returns all mappings for the given post/key type combination.
     *
     * @return array<int, array{hash:string,key_index:int,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}>
     */
    public function getPostMappings(int $postId, string $keyType): array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return [];
        }

        return $this->postRepository->findPostMappings($postId, $keyId);
    }

    /**
     * Returns a single post mapping identified by key index.
     *
     * @return array{hash:string,key_index:int,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}|null
     */
    public function getPostMapping(int $postId, string $keyType, int $keyIndex): ?array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->postRepository->findPostMapping($postId, $keyId, $keyIndex);
    }

    /**
     * Returns featured post URL and ALT mapping data using one repository read.
     *
     * @return array{post_id:int,key_type:string,key_index:int,url:string,url_hash:string,alt:string,alt_hash:string}|null
     */
    public function getPostImageAndAltMapping(int $postId, string $keyType = 'image', int $keyIndex = 0): ?array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->postRepository->findPostImageAndAltMapping($postId, $keyId, $keyType, $keyIndex);
    }

    public function getPostMappingHash(int $postId, string $keyType, int $keyIndex): ?string {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->postRepository->findPostMappingHash($postId, $keyId, $keyIndex);
    }

    public function getPostHash(int $postId, string $keyType, int $keyIndex): ?string {
        $mapping = $this->getPostMapping($postId, $keyType, $keyIndex);
        return $mapping['hash'] ?? null;
    }

    public function deletePostMappings(int $postId, string $keyType, ?int $keyIndex = null): int {
        if ($keyType === 'image') {
            $keyIndex = $keyIndex ?? 0;
        }
        if (!$this->canMutatePostKey($keyType, $keyIndex)) {
            return 0;
        }
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return 0;
        }

        return $this->postRepository->deletePostMappings($postId, $keyId, $keyIndex);
    }

    public function deleteAllMappingsForDeletedPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }

        $this->wpdb->delete(
            $this->wpdb->prefix . 'fifu_map',
            ['post_id' => $postId],
            ['%d']
        );

        $this->wpdb->delete(
            $this->wpdb->prefix . 'fifu_alt_map',
            ['post_id' => $postId],
            ['%d']
        );

        $this->postRepository->clearPostReadCaches();
    }

    /**
     * Returns the term mapping for the specified key type.
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}|null
     */
    public function getTermMapping(int $termId, string $keyType): ?array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->termRepository->findTermMapping($termId, $keyId);
    }

    public function getTermHash(int $termId, string $keyType): ?string {
        $mapping = $this->getTermMapping($termId, $keyType);
        return $mapping['hash'] ?? null;
    }

    public function deleteTermMappings(int $termId, string $keyType): int {
        if (!$this->isWritableKeyType($keyType)) {
            return 0;
        }
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return 0;
        }

        return $this->termRepository->deleteTermMappings($termId, $keyId);
    }

    /**
     * Returns the normalized URL record for a hash if it exists.
     *
     * @return array{hash:string,url:string|null,width:int|null,height:int|null,is_valid:bool|null,validation_attempts:int,validation_last_attempt:string|null}|null
     */
    public function getUrlDetails(string $hash): ?array {
        return $this->postRepository->getUrlByHash($hash);
    }

    public function hashUrl(string $url): string {
        return md5($url);
    }

    private static function normalizeUrlForDb2(string $url): ?string {
        return Fifu_Db2_Normalizer::normalize_url($url);
    }

    private static function isValidDb2Url(string $url): bool {
        return Fifu_Db2_Normalizer::is_valid_url($url);
    }

    public function clearKeyCache(): void {
        $this->keyIdCache = [];
        $this->runtimeSchemaReadinessChecked = false;
        $this->postRepository->clearPostReadCaches();
    }

    public static function clearRequestCache(): void {
        if (!function_exists('fifu_db2_manager')) {
            return;
        }

        $manager = fifu_db2_manager();
        if ($manager instanceof self) {
            $manager->clearKeyCache();
        }
    }

    public static function clearRequestCacheForTests(): void {
        self::clearRequestCache();
    }

    /**
     * Persist a post mapping together with its normalized ALT record.
     */
    public function savePostAlt(
        int $postId,
        string $keyType,
        int $keyIndex,
        string $alt
    ): bool {
        if (!$this->canMutatePostKey($keyType, $keyIndex)) {
            return false;
        }
        $keyId = $this->resolveKeyId($keyType);
        $alt = self::normalizeAltForDb2($alt);

        if ($keyId === null || $alt === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $hash = $this->hashAlt($alt);
        if (!$this->postRepository->upsertAlt($hash, $alt)) {
            return false;
        }

        $altDetails = $this->getAltDetails($hash);
        if (
            !is_array($altDetails)
            || ($altDetails['hash'] ?? '') !== $hash
            || trim((string) ($altDetails['alt'] ?? '')) === ''
        ) {
            return false;
        }

        return $this->postRepository->upsertAltPostMapping($postId, $keyId, $keyIndex, $hash);
    }

    public function savePostImageFastInsert(int $postId, string $url, int $index = 0): bool
    {
        if ($index !== 0) {
            return false;
        }
        $keyType = 'image';
        $keyId = $this->resolveKeyId($keyType);
        $url = self::normalizeUrlForDb2($url);

        if ($keyId === null || $url === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $urlHash = $this->hashUrl($url);
        return $this->postRepository->upsertUrl($urlHash, $url, null, null)
            && $this->postRepository->upsertPostMapping($postId, $keyId, $index, $urlHash);
    }

    /**
     * Persist a full post image list using bulk URL confirmation and write-only mapping writes.
     *
     * @param array<int, string> $urls
     */
    public function savePostImageListFastInsert(int $postId, array $urls): bool
    {
        return $this->savePostUrlListFastInsert($postId, 'image', $urls);
    }

    public function savePostUrlListFastInsert(int $postId, string $keyType, array $urls): bool
    {
        $keyType = trim($keyType);

        $normalizedUrls = [];

        foreach ($urls as $url) {
            $url = self::normalizeUrlForDb2((string) $url);

            if ($url === null) {
                return false;
            }

            $normalizedUrls[] = $url;
        }

        if ($postId <= 0 || $keyType === '' || $normalizedUrls === []) {
            return false;
        }

        $keyId = $this->resolveKeyId($keyType);

        if ($keyId === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $hashesByIndex = [];
        $urlsByHash = [];

        foreach ($normalizedUrls as $index => $url) {
            $hash = $this->hashUrl($url);
            $hashesByIndex[(int) $index] = $hash;

            if (isset($urlsByHash[$hash]) && $urlsByHash[$hash] !== $url) {
                return false;
            }

            $urlsByHash[$hash] = $url;
        }

        foreach ($urlsByHash as $hash => $url) {
            if (!$this->postRepository->upsertUrlWithoutRead($hash, $url, null, null)) {
                return false;
            }
        }

        $confirmedRowsByHash = $this->postRepository->getUrlsByHashes(array_keys($urlsByHash));

        foreach ($normalizedUrls as $index => $url) {
            $hash = $hashesByIndex[(int) $index] ?? '';
            $confirmed = $confirmedRowsByHash[$hash] ?? null;

            if (
                !is_array($confirmed)
                || self::normalizeUrlForDb2((string) ($confirmed['url'] ?? '')) !== $url
            ) {
                return false;
            }
        }

        foreach ($hashesByIndex as $index => $hash) {
            if (!$this->postRepository->upsertPostMappingForKnownUrlHash($postId, $keyId, (int) $index, $hash)) {
                $this->postRepository->deletePostMappings($postId, $keyId, null);
                return false;
            }
        }

        return true;
    }

    public function savePostImageAltFastInsert(int $postId, string $alt, int $index = 0): bool
    {
        if ($index !== 0) {
            return false;
        }
        $keyType = 'image';
        $keyId = $this->resolveKeyId($keyType);
        $alt = self::normalizeAltForDb2($alt);

        if ($keyId === null || $alt === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $altHash = $this->hashAlt($alt);
        if (!$this->postRepository->upsertAlt($altHash, $alt)) {
            return false;
        }

        if (!$this->postRepository->upsertAltPostMappingForKnownAltHash($postId, $keyId, $index, $altHash)) {
            $this->deletePostAltMappings($postId, $keyType, $index);
            return false;
        }

        return true;
    }

    /**
     * Persist a post ALT list using write-only ALT mapping writes.
     *
     * @param array<int, string> $alts
     *
     * @return array{alts_confirmed: bool}
     */
    public function savePostAltListFastInsert(int $postId, string $keyType, array $alts): array
    {
        $keyType = trim($keyType);

        if ($postId <= 0 || $keyType === '') {
            return ['alts_confirmed' => false];
        }

        $keyId = $this->resolveKeyId($keyType);

        if ($keyId === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return ['alts_confirmed' => false];
        }

        foreach ($alts as $index => $alt) {
            $alt = self::normalizeAltForDb2((string) $alt);

            if ($alt === null) {
                continue;
            }

            $altHash = $this->hashAlt($alt);

            if (!$this->postRepository->upsertAlt($altHash, $alt)) {
                return ['alts_confirmed' => false];
            }

            if (!$this->postRepository->upsertAltPostMappingForKnownAltHash($postId, $keyId, (int) $index, $altHash)) {
                $this->deletePostAltMappings($postId, $keyType, (int) $index);
                return ['alts_confirmed' => false];
            }
        }

        return ['alts_confirmed' => true];
    }

    /**
     * Persist a term mapping together with its normalized ALT record.
     */
    public function saveTermAlt(
        int $termId,
        string $keyType,
        string $alt
    ): bool {
        $keyId = $this->resolveKeyId($keyType);
        $alt = self::normalizeAltForDb2($alt);

        if ($keyId === null || $alt === null || !$this->ensureKeyAvailableForWrite($keyType, $keyId)) {
            return false;
        }

        $hash = $this->hashAlt($alt);
        if (!$this->postRepository->upsertAlt($hash, $alt)) {
            return false;
        }

        $altDetails = $this->getAltDetails($hash);
        if (
            !is_array($altDetails)
            || ($altDetails['hash'] ?? '') !== $hash
            || trim((string) ($altDetails['alt'] ?? '')) === ''
        ) {
            return false;
        }

        return $this->termRepository->upsertAltTermMapping($termId, $keyId, $hash);
    }

    /**
     * Returns all ALT mappings for the given post/key type combination.
     *
     * @return array<int, array{hash:string,key_index:int,alt:string|null}>
     */
    public function getPostAltMappings(int $postId, string $keyType): array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return [];
        }

        return $this->postRepository->findPostAltMappings($postId, $keyId);
    }

    /**
     * Returns a single post ALT mapping identified by key index.
     *
     * @return array{hash:string,key_index:int,alt:string|null}|null
     */
    public function getPostAltMapping(int $postId, string $keyType, int $keyIndex): ?array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->postRepository->findPostAltMapping($postId, $keyId, $keyIndex);
    }

    public function getPostAltMappingHash(int $postId, string $keyType, int $keyIndex): ?string {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->postRepository->findPostAltMappingHash($postId, $keyId, $keyIndex);
    }

    public function getPostAltHash(int $postId, string $keyType, int $keyIndex): ?string {
        $mapping = $this->getPostAltMapping($postId, $keyType, $keyIndex);
        return $mapping['hash'] ?? null;
    }

    public function deletePostAltMappings(int $postId, string $keyType, ?int $keyIndex = null): int {
        if ($keyType === 'image') {
            $keyIndex = $keyIndex ?? 0;
        }
        if (!$this->canMutatePostKey($keyType, $keyIndex)) {
            return 0;
        }
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return 0;
        }

        return $this->postRepository->deletePostAltMappings($postId, $keyId, $keyIndex);
    }

    /**
     * Returns the term ALT mapping for the specified key type.
     *
     * @return array{hash:string,alt:string|null}|null
     */
    public function getTermAltMapping(int $termId, string $keyType): ?array {
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return null;
        }

        return $this->termRepository->findTermAltMapping($termId, $keyId);
    }

    public function getTermAltHash(int $termId, string $keyType): ?string {
        $mapping = $this->getTermAltMapping($termId, $keyType);
        return $mapping['hash'] ?? null;
    }

    public function deleteTermAltMappings(int $termId, string $keyType): int {
        if (!$this->isWritableKeyType($keyType)) {
            return 0;
        }
        $keyId = $this->resolveKeyId($keyType);
        if ($keyId === null) {
            return 0;
        }

        return $this->termRepository->deleteTermAltMappings($termId, $keyId);
    }

    /**
     * Returns the normalized ALT record for a hash if it exists.
     *
     * @return array{hash:string,alt:string|null}|null
     */
    public function getAltDetails(string $hash): ?array {
        return $this->postRepository->getAltByHash($hash);
    }

    public function hashAlt(string $alt): string {
        return md5($alt);
    }

    private static function normalizeAltForDb2(string $alt): ?string {
        return Fifu_Db2_Normalizer::normalize_alt($alt);
    }

    private function ensureKeyAvailableForWrite(string $keyType, int $keyId): bool {
        if (!$this->isWritableKeyType($keyType)) {
            return false;
        }
        if ($this->postRepository->keyIdMatchesType($keyId, $keyType)) {
            return true;
        }

        error_log(sprintf('ensureKeyAvailableForWrite fail: keyType=%s keyId=%d', $keyType, $keyId));
        unset($this->keyIdCache[$keyType]);
        return false;
    }

    private function isWritableKeyType(string $keyType): bool {
        return in_array($keyType, self::WRITABLE_KEY_TYPES, true);
    }

    private function canMutatePostKey(string $keyType, ?int $keyIndex): bool {
        if (!$this->isWritableKeyType($keyType)) {
            return false;
        }
        return $keyType !== 'image' || ($keyIndex ?? 0) === 0;
    }

    private function resolveKeyId(string $keyType): ?int {
        if (!$this->isSupportedKeyType($keyType)) {
            $this->keyIdCache[$keyType] = null;
            return null;
        }

        if (array_key_exists($keyType, $this->keyIdCache)) {
            $cached = $this->keyIdCache[$keyType];
            if ($cached !== null && $this->postRepository->keyIdMatchesType($cached, $keyType)) {
                return $cached;
            }

            unset($this->keyIdCache[$keyType]);
        }

        $keyId = $this->postRepository->getKeyIdByType($keyType);

        if ($keyId === null) {
            $this->keyIdCache[$keyType] = null;
            return null;
        }

        if (!$this->postRepository->keyIdMatchesType($keyId, $keyType)) {
            $this->keyIdCache[$keyType] = null;
            return null;
        }

        $this->keyIdCache[$keyType] = $keyId;
        return $keyId;
    }

    public function ensureRuntimeSchemaReady(): bool {
        if ($this->runtimeSchemaReadinessChecked) {
            return true;
        }

        $this->runtimeSchemaReadinessChecked = true;

        $requiredTables = [
            $this->wpdb->prefix . 'fifu_url',
            $this->wpdb->prefix . 'fifu_key',
            $this->wpdb->prefix . 'fifu_map',
            $this->wpdb->prefix . 'fifu_alt',
            $this->wpdb->prefix . 'fifu_alt_map',
        ];

        $missing = false;
        foreach ($requiredTables as $table) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                $missing = true;
                break;
            }
        }

        if (!$missing) {
            return true;
        }

        if (!function_exists('fifu_run_schema_migrations_for_blog')) {
            return false;
        }

        try {
            fifu_run_schema_migrations_for_blog();
        } catch (\Throwable $e) {
            error_log('Fifu_Db2_Manager runtime schema readiness failed: ' . $e->getMessage());
            return false;
        }

        $this->clearKeyCache();

        if (class_exists('Fifu_Db2_Post_Repository', false)) {
            if (method_exists('Fifu_Db2_Post_Repository', 'clearRequestCache')) {
                Fifu_Db2_Post_Repository::clearRequestCache();
            } elseif (method_exists('Fifu_Db2_Post_Repository', 'clearRequestCacheForTests')) {
                Fifu_Db2_Post_Repository::clearRequestCacheForTests();
            }
        }

        foreach ($requiredTables as $table) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                return false;
            }
        }

        return true;
    }

    private function isSupportedKeyType(string $keyType): bool {
        return in_array($keyType, self::SUPPORTED_KEY_TYPES, true);
    }
}
