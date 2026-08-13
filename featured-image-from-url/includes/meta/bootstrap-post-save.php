<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post', static function ($postId, $post, $update): void {
    if (empty($_REQUEST['fifu_post_save_pending'])) {
        return;
    }

    $postId = is_numeric($postId)
        ? (int) $postId
        : 0;

    if (
        $postId <= 0
        || !$post instanceof WP_Post
    ) {
        return;
    }

    $expectedPostId = (int) ($_REQUEST['fifu_post_save_id'] ?? 0);
    if ($expectedPostId !== $postId) {
        return;
    }

    $ignore = !empty($_REQUEST['fifu_post_save_ignore']);
    $request = $_REQUEST;

    unset(
        $_REQUEST['fifu_post_save_pending'],
        $_REQUEST['fifu_post_save_id'],
        $_REQUEST['fifu_post_save_ignore']
    );

    Fifu_Post_Save_Service::save_from_editor($postId, $request, $ignore);
}, 20, 3);
