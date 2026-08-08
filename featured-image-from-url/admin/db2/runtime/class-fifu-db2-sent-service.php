<?php

declare(strict_types=1);

/**
 * Runtime service skeleton for sent status handling.
 */
class Fifu_Db2_Sent_Service {

    private Fifu_Db2_Sent_Repository $repository;
    private const EVENT_KEY_METADATA_POST = 'metadatapost';
    private const EVENT_KEY_METADATA_TERM = 'metadataterm';
    private const EVENT_KEY_TAGS = 'tags';

    public function __construct(Fifu_Db2_Sent_Repository $repository) {
        $this->repository = $repository;
    }

    public function get_event_id(string $event_key): ?int {
        return $this->repository->get_event_id($event_key);
    }

    public function set_sent_post_metadata(int $post_id, bool $sent): bool {
        return $this->set_sent_status('post', $post_id, self::EVENT_KEY_METADATA_POST, $sent);
    }

    public function set_sent_term_metadata(int $term_id, bool $sent): bool {
        return $this->set_sent_status('term', $term_id, self::EVENT_KEY_METADATA_TERM, $sent);
    }

    public function is_sent_post_metadata(int $post_id): bool {
        return $this->is_sent_status('post', $post_id, self::EVENT_KEY_METADATA_POST);
    }

    public function is_sent_term_metadata(int $term_id): bool {
        return $this->is_sent_status('term', $term_id, self::EVENT_KEY_METADATA_TERM);
    }

    public function register_pending(string $object_type, int $object_id, string $event_key, ?string $last_error = null): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->upsert_pending($object_type, $object_id, $event_id, $last_error);
    }

    public function increment_attempt(string $object_type, int $object_id, string $event_key, ?string $last_error = null): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->increment_attempt($object_type, $object_id, $event_id, $last_error);
    }

    public function set_attempts(string $object_type, int $object_id, string $event_key, int $attempts, ?string $last_error = null): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->set_attempts($object_type, $object_id, $event_id, $attempts, $last_error);
    }

    public function mark_sent_ok_with_attempts(string $object_type, int $object_id, string $event_key, int $attempts): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->mark_sent_ok_with_attempts($object_type, $object_id, $event_id, $attempts);
    }

    public function mark_sent_ok(string $object_type, int $object_id, string $event_key): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->mark_sent_ok($object_type, $object_id, $event_id);
    }

    public function delete_status(string $object_type, int $object_id, string $event_key): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        return $this->repository->delete_status($object_type, $object_id, $event_id);
    }

    public function bulk_set_attempts(string $object_type, array $object_ids, string $event_key, int $attempts, ?string $last_error = null): bool
    {
        $event_id = $this->get_event_id($event_key);

        if ($event_id === null) {
            return false;
        }

        return $this->repository->bulk_set_attempts($object_type, $object_ids, $event_id, $attempts, $last_error);
    }

    public function bulk_delete_status(string $object_type, array $object_ids, string $event_key): bool
    {
        $event_id = $this->get_event_id($event_key);

        if ($event_id === null) {
            return false;
        }

        return $this->repository->bulk_delete_status($object_type, $object_ids, $event_id);
    }

    public function get_status(string $object_type, int $object_id, string $event_key): ?array {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return null;
        }

        return $this->repository->get_status($object_type, $object_id, $event_id);
    }

    public function get_pending_object_ids(string $event_key, int $limit = 500): array {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return [];
        }

        return $this->repository->get_pending_object_ids($event_id, $limit);
    }

    public function set_sent_post_tags(int $post_id, bool $sent): bool {
        return $this->set_sent_status('post', $post_id, self::EVENT_KEY_TAGS, $sent);
    }

    public function is_sent_post_tags(int $post_id): bool {
        return $this->is_sent_status('post', $post_id, self::EVENT_KEY_TAGS);
    }

    public function set_attempts_post_tags(int $post_id, int $attempts, ?string $last_error = null): bool {
        return $this->set_attempts('post', $post_id, self::EVENT_KEY_TAGS, $attempts, $last_error);
    }

    public function get_attempts_post_tags(int $post_id): ?int {
        return $this->get_status_attempts('post', $post_id, self::EVENT_KEY_TAGS);
    }

    private function set_sent_status(string $object_type, int $object_id, string $event_key, bool $sent): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        if ($sent) {
            return $this->repository->mark_sent_ok($object_type, $object_id, $event_id);
        }

        return $this->repository->upsert_pending($object_type, $object_id, $event_id);
    }

    private function is_sent_status(string $object_type, int $object_id, string $event_key): bool {
        $event_id = $this->get_event_id($event_key);
        if ($event_id === null) {
            return false;
        }

        $status = $this->repository->get_status($object_type, $object_id, $event_id);
        if (!is_array($status) || !array_key_exists('sent', $status)) {
            return false;
        }

        return (int) $status['sent'] === 1;
    }

    private function get_status_attempts(string $object_type, int $object_id, string $event_key): ?int {
        $status = $this->get_status($object_type, $object_id, $event_key);
        if ($status === null) {
            return null;
        }

        if (isset($status['sent']) && (int) $status['sent'] === 1) {
            return null;
        }

        return isset($status['attempts']) ? (int) $status['attempts'] : null;
    }
}
