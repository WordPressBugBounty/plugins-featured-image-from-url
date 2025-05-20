<?php

define('FIFU_JETPACK_SIZES', serialize(array(75, 100, 150, 240, 320, 500, 640, 800, 1024, 1280, 1600)));

function fifu_resize_jetpack_image_size($size, $url) {
    if (strpos($url, 'wp.fifu.app/') !== false) {
        // Parse the URL to extract its components
        $parts = parse_url($url);
        $path_parts = explode('/', trim($parts['path'], '/'));
        $path_count = count($path_parts);

        // Extract query parameters (if any)
        $query = isset($parts['query']) ? $parts['query'] : '';
        parse_str($query, $query_params);

        // Add or update the size parameter in the query
        $query_params['w'] = $size;
        $query_params['h'] = 0;
        $query_params['c'] = 0;

        if ($path_count >= 4) {
            // The second-to-last element is the signature
            $signature_index = $path_count - 2;

            // Remove the signature from the path
            unset($path_parts[$signature_index]);

            // Rebuild the path without the signature
            $new_path = '/' . implode('/', $path_parts);

            // Rebuild the query string
            $new_query = http_build_query($query_params);

            // Create the unsigned URL to calculate a new signature
            $unsigned_url = '//' . $parts['host'] . $new_path . ($new_query ? '?' . $new_query : '');

            // Generate a new signature
            $new_signature = fifu_get_signature($unsigned_url, 'fifu');

            // Insert the new signature into the second-to-last position
            array_splice($path_parts, $signature_index, 0, $new_signature);

            // Rebuild the path with the new signature
            $final_path = '/' . implode('/', $path_parts);

            // Return the complete URL with the new signature
            return $parts['scheme'] . '://' . $parts['host'] . $final_path . ($new_query ? '?' . $new_query : '');
        }
        return $url;
    } else {
        $size = (int) $size;

        if (strpos($url, 'resize=')) {
            $aux = explode('resize=', $url)[1];
            $aux = explode(',', $aux);
            $w = isset($aux[0]) ? (int) $aux[0] : 0;
            $h = isset($aux[1]) ? (int) $aux[1] : 0;
            $new_h = $w ? intval($size * $h / $w) : 0;
            $clean_url = explode('?', $url)[0];
            if ($new_h == 0)
                return "{$clean_url}?w={$size}&ssl=1";
            else
                return "{$clean_url}?resize={$size},{$new_h}&ssl=1";
        }

        $del = strpos($url, "?") !== false ? "&" : "?";

        return "{$url}{$del}w={$size}&resize={$size}&ssl=1";
    }
}

function fifu_jetpack_get_set($url, $is_slider) {
    $quality = $is_slider ? 1.1 : 1;
    $set = '';
    $count = 0;
    foreach (unserialize(FIFU_JETPACK_SIZES) as $i)
        $set .= (($count++ != 0) ? ', ' : '') . fifu_resize_jetpack_image_size($i * $quality, $url) . ' ' . $i . 'w';
    return $set;
}

function fifu_jetpack_blocked($url) {
    if (!$url)
        return true;

    if (fifu_is_photon_url($url))
        return true;

    if (substr($url, -5) === '.avif')
        return true;

    $blocklist = array('localhost', 'amazon-adsystem.com', 'sapo.io', 'i.guim.co.uk', 'image.influenster.com', 'api.screenshotmachine.com', 'img.brownsfashion.com', 'fbcdn.net', 'nitrocdn.com', 'brightspotcdn.com', 'realtysouth.com', 'tiktokcdn.com', 'fdcdn.akamaized.net', 'blockchainstock.azureedge.net', 'aa.com.tr', 'cdn.discordapp.com', 'download.schneider-electric.com', 'images.twojjs.com', 'preview.redd.it', 'external-preview.redd.it', 'i.redd.it', 'cdn.fbsbx.com', 'canva.com', 'cdn.fifu.app', 'cloud.fifu.app', 'images.placeholders.dev');
    foreach ($blocklist as $domain) {
        if (strpos($url, $domain) !== false)
            return true;
    }
    return false;
}

function fifu_is_photon_url($url) {
    $list = array('i0.wp.com', 'i1.wp.com', 'i2.wp.com', 'i3.wp.com', 'wp.fifu.app');
    foreach ($list as $domain) {
        if (strpos($url, $domain) !== false)
            return true;
    }
    return false;
}

function fifu_jetpack_photon_url($url, $args, $att_id) {
    if (fifu_jetpack_blocked($url))
        return $url;

    if (fifu_ends_with($url, '.svg'))
        return $url;

    if (fifu_is_from_proxy_urls($url)) {
        return fifu_pubcdn_get_image_url($att_id, $url, $args);
    } else {
        $args['ssl'] = 1;

        $image_url_parts = wp_parse_url($url);
        if (!is_array($image_url_parts) || empty($image_url_parts['host']) || empty($image_url_parts['path']))
            return $url;
        $subdomain = abs(crc32($url) % 4);
        $host = $image_url_parts['host'];
        $path = $image_url_parts['path'];
        $query = isset($image_url_parts['query']) ? $image_url_parts['query'] : null;
        $query = $query ? '?' . $query : '';
        $photon_url = "https://i{$subdomain}.wp.com/{$host}{$path}{$query}";
        if ($args)
            return add_query_arg($args, $photon_url);
        return $photon_url;
    }
}

function fifu_pubcdn_get_image_url($att_id, $image_url, $qp) {
    if ($att_id) {
        $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
        $slug = $alt ? $alt : fifu_get_parent_slug($att_id);
        $post_id = get_post($att_id)->post_parent;
    } else {
        $slug = 'not-found';
        $post_id = null;
    }

    if ($post_id) {
        $qp = $qp ? $qp . '&' : '?';
        $qp .= 'p=' . $post_id;
    }

    $decoded_string = urldecode($slug);
    if (function_exists('transliterator_transliterate')) {
        $post_slug = sanitize_title(transliterator_transliterate('Any-Latin; Latin-ASCII', $decoded_string));
    } else {
        // Fallback: Remove non-ASCII characters and sanitize
        $fallback_slug = preg_replace('/[^\x20-\x7E]/u', '', $decoded_string);
        $post_slug = sanitize_title($fallback_slug);
    }

    $main_domain = explode('/', get_home_url())[2];

    $encoded_url = fifu_base64($image_url);
    $new_url = "//wp.fifu.app/" . $main_domain . "/" . $encoded_url . "/" . $post_slug . ".webp" . $qp;
    $signature = fifu_get_signature($new_url, 'fifu');
    return 'https:' . str_replace($encoded_url, $encoded_url . '/' . $signature, $new_url);
}

function fifu_get_signature($url, $token) {
    // Generate the HMAC-SHA256 of the URL using the token as the key
    $hash = hash_hmac('sha256', $url, $token, true);

    // Convert the hash to a hexadecimal representation and truncate it to 12 characters
    $signature = substr(bin2hex($hash), 0, 12);

    return $signature;
}

