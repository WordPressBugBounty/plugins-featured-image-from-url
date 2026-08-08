<?php
declare(strict_types=1);

final class Fifu_Cloud_Http_Client {

    /**
     * Check if the current site is running locally or in debug mode.
     */
    public static function is_local(): bool {
        $query = 'http://localhost';
        return substr(get_home_url(), 0, strlen($query)) === $query || FIFU_CLOUD_DEBUG;
    }

    /**
     * Perform a POST request to the given endpoint using either wp_remote_post or wp_safe_remote_post.
     */
    public static function post(string $endpoint, array $args) {
        return self::is_local() ? wp_remote_post($endpoint, $args) : wp_safe_remote_post($endpoint, $args);
    }

    /**
     * Resolve the public client IP to be sent to FIFU Cloud.
     */
    public static function get_public_ip(): string {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (isset($_SERVER[$key]) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
