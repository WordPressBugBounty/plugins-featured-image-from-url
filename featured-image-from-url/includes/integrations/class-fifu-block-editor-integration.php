<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Block_Editor_Integration {
    private static bool $is_rest_dispatching = false;

    public static function register_hooks(): void {
        // Register hooks related to the FIFU Gutenberg block integration.
        add_action('init', [ self::class, 'register_block_and_meta' ]);
        add_action('rest_after_insert_post', [ self::class, 'sync_block_with_fifu' ], 10, 3);
    }

    public static function register_block_and_meta(): void {
        $should_register_block = true;
        if (is_admin() && isset($GLOBALS['wp_customize']) && $GLOBALS['wp_customize'] instanceof WP_Customize_Manager) {
            $should_register_block = false;
        }
        if (is_admin() && basename($_SERVER['PHP_SELF']) === 'widgets.php') {
            $should_register_block = false;
        }

        if ($should_register_block) {
            $block_strings = Fifu_Block_Strings::get_strings();
            register_block_type(
                FIFU_PLUGIN_DIR . 'blocks/fifu-image',
                array(
                    'title' => $block_strings['title']['image'](),
                    'description' => $block_strings['description']['image'](),
                )
            );

            $strings = array();
            foreach ($block_strings as $group => $items) {
                foreach ($items as $key => $fn) {
                    $strings[$group][$key] = $fn();
                }
            }
            wp_localize_script(
                'fifu-image-editor-script',
                'fifuBlockStrings',
                $strings
            );
        }

        register_post_meta('post', 'fifu_image_url', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
        register_post_meta('post', 'fifu_image_alt', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
        add_filter('get_post_metadata', [ self::class, 'filter_block_meta_rest_reads' ], 10, 5);
        add_filter('update_post_metadata', [ self::class, 'filter_block_meta_rest_writes' ], 10, 5);
        add_filter('add_post_metadata', [ self::class, 'filter_block_meta_rest_adds' ], 10, 5);
        add_filter('rest_pre_insert_post', [ self::class, 'filter_rest_pre_insert_post' ], 10, 2);
        add_filter('rest_request_before_callbacks', [ self::class, 'mark_rest_dispatching' ], 10, 3);
        add_filter('rest_request_after_callbacks', [ self::class, 'unmark_rest_dispatching' ], 10, 3);
    }

    public static function sync_block_with_fifu(\WP_Post $post, \WP_REST_Request $request, bool $creating): void {
        $post_id = isset($post->ID) ? (int) $post->ID : 0;
        if (!$post_id) {
            return;
        }

        $post_content = isset($post->post_content) ? (string) $post->post_content : '';

        if (!self::fifu_block_editor_has_fifu_image_block($post_content)) {
            return;
        }

        $request_meta = self::fifu_block_editor_get_request_meta($request);

        $has_request_image_url = array_key_exists('fifu_image_url', $request_meta);
        $has_request_image_alt = array_key_exists('fifu_image_alt', $request_meta);

        $raw_image_url = $has_request_image_url
            ? $request_meta['fifu_image_url']
            : Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);

        $image_url = esc_url_raw(rtrim((string) $raw_image_url));

        if (!function_exists('fifu_dev_set_image')) {
            $developer_functions = FIFU_PLUGIN_DIR . 'includes/api/fifu-developer-functions.php';
            if (is_readable($developer_functions)) {
                require_once $developer_functions;
            }
        }

        $image_saved = fifu_dev_set_image($post_id, $image_url);

        if (!$image_saved) {
            return;
        }

        if ($image_url === '') {
            FIFU_Post_Meta_Updater::instance()->update_or_delete_value($post_id, 'fifu_image_alt', '');
            return;
        }

        $raw_image_alt = $has_request_image_alt
            ? $request_meta['fifu_image_alt']
            : Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id);

        $image_alt = sanitize_text_field((string) $raw_image_alt);
        FIFU_Post_Meta_Updater::instance()->update_or_delete_value($post_id, 'fifu_image_alt', $image_alt);
    }

    public static function filter_block_meta_rest_reads($value, int $post_id, string $meta_key, bool $single, string $meta_type = 'post') {
        if (!self::should_virtualize_block_meta($meta_key) || !self::is_rest_request_context()) {
            return $value;
        }

        remove_filter('get_post_metadata', [ self::class, 'filter_block_meta_rest_reads' ], 10);

        try {
            if ($meta_key === 'fifu_image_url') {
                $effective_value = Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);
            } else {
                $effective_value = Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id);
            }
        } finally {
            add_filter('get_post_metadata', [ self::class, 'filter_block_meta_rest_reads' ], 10, 5);
        }

        if ($effective_value === null || $effective_value === '') {
            return $single ? '' : array();
        }

        return $single ? (string) $effective_value : array((string) $effective_value);
    }

    public static function filter_block_meta_rest_writes($check, int $post_id, string $meta_key, $meta_value, $prev_value) {
        if (!self::should_virtualize_block_meta($meta_key) || !self::is_rest_request_context()) {
            return $check;
        }

        return true;
    }

    public static function filter_block_meta_rest_adds($check, int $post_id, string $meta_key, $meta_value, bool $unique) {
        if (!self::should_virtualize_block_meta($meta_key) || !self::is_rest_request_context()) {
            return $check;
        }

        return true;
    }

    public static function filter_rest_pre_insert_post($prepared_post, \WP_REST_Request $request) {
        $request_content = self::get_request_block_content($request);
        if (!has_block('fifu/image', $request_content)) {
            return $prepared_post;
        }

        $meta = $request->get_param('meta');
        if (!is_array($meta)) {
            $meta = array();
        }

        if (array_key_exists('fifu_image_url', $meta)) {
            $raw_image_url = trim((string) $meta['fifu_image_url']);
            $sanitized_image_url = esc_url_raw($raw_image_url);

            if ($sanitized_image_url !== '' && !self::is_valid_block_remote_image_url($sanitized_image_url)) {
                unset($meta['fifu_image_url'], $meta['fifu_image_alt']);
                $request->set_param('meta', $meta);
            }
        }

        return $prepared_post;
    }

    private static function should_virtualize_block_meta(string $meta_key): bool {
        return in_array($meta_key, array('fifu_image_url', 'fifu_image_alt'), true);
    }

    private static function is_rest_request_context(): bool {
        return (defined('REST_REQUEST') && REST_REQUEST) || self::$is_rest_dispatching;
    }

    private static function fifu_block_editor_has_fifu_image_block($post_content): bool {
        $post_content = (string) $post_content;

        if (function_exists('has_block') && has_block('fifu/image', $post_content)) {
            return true;
        }

        return strpos($post_content, '<!-- wp:fifu/image') !== false;
    }

    private static function fifu_block_editor_get_request_meta($request): array {
        if ($request instanceof \WP_REST_Request) {
            $meta = $request->get_param('meta');
            return is_array($meta) ? $meta : array();
        }

        if (is_object($request) && method_exists($request, 'get_param')) {
            $meta = $request->get_param('meta');
            return is_array($meta) ? $meta : array();
        }

        return array();
    }

    private static function get_request_block_content(\WP_REST_Request $request): string {
        $params = $request->get_params();
        if (array_key_exists('content', $params) && is_string($params['content'])) {
            return $params['content'];
        }

        $meta = $request->get_param('meta');
        if (is_array($meta) && array_key_exists('content', $meta) && is_string($meta['content'])) {
            return $meta['content'];
        }

        return '';
    }

    private static function is_valid_block_remote_image_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $url = esc_url_raw($url);
        if ($url === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, array('http', 'https'), true)) {
            return false;
        }

        if ($host === '') {
            return false;
        }

        if ($host !== 'localhost' && strpos($host, '.') === false) {
            return false;
        }

        return true;
    }

    public static function mark_rest_dispatching($response, $handler, $request) {
        self::$is_rest_dispatching = true;
        return $response;
    }

    public static function unmark_rest_dispatching($response, $handler, $request) {
        self::$is_rest_dispatching = false;
        return $response;
    }
}
