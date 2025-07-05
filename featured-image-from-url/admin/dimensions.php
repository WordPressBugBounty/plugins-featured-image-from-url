<?php

define('PROXY_URLS', [
    "https://drive.google.com",
    "https://drive.usercontent.google.com",
    "https://lh3.googleusercontent.com",
    "https://s.yimg.com",
    "https://s1.yimg.com",
    "https://blockworks.co",
    "https://coincodex.com",
    "https://www.ft.com",
    "https://cdn.sellio.net",
    "https://cf.bstatic.com",
    "https://media-cdn.oriflame.com",
    "https://i.ytimg.com",
    "https://cdn.myshoptet.com",
    "https://i.imgur.com",
    "https://a1.espncdn.com",
    "https://books.google.com",
    "https://embed-cdn.gettyimages.com",
    "https://media.gettyimages.com",
    "https://forum.rolug.ro",
    "https://assets.ellosgroup.com",
    "https://www.nzherald.co.nz",
    "https://img.youtube.com",
    "https://cdn.diariodeavisos.com",
]);

function fifu_image_downsize($out, $att_id, $size) {
    global $FIFU_SESSION;

    if (!$att_id || !fifu_is_remote_image($att_id)) {
        return $out;
    }

    if (fifu_is_off('fifu_photon')) {
        return $out;
    }

    fifu_update_cdn_stats();

    $original_image_url = get_post_meta($att_id, '_wp_attached_file', true);
    if ($original_image_url) {
        if (strpos($original_image_url, "https://thumbnails.odycdn.com") !== 0 &&
                strpos($original_image_url, "https://res.cloudinary.com/glide/") !== 0 &&
                fifu_jetpack_blocked($original_image_url)) {
            return $out;
        }
    }

    if (fifu_ends_with($original_image_url, '.svg'))
        return $out;

    if (fifu_is_from_speedup($original_image_url))
        return $out;

    $image_url = $original_image_url;

    $size_details = fifu_get_image_size_details($size);
    $width = $size_details['width'];
    $height = $size_details['height'];
    $crop = $size_details['crop'];

    // Check if the requested size is "full"
    if ($size === 'full') {
        // Check if dimensions are already saved
        $metadata = wp_get_attachment_metadata($att_id);
        if (!empty($metadata['width']) && !empty($metadata['height'])) {
            $original_width = intval($metadata['width']);
            $original_height = intval($metadata['height']);
            $aspect_ratio = $original_height / $original_width;
            $max_dimension = 1920;

            if ($original_width > $original_height) {
                // Landscape or square image
                $new_width = min($original_width, $max_dimension);
                $new_height = intval($new_width * $aspect_ratio);
            } else {
                // Portrait image
                $new_height = min($original_height, $max_dimension);
                $new_width = intval($new_height / $aspect_ratio);
            }

            $new_url = fifu_resize_with_photon($image_url, $new_width, $new_height, $crop, $att_id, $size);

            $FIFU_SESSION['cdn-new-old'][$new_url] = $original_image_url;
            return array($new_url, $new_width, $new_height, true);
        } else {
            return $out;
        }
    } else {
        if (!$width && !$height)
            return $out;

        $new_url = fifu_resize_with_photon($image_url, $width, $height, $crop, $att_id, $size);

        $FIFU_SESSION['cdn-new-old'][$new_url] = $original_image_url;
        return array($new_url, $width, $height, true);
    }
}

add_filter('image_downsize', 'fifu_image_downsize', 10, 3);

function fifu_resize_with_photon($url, $width, $height, $crop, $att_id, $size) {
    if (!$width && !$height) {
        $size_details = fifu_get_image_size_details($size);
        $width = $size_details['width'];
        $height = $size_details['height'];
        $crop = $size_details['crop'];
    }

    if (fifu_is_from_proxy_urls($url)) {
        if (!$width && !$height) {
            $url = fifu_pubcdn_get_image_url($att_id, $url, null);
            return $url;
        }

        $w = $width;
        $h = $height;

        // force a square on WooCommerce when either width or height is undefined.
        if (is_string($size) && strpos($size, 'woocommerce') !== false) {
            $h = $h == 0 ? $w : $h;
            $w = $w == 0 ? $h : $w;
        }

        $c = is_null($crop) ? 0 : (int) $crop;

        $url = fifu_pubcdn_get_image_url($att_id, $url, "?w={$w}&h={$h}&c={$c}");
        return $url;
    } else {
        $photon_base_url = "https://i" . (hexdec(substr(md5($url), 0, 1)) % 4) . ".wp.com/";

        $delimiter = strpos($url, "?") !== false ? '&' : '?';

        if (strpos($url, "wp.com/mshots") !== false || strpos($url, "screenshot.fifu.app") !== false) {
            $crop = "&crop=0px,0px,{$width}px,{$height}px";
        } else {
            $resize_param = $height == 9999 ? "{$width}" : "{$width},{$height}";
            $crop = "&resize={$resize_param}";
        }

        $ssl_param = '&ssl=1';

        return $photon_base_url . preg_replace('#^https?://#', '', $url) . "{$delimiter}w={$width}{$crop}{$ssl_param}";
    }
}

function fifu_is_from_proxy_urls($original_image_url) {
    // $cdn_count = get_transient('fifu_stats_cdn_count') ?? 0;
    // $cdn_count = intval($cdn_count);
    // if ($cdn_count > 0 && $cdn_count <= 100) {
    //     return true;
    // }

    foreach (PROXY_URLS as $url) {
        if (strpos($original_image_url, $url) === 0) {
            return true;
        }
    }
    return false;
}

add_filter('image_downsize', 'fifu_detect_image_size_usage', 10, 3);

function fifu_detect_image_size_usage($image, $id, $size) {
    $page_type = 'unknown';

    // Primary checks
    if (is_front_page()) {
        $page_type = "front page";
    } elseif (is_plugin_active('woocommerce/woocommerce.php')) {
        // WooCommerce-specific checks
        if (function_exists('is_shop') && is_shop()) {
            $page_type = "shop";
        } elseif (function_exists('is_product') && is_product()) {
            $page_type = "product";
        } elseif (function_exists('is_product_category') && is_product_category()) {
            $page_type = "product category";
        } elseif (function_exists('is_product_tag') && is_product_tag()) {
            $page_type = "product tag";
        } elseif (function_exists('is_cart') && is_cart()) {
            $page_type = "cart";
        } elseif (function_exists('is_checkout') && is_checkout()) {
            $page_type = "checkout";
        } elseif (function_exists('is_account_page') && is_account_page()) {
            $page_type = "account";
        } elseif (function_exists('is_order_received_page') && is_order_received_page()) {
            $page_type = "order received";
        }
    }

    // Universal WordPress checks
    if ($page_type === "unknown") {
        if (is_home()) {
            $page_type = "blog home";
        } elseif (is_category()) {
            $page_type = "category";
        } elseif (is_tag()) {
            $page_type = "tag";
        } elseif (is_tax()) {
            $page_type = "taxonomy";
        } elseif (is_single()) {
            $page_type = "single post";
        } elseif (is_page()) {
            $page_type = "page";
        } elseif (is_archive()) {
            $page_type = "archive";
        } elseif (is_author()) {
            $page_type = "author";
        } elseif (is_search()) {
            $page_type = "search";
        } elseif (is_404()) {
            $page_type = "404";
        } elseif (is_attachment()) {
            $page_type = "attachment";
        }
    }

    // Get the option key for this size
    $option_key = fifu_get_size_option_key($size);

    // Get existing data or create default
    $default_data = [
        'w' => 0,
        'h' => 0,
        'c' => false,
        'pages' => []
    ];

    // For string sizes, get dimensions from registered sizes if available
    if (is_string($size)) {
        $registered_sizes = wp_get_registered_image_subsizes();
        if (array_key_exists($size, $registered_sizes)) {
            $default_data['w'] = $registered_sizes[$size]['width'];
            $default_data['h'] = $registered_sizes[$size]['height'];
            $default_data['c'] = $registered_sizes[$size]['crop'];
        }
    }
    // For array sizes, use the array values
    elseif (is_array($size) && count($size) >= 2) {
        $default_data['w'] = (int) $size[0];
        $default_data['h'] = (int) $size[1];
        $default_data['c'] = isset($size[2]) ? (bool) $size[2] : false;
    } else {
        return $image; // Invalid size format
    }

    $current = get_option($option_key, $default_data);

    // Update pages array if we have a valid page type
    if (!in_array($page_type, $current['pages'])) {
        $current['pages'][] = $page_type;
        update_option($option_key, $current);
    }

    return $image;
}

function fifu_get_size_option_key($size) {
    if (is_string($size))
        return empty($size) ? "fifu_detected_size_empty" : "fifu_detected_size_{$size}";

    if (is_array($size) && count($size) >= 2) {
        $w = (int) $size[0];
        $h = (int) $size[1];
        $c = isset($size[2]) ? (bool) $size[2] : false;
        return "fifu_detected_size_{$w}x{$h}x" . ($c ? '1' : '0');
    }

    return "fifu_detected_size_unknown";
}

function fifu_get_defined_size_key($size) {
    if (is_string($size) && strpos($size, 'fifu_detected_size_') === 0)
        return str_replace('fifu_detected_size_', 'fifu_defined_size_', $size);

    $detected_key = fifu_get_size_option_key($size);
    return str_replace('fifu_detected_size_', 'fifu_defined_size_', $detected_key);
}

function fifu_get_size_name_from_key($option_key) {
    if (strpos($option_key, 'fifu_detected_size_') === 0)
        return substr($option_key, strlen('fifu_detected_size_'));

    if (strpos($option_key, 'fifu_defined_size_') === 0)
        return substr($option_key, strlen('fifu_defined_size_'));

    return $option_key;
}

function fifu_update_cdn_stats() {
    if (fifu_is_bot_request())
        return;

    $date = new DateTime();
    $date = $date->format('Y-m-d');
    $stats_date = get_option('fifu_stats_date');
    if (!$stats_date) {
        update_option('fifu_stats_date', $date);
        // set_transient('fifu_stats_cdn_count', 1, 0);
    } else {
        if ($stats_date == $date) {
            // $cdn_count = get_transient('fifu_stats_cdn_count') ?? 0;
            // set_transient('fifu_stats_cdn_count', $cdn_count + 1, 0);
        } else {
            fifu_send_cdn_stats();
            delete_option('fifu_stats_date');
            // delete_transient('fifu_stats_cdn_count');
        }
    }
}

function fifu_send_cdn_stats() {
    // Get the stats data
    $date = get_option('fifu_stats_date');
    if (!$date)
        return false;

    $num_urls = fifu_db_count_urls();
    $num_cdn = get_transient('fifu_stats_cdn_count') ?? 0;

    // Create a unique site identifier using domain name
    $site_url = parse_url(get_site_url(), PHP_URL_HOST);
    $site_id = md5($site_url);

    // Prepare the data to send
    $data = array(
        'id' => $site_id,
        'num_urls' => $num_urls,
        'num_cdn' => $num_cdn,
        'date' => $date,
        'slug' => FIFU_SLUG,
        'version' => (int) str_replace('.', '', fifu_version_number()),
        'theme' => wp_get_theme()->get('Name')
    );

    // API endpoint
    $api_url = 'https://i0.fifu.app/stats';

    // Send the POST request
    $response = wp_remote_post($api_url, array(
        'method' => 'POST',
        'timeout' => 15,
        'redirection' => 5,
        'httpversion' => '1.1',
        'blocking' => false,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($data),
        'cookies' => array(),
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        error_log('FIFU stats sending error: ' . $response->get_error_message());
        return false;
    }

    return true;
}

function fifu_is_bot_request() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (empty($user_agent)) {
        return true;
    }

    $bot_keywords = array(
        'bot', 'crawl', 'spider', 'slurp', 'search', 'archive', 'scrap',
        'lighthouse', 'pingdom', 'pagespeed', 'monitor', 'whatsapp', 'facebook',
        'snippet', 'headless', 'python', 'perl', 'urllib', 'curl', 'wget',
        'semrush', 'ahrefs', 'screaming', 'phantom', 'zgrab', 'siteaudit', 'time.news'
    );

    foreach ($bot_keywords as $keyword) {
        if (stripos($user_agent, $keyword) !== false) {
            return true;
        }
    }

    $browser_keywords = array('chrome', 'firefox', 'safari', 'opera', 'edge', 'msie', 'trident');
    $has_browser_keyword = false;

    foreach ($browser_keywords as $keyword) {
        if (stripos($user_agent, $keyword) !== false) {
            $has_browser_keyword = true;
            break;
        }
    }

    if (!$has_browser_keyword) {
        return true;
    }

    if (!isset($_SERVER['HTTP_ACCEPT']) || empty($_SERVER['HTTP_ACCEPT'])) {
        return true;
    }

    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return true;
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }
    }

    return false;
}

function fifu_get_image_size_details($size) {
    // Check for defined sizes first
    $defined = fifu_get_defined_size_key($size);
    if ($defined) {
        $defined_data = get_option($defined);
        if ($defined_data) {
            return [
                'width' => intval($defined_data['w']),
                'height' => intval($defined_data['h']),
                'crop' => intval($defined_data['c']),
            ];
        }
    }

    // Default values if no defined size is found
    $default_width = 0;
    $default_height = 0;
    $default_crop = 0;

    $image_sizes = get_intermediate_image_sizes();
    $registered_sizes = wp_get_registered_image_subsizes();

    $width = $default_width;
    $height = $default_height;
    $crop = $default_crop;

    if (is_array($size)) {
        $width = isset($size[0]) && is_numeric($size[0]) ? intval($size[0]) : $default_width;
        $height = isset($size[1]) && is_numeric($size[1]) ? intval($size[1]) : $default_height;
        $crop = isset($size[2]) ? (boolval($size[2]) ? 1 : 0) : $default_crop;
    } elseif (is_string($size) && in_array($size, $image_sizes, true)) {
        if (isset($registered_sizes[$size])) {
            $width = intval($registered_sizes[$size]['width']);
            $height = intval($registered_sizes[$size]['height']);
            $crop = intval(boolval($registered_sizes[$size]['crop']));
        } else {
            $width = intval(get_option("{$size}_size_w", $default_width));
            $height = intval(get_option("{$size}_size_h", $default_height));
            $crop = intval(get_option("{$size}_size_crop", $default_crop));
        }
    }

    return [
        'width' => $width,
        'height' => $height,
        'crop' => $crop,
    ];
}

function fifu_is_cdn_url($url) {
    if (strpos($url, "https://thumbnails.odycdn.com") === 0)
        return true;

    if (strpos($url, "https://res.cloudinary.com/glide/") === 0)
        return true;

    for ($i = 0; $i <= 3; $i++) {
        if (strpos($url, "https://i{$i}.wp.com") === 0)
            return true;
    }

    return false;
}

