<?php

define('FIFU_JETPACK_SIZES', serialize(array(75, 100, 150, 240, 320, 500, 640, 800, 1024, 1280, 1600)));

function fifu_resize_jetpack_image_size($size, $url) {
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

    $blocklist = array('localhost', 'plus.unsplash.com', 'amazon-adsystem.com', 'sapo.io', 'i.guim.co.uk', 'image.influenster.com', 'api.screenshotmachine.com', 'img.brownsfashion.com', 'fbcdn.net', 'nitrocdn.com', 'brightspotcdn.com', 'realtysouth.com', 'tiktokcdn.com', 'fdcdn.akamaized.net', 'blockchainstock.azureedge.net', 'aa.com.tr', 'cdn.discordapp.com', 'download.schneider-electric.com', 'images.twojjs.com', 'preview.redd.it', 'external-preview.redd.it', 'i.redd.it', 'cdn.fbsbx.com', 'canva.com', 'cdn.fifu.app', 'cloud.fifu.app', 'images.placeholders.dev');
    foreach ($blocklist as $domain) {
        if (strpos($url, $domain) !== false)
            return true;
    }
    return false;
}

function fifu_is_photon_url($url) {
    $list = array('i0.wp.com', 'i1.wp.com', 'i2.wp.com', 'i3.wp.com');
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

