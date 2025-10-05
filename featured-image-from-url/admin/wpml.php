<?php

function fifu_wpml_copy_prefixed_post_meta($source_id, $target_id) {
    if (!$source_id || !$target_id || $source_id === $target_id) {
        return;
    }

    $image_url = get_post_meta($source_id, 'fifu_image_url', true);
    if (is_array($image_url)) {
        $image_url = reset($image_url);
    }

    $image_url = is_string($image_url) ? trim($image_url) : '';

    fifu_dev_set_image($target_id, $image_url);
}

add_action('wcml_after_duplicate_product_post_meta', function ($original_id, $translated_id, $data = false) {
    fifu_wpml_copy_prefixed_post_meta($original_id, $translated_id);
}, 10, 3);

add_action('wcml_after_sync_product_data', function ($original_id, $translated_id, $language) {
    fifu_wpml_copy_prefixed_post_meta($original_id, $translated_id);
}, 10, 3);

add_action('icl_make_duplicate', function ($source_id, $lang, $post_array, $duplicate_id) {
    $post_type = get_post_type($source_id);

    if (!$post_type) {
        return;
    }

    fifu_wpml_copy_prefixed_post_meta($source_id, $duplicate_id);
}, 10, 4);

