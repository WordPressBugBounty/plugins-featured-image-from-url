<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Review_Notice
{
    private const OPTION_INSTALLED_TIME = 'fifu_installed_time';
    private const OPTION_SNOOZE_UNTIL = 'fifu_review_snooze_until';
    private const OPTION_DONE = 'fifu_review_done';
    private const QUERY_ACTION = 'fifu_review_action';
    private const NONCE_ACTION = 'fifu_review_nonce';
    private const INITIAL_DELAY_SECONDS = 1209600;
    private const SNOOZE_SECONDS = 2592000;
    private const REVIEW_URL = 'https://wordpress.org/support/plugin/' . 'featured-image-from-url/reviews/' . '?filter=5#new-post';
    private const SUPPORT_URL = 'https://wordpress.org/support/plugin/' . 'featured-image-from-url/';

    public static function register_hooks(): void
    {
        add_action('admin_init', [self::class, 'handle_admin_init']);
        add_action('admin_notices', [self::class, 'render_notice']);
    }

    public static function handle_admin_init(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::ensure_installed_time();

        if (!isset($_GET[self::QUERY_ACTION])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash((string) $_GET[self::QUERY_ACTION]));
        if (!in_array($action, ['later', 'done'], true)) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $now = time();
        if ($action === 'later') {
            update_option(self::OPTION_SNOOZE_UNTIL, $now + self::SNOOZE_SECONDS, false);
        } else {
            update_option(self::OPTION_DONE, 1, false);
            delete_option(self::OPTION_SNOOZE_UNTIL);
        }

        $redirect = remove_query_arg([self::QUERY_ACTION, '_wpnonce']);
        wp_safe_redirect($redirect);
    }

    public static function render_notice(): void
    {
        if (!current_user_can('manage_options') || self::is_fifu_screen() || !self::should_show(time())) {
            return;
        }

        $strings = Fifu_Plugins_Strings::get_strings();
        $baseUrl = remove_query_arg([self::QUERY_ACTION, '_wpnonce']);
        $laterUrl = wp_nonce_url(add_query_arg(self::QUERY_ACTION, 'later', $baseUrl), self::NONCE_ACTION);
        $doneUrl = wp_nonce_url(add_query_arg(self::QUERY_ACTION, 'done', $baseUrl), self::NONCE_ACTION);

        echo '<div class="notice notice-info fifu-keep-notice" style="padding:12px 15px;">';
        echo '<p style="margin:0 0 8px;display:flex;align-items:center;">';
        echo '<span class="dashicons dashicons-camera" style="font-size:20px;margin-right:6px;"></span>';
        echo '<strong>' . esc_html($strings['review']['title']()) . '</strong>&nbsp;';
        echo esc_html($strings['review']['message']());
        echo '</p><p style="margin:0;">';
        echo '<a class="button button-primary" style="margin-right:6px;" target="_blank" rel="noopener noreferrer" href="' . esc_url(self::review_url()) . '">' . esc_html($strings['review']['leave']()) . '</a>';
        echo '<a class="button" style="margin-right:6px;" href="' . esc_url($laterUrl) . '">' . esc_html($strings['review']['later']()) . '</a>';
        echo '<a class="button" style="margin-right:6px;" href="' . esc_url($doneUrl) . '">' . esc_html($strings['review']['done']()) . '</a>';
        echo '<a class="button-link" target="_blank" rel="noopener noreferrer" href="' . esc_url(self::support_url()) . '">' . esc_html($strings['review']['help']()) . '</a>';
        echo '</p></div>';
    }

    public static function review_url(): string
    {
        return self::REVIEW_URL;
    }

    public static function support_url(): string
    {
        return self::SUPPORT_URL;
    }

    private static function ensure_installed_time(): void
    {
        if (get_option(self::OPTION_INSTALLED_TIME, false) !== false) {
            return;
        }
        add_option(self::OPTION_INSTALLED_TIME, time(), '', false);
    }

    private static function should_show(int $now): bool
    {
        if ((int) get_option(self::OPTION_DONE, 0) === 1) {
            return false;
        }
        $installed = (int) get_option(self::OPTION_INSTALLED_TIME, 0);
        if ($installed <= 0 || $now < $installed + self::INITIAL_DELAY_SECONDS) {
            return false;
        }
        return $now >= (int) get_option(self::OPTION_SNOOZE_UNTIL, 0);
    }

    private static function is_fifu_screen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
        if ($page === '' || $page === 'featured-image-from-url') {
            return $page !== '';
        }
        return strpos($page, 'fifu-') === 0;
    }
}
