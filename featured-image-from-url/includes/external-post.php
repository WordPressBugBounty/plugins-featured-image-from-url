<?php

add_action('save_post', 'fifu_save_properties_ext');

function fifu_save_properties_ext($post_id) {
    if (isset($_POST['fifu_input_url']))
        return;

    $first = fifu_first_url_in_content($post_id);
    $url = $first ? esc_url_raw(rtrim($first)) : null;

    if ((!isset($_POST['action']) || $_POST['action'] != 'elementor_ajax') && $url && fifu_is_on('fifu_get_first') && !fifu_has_local_featured_image($post_id) && fifu_is_valid_cpt($post_id)) {
        update_post_meta($post_id, 'fifu_image_url', fifu_convert($url));
        fifu_db_update_fake_attach_id($post_id);
        return;
    }

    if (!$url && get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url')) {
        if (fifu_is_valid_default_cpt($post_id))
            fifu_db_update_fake_attach_id($post_id);
    }

    /* image url from slotslauch */
    if (fifu_is_slotslaunch_active()) {
        $url = esc_url_raw(rtrim(get_post_meta($post_id, 'slimg', true)));
        if ($url)
            fifu_dev_set_image($post_id, $url);
    }
}

function fifu_first_url_in_content($post_id) {
    $content = get_post_field('post_content', $post_id);
    $content = html_entity_decode($content);
    if (!$content)
        return;

    $matches = array();

    preg_match_all('/<img[^>]*>/', $content, $matches);

    if (sizeof($matches) == 0)
        return;

    // $matches
    $tag = null;
    foreach ($matches[0] as $tag) {
        if (($tag && strpos($tag, 'data:image/jpeg') !== false))
            continue;

        $src = fifu_get_attribute('src', $tag);
        if (!preg_match('/^https?:\/\//', $src))
            continue;

        // skip
        $skip_list = get_option('fifu_skip');
        if ($skip_list) {
            $skip = false;
            foreach (explode(',', $skip_list) as $word) {
                if (strpos($tag, $word) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip)
                continue;
        }

        break;
    }

    if (!$tag)
        return null;

    // src
    $src = fifu_get_attribute('src', $tag);

    if (!preg_match('/^https?:\/\//', $src))
        return null;

    return $src;
}

function fifu_update_fake_attach_id($post_id) {
    fifu_db_update_fake_attach_id($post_id);
}

