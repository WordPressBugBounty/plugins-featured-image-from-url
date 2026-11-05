<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Performs a bounded HTTP check for a remote image URL.
 */
final class Fifu_Url_Validation_Checker
{
    private const TIMEOUT_SECONDS = 8;
    private const MAX_REDIRECTIONS = 3;

    public static function is_valid(string $url): bool
    {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $args = [
            'method' => 'HEAD',
            'timeout' => self::TIMEOUT_SECONDS,
            'redirection' => self::MAX_REDIRECTIONS,
            'reject_unsafe_urls' => true,
        ];
        $response = wp_safe_remote_get($url, $args);
        if (is_wp_error($response)) {
            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status === 405 || $status === 501) {
            $args['method'] = 'GET';
            $args['headers'] = ['Range' => 'bytes=0-0'];
            $args['limit_response_size'] = 1;
            $response = wp_safe_remote_get($url, $args);
            if (is_wp_error($response)) {
                return false;
            }
            $status = (int) wp_remote_retrieve_response_code($response);
        }

        return $status >= 200 && $status < 400;
    }
}
