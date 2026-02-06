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

add_action('wpml_after_copy_custom_field', function ($from_id, $to_id, $meta_key) {

    if ($meta_key !== 'fifu_image_url') {
        return;
    }

    if (!function_exists('fifu_dev_set_image')) {
        return;
    }

    $url = get_post_meta($to_id, 'fifu_image_url', true);
    if (is_array($url)) {
        $url = reset($url);
    }
    $url = is_string($url) ? trim($url) : '';

    if ($url === '') {
        return;
    }

    fifu_dev_set_image((int) $to_id, $url);
}, PHP_INT_MAX, 3);