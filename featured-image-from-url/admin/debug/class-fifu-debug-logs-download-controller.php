<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Debug_Logs_Download_Controller
{
    private const ACTION = 'fifu_download_debug_logs';
    private const NONCE_ACTION = 'fifu_download_debug_logs';

    public static function register_hooks(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'handle_download']);
    }

    public static function get_download_url(): string
    {
        return admin_url(
            'admin-post.php?action=' . self::ACTION . '&_wpnonce=' . rawurlencode(wp_create_nonce(self::NONCE_ACTION))
        );
    }

    public static function can_download(): bool
    {
        return current_user_can('manage_options') && get_option('fifu_debug') === 'toggleon';
    }

    public static function handle_download(): void
    {
        if (!self::can_download()) {
            wp_die(
                self::translate('Debug log download is not available.'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer(self::NONCE_ACTION);

        $package = Fifu_Debug_Logs_Package_Service::create_package();
        if (is_wp_error($package)) {
            wp_die(
                $package->get_error_message(),
                '',
                ['response' => $package->get_error_code() === 'fifu_debug_logs_not_found' ? 404 : 500]
            );
        }

        $path = $package['path'] ?? '';
        $filename = $package['filename'] ?? 'fifu-debug-logs.zip';

        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            Fifu_Debug_Logs_Package_Service::delete_package(is_array($package) ? $package : []);

            wp_die(
                self::translate('Debug logs package is not readable.'),
                '',
                ['response' => 500]
            );
        }

        if (headers_sent()) {
            Fifu_Debug_Logs_Package_Service::delete_package($package);

            wp_die(
                self::translate('Debug logs package cannot be sent because headers were already sent.'),
                '',
                ['response' => 500]
            );
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename((string) $filename) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        Fifu_Debug_Logs_Package_Service::delete_package($package);
        exit;
    }

    private static function translate(string $message): string
    {
        if (!function_exists('__')) {
            return $message;
        }

        $domain = defined('FIFU_SLUG') ? FIFU_SLUG : '';

        return __($message, $domain);
    }
}
