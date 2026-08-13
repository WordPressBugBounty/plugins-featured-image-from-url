<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Comments must be in English.
// Registers lifecycle hooks that keep FIFU attachments in sync.
add_action(
    'before_delete_post',
    [
        Fifu_Post_Attachment_Sync_Service::class,
        'handle_post_deleted',
    ]
);

add_action(
    'wp_insert_post',
    static function (
        $post_id,
        $post,
        $update
    ): void {
        $post_id = is_numeric($post_id) ? (int) $post_id : 0;

        if ($post_id <= 0 || !$post instanceof \WP_Post) {
            return;
        }

        $postId = (int) ($post->ID ?? 0);

        if ($postId <= 0) {
            return;
        }

        $action = $_POST['action'] ?? '';

        if (
            wp_is_post_revision(
                $postId
            )
        ) {
            return;
        }

        if (
            $action
            === 'woocommerce_do_ajax_product_import'
        ) {
            return;
        }

        if (
            ($post->post_type ?? '') === 'product'
            && isset($_POST['woocommerce_meta_nonce'])
        ) {
            return;
        }

        Fifu_Post_Attachment_Sync_Service::
            sync_featured_attachment($postId);
    },
    10,
    3
);
