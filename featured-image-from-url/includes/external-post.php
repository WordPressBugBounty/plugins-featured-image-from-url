<?php

add_action('save_post', 'fifu_save_properties_ext');

function fifu_save_properties_ext($post_id) {
    if (isset($_POST['fifu_input_url']))
        return;

    $first = fifu_first_url_in_content($post_id);
    $image_url = $first ? esc_url_raw(rtrim($first)) : null;

    if ((($_POST['action'] ?? '') != 'elementor_ajax') && $image_url && fifu_is_on('fifu_get_first') && !fifu_has_local_featured_image($post_id) && fifu_is_valid_cpt($post_id)) {
        update_post_meta($post_id, 'fifu_image_url', fifu_convert($image_url));
        fifu_db_update_fake_attach_id($post_id);
        return;
    }

    if (!$image_url && get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url')) {
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
    foreach (($matches[0] ?? []) as $tag) {
        if (($tag && strpos($tag, 'data:image/jpeg') !== false))
            continue;

        $src = fifu_get_attribute('src', $tag);
        // resolve to absolute URL (supports relative and protocol-relative)
        $abs = fifu_resolve_absolute_url($post_id, $src);
        if (!$abs)
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
    $abs = fifu_resolve_absolute_url($post_id, $src);
    if (!$abs)
        return null;

    return $abs;
}

// Resolve relative or protocol-relative URLs to absolute using the post permalink as base
function fifu_resolve_absolute_url($post_id, $url) {
    $url = trim((string) $url);
    if ($url === '')
        return null;

    // ignore data URIs
    if (stripos($url, 'data:') === 0)
        return null;

    // already absolute
    if (preg_match('/^https?:\/\//i', $url))
        return $url;

    // looks like a domain without scheme (e.g., youtu.be/abc, www.example.com/a)
    if (preg_match('/^(?:www\.)?[a-z0-9.-]+\.[a-z]{2,}(?::\d+)?(?:\/.+)?$/i', $url)) {
        $scheme = is_ssl() ? 'https' : 'http';
        return $scheme . '://' . ltrim($url, '/');
    }

    // protocol-relative (e.g., //example.com/img.jpg)
    if (strpos($url, '//') === 0) {
        $scheme = is_ssl() ? 'https:' : 'http:';
        return $scheme . $url;
    }

    // base for resolution: post permalink or site home
    $base = get_permalink($post_id);
    if (!$base)
        $base = home_url('/');

    $base_parts = wp_parse_url($base);
    if (!$base_parts || empty($base_parts['host']))
        return null;

    $scheme = $base_parts['scheme'] ?? (is_ssl() ? 'https' : 'http');
    $host = $base_parts['host'];
    $port = isset($base_parts['port']) ? ':' . $base_parts['port'] : '';
    $base_path = $base_parts['path'] ?? '/';

    // same-host absolute-path reference
    if (isset($url[0]) && $url[0] === '/') {
        $path = $url;
        $path = fifu_remove_dot_segments($path);
        return $scheme . '://' . $host . $port . $path;
    }

    // query-only or fragment-only reference
    if (isset($url[0]) && ($url[0] === '?' || $url[0] === '#')) {
        return $scheme . '://' . $host . $port . $base_path . $url;
    }

    // relative path reference
    $dir = (substr($base_path, -1) === '/') ? rtrim($base_path, '/') : rtrim(dirname($base_path), '/');
    if ($dir === '/' || $dir === '\\')
        $dir = '';
    $path = ($dir ? $dir : '') . '/' . $url;
    $path = fifu_remove_dot_segments($path);

    return $scheme . '://' . $host . $port . $path;
}

function fifu_remove_dot_segments($path) {
    $leading_slash = (strlen($path) > 0 && $path[0] === '/');
    $segments = explode('/', $path);
    $output = [];
    foreach ($segments as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            array_pop($output);
            continue;
        }
        $output[] = $seg;
    }
    $normalized = ($leading_slash ? '/' : '') . implode('/', $output);
    // preserve trailing slash if original had it and not query/fragment
    if ($normalized !== '/' && substr($path, -1) === '/')
        $normalized .= '/';
    return $normalized === '' ? '/' : $normalized;
}

function fifu_update_fake_attach_id($post_id) {
    fifu_db_update_fake_attach_id($post_id);
}

