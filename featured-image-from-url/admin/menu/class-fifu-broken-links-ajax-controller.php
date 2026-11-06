<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Supplies broken featured-image URLs to the Link Checker table.
 */
final class Fifu_Broken_Links_Ajax_Controller
{
    public static function register_hooks(): void
    {
        add_action('wp_ajax_fifu_broken_links', [self::class, 'handle_request']);
    }

    public static function handle_request(): void
    {
        check_ajax_referer('fifu_broken_links', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to view broken links.', FIFU_SLUG)], 403);
        }

        $repository = new Fifu_Url_Validation_Repository();
        $rows = [];
        foreach ($repository->get_broken_featured_posts() as $post) {
            $postId = (int) ($post['post_id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $editUrl = get_edit_post_link($postId);
            if (!is_string($editUrl) || $editUrl === '') {
                $editUrl = admin_url('post.php?post=' . $postId . '&action=edit');
            }

            $rows[] = [
                sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url($editUrl), esc_html((string) ($post['post_title'] ?? ''))),
                sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url((string) ($post['url'] ?? '')), esc_html((string) ($post['url'] ?? ''))),
            ];
        }

        wp_send_json(['data' => $rows]);
    }
}
