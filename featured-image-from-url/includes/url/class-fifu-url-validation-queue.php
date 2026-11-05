<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Enqueues featured image URLs and starts a detached worker request.
 */
final class Fifu_Url_Validation_Queue
{
    private const DISPATCH_OPTION = 'fifu_url_validation_dispatch_at';
    private const DISPATCH_LEASE_SECONDS = 45;
    private const WORKER_ACTION = 'fifu_process_url_validation';

    public static function enqueue_featured_image_url(string $url): bool
    {
        if (get_option('fifu_validate_featured_image_urls') !== 'toggleon') {
            return false;
        }

        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $repository = new Fifu_Url_Validation_Repository();
        if (!$repository->enqueue_url(md5($url), $url, gmdate('Y-m-d H:i:s'))) {
            return false;
        }

        if ($repository->get_next_queued_url() !== null) {
            self::dispatch_worker();
        }

        return true;
    }

    public static function dispatch_worker(): bool
    {
        $lockValue = get_option(self::DISPATCH_OPTION, false);
        if (is_numeric($lockValue) && (time() - (int) $lockValue) > self::DISPATCH_LEASE_SECONDS) {
            delete_option(self::DISPATCH_OPTION);
        }

        if (!add_option(self::DISPATCH_OPTION, (string) time(), '', 'no')) {
            return false;
        }

        $token = hash_hmac('sha256', self::WORKER_ACTION, wp_salt('auth'));
        $response = wp_remote_post(
            admin_url('admin-ajax.php'),
            [
                'blocking' => false,
                'timeout' => 0.01,
                'redirection' => 0,
                'body' => [
                    'action' => self::WORKER_ACTION,
                    'token' => $token,
                ],
            ]
        );

        if (is_wp_error($response)) {
            delete_option(self::DISPATCH_OPTION);
            return false;
        }

        return true;
    }

    public static function release_dispatch_lease(): void
    {
        delete_option(self::DISPATCH_OPTION);
    }

    public static function is_valid_worker_token($token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $expected = hash_hmac('sha256', self::WORKER_ACTION, wp_salt('auth'));
        return hash_equals($expected, $token);
    }
}
