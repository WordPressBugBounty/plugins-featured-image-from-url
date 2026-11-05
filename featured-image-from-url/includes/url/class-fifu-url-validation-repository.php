<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Persists URL validation queue state in the existing FIFU URL table.
 */
final class Fifu_Url_Validation_Repository
{
    private wpdb $wpdb;
    private string $tableUrl;
    private string $tableMap;
    private string $tableKey;

    public function __construct(?wpdb $wpdbInstance = null)
    {
        if ($wpdbInstance === null) {
            global $wpdb;
            $wpdbInstance = $wpdb;
        }

        $this->wpdb = $wpdbInstance;
        $this->tableUrl = $wpdbInstance->prefix . 'fifu_url';
        $this->tableMap = $wpdbInstance->prefix . 'fifu_map';
        $this->tableKey = $wpdbInstance->prefix . 'fifu_key';
    }

    public function enqueue_url(string $hash, string $url, string $queuedAt): bool
    {
        $hash = trim($hash);
        $url = trim($url);

        if ($hash === '' || !self::is_http_url($url)) {
            return false;
        }

        $retryAfterHours = Fifu_Url_Validation_Policy::RETRY_AFTER_HOURS;
        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->tableUrl} (hash, url, validation_queued_at) VALUES (%s, %s, %s)
            ON DUPLICATE KEY UPDATE validation_queued_at = IF(
                validation_queued_at IS NULL
                AND (validation_checked_at IS NULL OR validation_checked_at <= DATE_SUB(%s, INTERVAL {$retryAfterHours} HOUR)),
                VALUES(validation_queued_at),
                validation_queued_at
            )",
            $hash,
            $url,
            $queuedAt,
            $queuedAt
        );

        return $this->wpdb->query($query) !== false;
    }

    /**
     * Returns the oldest queued URL.
     *
     * @return array{hash:string,url:string,validation_failures:int,validation_checked_at:string|null,validation_queued_at:string}|null
     */
    public function get_next_queued_url(): ?array
    {
        $row = $this->wpdb->get_row(
            "SELECT hash, url, validation_failures, validation_checked_at, validation_queued_at
            FROM {$this->tableUrl}
            WHERE validation_queued_at IS NOT NULL
            ORDER BY validation_queued_at ASC, hash ASC
            LIMIT 1",
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'hash' => (string) ($row['hash'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'validation_failures' => max(0, (int) ($row['validation_failures'] ?? 0)),
            'validation_checked_at' => self::nullable_string($row['validation_checked_at'] ?? null),
            'validation_queued_at' => (string) ($row['validation_queued_at'] ?? ''),
        ];
    }

    public function record_attempt(string $hash, bool $succeeded, string $attemptedAt): bool
    {
        $failureSql = $succeeded ? '0' : 'validation_failures + 1';
        $query = $this->wpdb->prepare(
            "UPDATE {$this->tableUrl}
            SET validation_failures = {$failureSql}, validation_checked_at = %s, validation_queued_at = NULL
            WHERE hash = %s",
            $attemptedAt,
            $hash
        );

        return $this->wpdb->query($query) !== false;
    }

    /**
     * Returns posts whose featured image URL has failed on three or more daily checks.
     *
     * @return array<int, array{post_id:int,post_title:string,url:string}>
     */
    public function get_broken_featured_posts(): array
    {
        $postsTable = $this->wpdb->posts;
        $query = "SELECT DISTINCT p.ID AS post_id, p.post_title, u.url
            FROM {$postsTable} p
            INNER JOIN {$this->tableMap} m ON m.post_id = p.ID AND m.key_index = 0
            INNER JOIN {$this->tableKey} k ON k.key_id = m.key_id AND k.key_type = 'image'
            INNER JOIN {$this->tableUrl} u ON u.hash = m.hash
            WHERE u.validation_failures >= " . Fifu_Url_Validation_Policy::BROKEN_AFTER_FAILURES . "
            AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
            ORDER BY p.post_title ASC, p.ID ASC";

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $postId = (int) ($row['post_id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $result[] = [
                'post_id' => $postId,
                'post_title' => (string) ($row['post_title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
            ];
        }

        return $result;
    }

    private static function is_http_url(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private static function nullable_string($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
