<?php

declare(strict_types=1);

/**
 * Repository skeleton for sent status tracking.
 */
if (!class_exists('Fifu_Db2_Sent_Repository', false)) {
    class Fifu_Db2_Sent_Repository {

    private wpdb $wpdb;
    /** @var array<string, int|null> */
    private static array $event_cache = [];

    /**
     * @param wpdb $wpdb
     */
    public function __construct(wpdb $wpdb) {
        $this->wpdb = $wpdb;
    }

    public function get_event_id(string $event_key): ?int {
        $key = $this->normalize_event_key($event_key);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, self::$event_cache)) {
            $cached = self::$event_cache[$key];
            if (is_int($cached) && $cached > 0 && $this->event_id_matches_key($cached, $key)) {
                return $cached;
            }

            unset(self::$event_cache[$key]);
        }

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table_event()} WHERE event_key = %s LIMIT 1",
            $key
        );
        $result = $this->wpdb->get_var($sql);
        $id = $result === null ? null : (int) $result;
        self::$event_cache[$key] = $id;

        return $id;
    }

    private function event_id_matches_key(int $event_id, string $event_key): bool {
        if ($event_id <= 0 || $event_key === '') {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table_event()}
             WHERE id = %d
               AND event_key = %s",
            $event_id,
            $event_key
        );

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    public function upsert_pending(string $object_type, int $object_id, int $event_id, ?string $last_error = null): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $error_placeholder = $last_error === null ? 'NULL' : '%s';
        $params = [$type, $object_id, $event_id];
        if ($last_error !== null) {
            $params[] = $last_error;
        }

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_sent()} (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
             VALUES (%s, %d, %d, 0, 0, NOW(), {$error_placeholder}, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               sent = 0,
               last_sent_at = NOW(),
               last_error = VALUES(last_error)",
            $params
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function increment_attempt(string $object_type, int $object_id, int $event_id, ?string $last_error = null): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $error_placeholder = $last_error === null ? 'NULL' : '%s';
        $params = [$type, $object_id, $event_id];
        if ($last_error !== null) {
            $params[] = $last_error;
        }

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_sent()} (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
             VALUES (%s, %d, %d, 0, 1, NOW(), {$error_placeholder}, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               attempts = attempts + 1,
               sent = 0,
               last_sent_at = NOW(),
               last_error = VALUES(last_error),
               updated_at = NOW()",
            $params
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function set_attempts(string $object_type, int $object_id, int $event_id, int $attempts, ?string $last_error = null): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $error_placeholder = $last_error === null ? 'NULL' : '%s';
        $params = [$type, $object_id, $event_id, $attempts];
        if ($last_error !== null) {
            $params[] = $last_error;
        }

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_sent()} (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
             VALUES (%s, %d, %d, 0, %d, NOW(), {$error_placeholder}, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               sent = 0,
               attempts = VALUES(attempts),
               last_sent_at = NOW(),
               last_error = VALUES(last_error),
               updated_at = NOW()",
            $params
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function mark_sent_ok_with_attempts(string $object_type, int $object_id, int $event_id, int $attempts): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $attempts = max(0, $attempts);

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_sent()} (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
             VALUES (%s, %d, %d, 1, %d, NOW(), NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               sent = 1,
               attempts = VALUES(attempts),
               last_error = NULL,
               last_sent_at = NOW(),
               updated_at = NOW()",
            $type,
            $object_id,
            $event_id,
            $attempts
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function mark_sent_ok(string $object_type, int $object_id, int $event_id): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_sent()} (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
             VALUES (%s, %d, %d, 1, 0, NOW(), NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               sent = 1,
               last_error = NULL,
               last_sent_at = NOW(),
               updated_at = NOW()",
            $type,
            $object_id,
            $event_id
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function delete_status(string $object_type, int $object_id, int $event_id): bool {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_sent()}
             WHERE object_type = %s
               AND object_id = %d
               AND event_id = %d",
            $type,
            $object_id,
            $event_id
        );

        return $sql !== false && $this->wpdb->query($sql) !== false;
    }

    public function bulk_set_attempts(string $object_type, array $object_ids, int $event_id, int $attempts, ?string $last_error = null): bool
    {
        $type = $this->normalize_object_type($object_type);
        $ids = array_values(array_unique(array_filter(array_map('intval', $object_ids), static function ($id) {
            return $id > 0;
        })));

        if ($type === null || $event_id <= 0) {
            return false;
        }

        if (empty($ids)) {
            return true;
        }

        $attempts = max(0, $attempts);
        $ok = true;

        foreach (array_chunk($ids, 1000) as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $object_id) {
                if ($last_error === null) {
                    $values[] = '(%s, %d, %d, 0, %d, NOW(), NULL, NOW(), NOW())';
                    array_push($params, $type, $object_id, $event_id, $attempts);
                } else {
                    $values[] = '(%s, %d, %d, 0, %d, NOW(), %s, NOW(), NOW())';
                    array_push($params, $type, $object_id, $event_id, $attempts, $last_error);
                }
            }

            $sql = $this->wpdb->prepare(
                "INSERT INTO {$this->table_sent()}
                    (object_type, object_id, event_id, sent, attempts, last_sent_at, last_error, created_at, updated_at)
                 VALUES " . implode(',', $values) . "
                 ON DUPLICATE KEY UPDATE
                    sent = 0,
                    attempts = VALUES(attempts),
                    last_sent_at = NOW(),
                    last_error = VALUES(last_error),
                    updated_at = NOW()",
                ...$params
            );

            if ($sql === false || $this->wpdb->query($sql) === false) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function bulk_delete_status(string $object_type, array $object_ids, int $event_id): bool
    {
        $type = $this->normalize_object_type($object_type);
        $ids = array_values(array_unique(array_filter(array_map('intval', $object_ids), static function ($id) {
            return $id > 0;
        })));

        if ($type === null || $event_id <= 0) {
            return false;
        }

        if (empty($ids)) {
            return true;
        }

        $ok = true;

        foreach (array_chunk($ids, 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));

            $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->table_sent()}
                 WHERE object_type = %s
                   AND event_id = %d
                   AND object_id IN ($placeholders)",
                $type,
                $event_id,
                ...$chunk
            );

            if ($sql === false || $this->wpdb->query($sql) === false) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function get_pending_object_ids(int $event_id, int $limit = 500): array {
        if ($event_id <= 0) {
            return [];
        }

        $limit = $this->clamp_limit($limit);

        $sql = $this->wpdb->prepare(
            "SELECT object_id
             FROM {$this->table_sent()}
             WHERE event_id = %d
               AND sent = 0
             ORDER BY COALESCE(last_sent_at, '1970-01-01') ASC, object_id ASC
             LIMIT %d",
            $event_id,
            $limit
        );

        $results = $this->wpdb->get_col($sql);
        if (!is_array($results)) {
            return [];
        }

        return array_map('intval', $results);
    }

    public function get_status(string $object_type, int $object_id, int $event_id): ?array {
        $type = $this->normalize_object_type($object_type);
        if ($type === null || $event_id <= 0 || $object_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT sent, attempts
             FROM {$this->table_sent()}
             WHERE object_type = %s AND object_id = %d AND event_id = %d
             LIMIT 1",
            $type,
            $object_id,
            $event_id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($result) || !array_key_exists('sent', $result) || !array_key_exists('attempts', $result)) {
            return null;
        }

        return [
            'sent' => (int) $result['sent'],
            'attempts' => (int) $result['attempts'],
        ];
    }

    protected function table_sent(): string {
        return $this->wpdb->prefix . 'fifu_sent';
    }

    protected function table_event(): string {
        return $this->wpdb->prefix . 'fifu_sent_event';
    }

    protected function normalize_object_type(string $object_type): ?string {
        $normalized = strtolower(trim($object_type));
        if ($normalized !== 'post' && $normalized !== 'term') {
            return null;
        }

        return $normalized;
    }

    protected function normalize_event_key(string $event_key): string {
        return strtolower(trim($event_key));
    }

    protected function clamp_limit(int $limit): int {
        if ($limit < 1) {
            return 1;
        }

        if ($limit > 5000) {
            return 5000;
        }

        return $limit;
    }
}
}
