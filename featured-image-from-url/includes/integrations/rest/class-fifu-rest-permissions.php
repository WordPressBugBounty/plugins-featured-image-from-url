<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Rest_Permissions {

    public static function private_data(\WP_REST_Request $request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', __('Private'), array('status' => 401));
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('rest_forbidden', __('Private'), array('status' => 401));
        }

        return true;
    }

    public static function quick_edit_read(\WP_REST_Request $request) {
        if (!current_user_can('publish_posts')) {
            return new \WP_Error('rest_forbidden', __('Private'), ['status' => 401]);
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('rest_forbidden', __('Private'), ['status' => 401]);
        }

        $post_id = self::quick_edit_requested_post_id($request);
        if ($post_id <= 0) {
            return true;
        }

        $post = get_post($post_id);
        if ($post && !current_user_can('edit_post', $post_id)) {
            return new \WP_Error('rest_forbidden', __('Private'), ['status' => 403]);
        }

        return true;
    }

    private static function quick_edit_requested_post_id(\WP_REST_Request $request): int {
        foreach (['post_id', 'parent_id'] as $parameter) {
            $post_id = absint($request->get_param($parameter));
            if ($post_id > 0) {
                return $post_id;
            }
        }

        return 0;
    }

    public static function public_access(\WP_REST_Request $request): bool {
        return true;
    }
}
