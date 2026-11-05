<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Runs one queued URL check per detached admin-ajax request.
 */
final class Fifu_Url_Validation_Worker
{
    private const LOCK_OPTION = 'fifu_url_validation_worker_lock';
    private const LOCK_LEASE_SECONDS = 60;

    public static function register_hooks(): void
    {
        add_action('wp_ajax_fifu_process_url_validation', [self::class, 'handle_request']);
        add_action('wp_ajax_nopriv_fifu_process_url_validation', [self::class, 'handle_request']);
    }

    public static function handle_request(): void
    {
        $token = $_POST['token'] ?? '';
        if (function_exists('wp_unslash')) {
            $token = wp_unslash($token);
        }

        self::process_once($token);
        wp_die('');
    }

    /**
     * Processes at most one URL. Exposed separately so the queue behavior remains testable.
     *
     * @param mixed $token
     */
    public static function process_once($token): bool
    {
        if (!Fifu_Url_Validation_Queue::is_valid_worker_token($token)) {
            return false;
        }

        if (!self::acquire_worker_lock()) {
            return false;
        }

        $repository = new Fifu_Url_Validation_Repository();
        $processed = false;

        try {
            if (get_option('fifu_validate_featured_image_urls') !== 'toggleon') {
                return false;
            }

            $row = $repository->get_next_queued_url();
            if ($row === null) {
                return false;
            }

            $valid = Fifu_Url_Validation_Checker::is_valid($row['url']);
            if (!$repository->record_attempt($row['hash'], $valid, gmdate('Y-m-d H:i:s'))) {
                return false;
            }

            $processed = true;
        } finally {
            delete_option(self::LOCK_OPTION);
            Fifu_Url_Validation_Queue::release_dispatch_lease();
        }

        if ($processed && $repository->get_next_queued_url() !== null) {
            Fifu_Url_Validation_Queue::dispatch_worker();
        }

        return $processed;
    }

    private static function acquire_worker_lock(): bool
    {
        $lockValue = get_option(self::LOCK_OPTION, false);
        if (is_numeric($lockValue) && (time() - (int) $lockValue) > self::LOCK_LEASE_SECONDS) {
            delete_option(self::LOCK_OPTION);
        }

        return add_option(self::LOCK_OPTION, (string) time(), '', 'no');
    }
}
