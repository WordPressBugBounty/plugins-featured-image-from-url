<?php

define('FIFU_PLACEHOLDER', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

add_filter('wp_head', 'fifu_add_js');

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

global $pagenow;
if (!in_array($pagenow, array('post.php', 'post-new.php', 'admin-ajax.php', 'wp-cron.php'))) {
    if (is_plugin_active('wordpress-seo/wp-seo.php')) {
        add_action('wpseo_opengraph_image', 'fifu_add_social_tag_yoast');
        add_action('wpseo_twitter_image', 'fifu_add_social_tag_yoast');
        add_action('wpseo_add_opengraph_images', 'fifu_add_social_tag_yoast_list');
    } else
        add_filter('wp_head', 'fifu_add_social_tags');
}

add_action('wp_head', 'fifu_home_add_social_tags', 9999);

add_filter('wp_head', 'fifu_apply_css');

function fifu_add_js() {
    if (fifu_is_amp_request())
        return;

    if (fifu_su_sign_up_complete()) {
        echo '<link rel="preconnect" href="https://cloud.fifu.app">';
        echo '<link rel="preconnect" href="https://cdn.fifu.app">';
    }

    if (fifu_is_on('fifu_photon')) {
        for ($i = 0; $i <= 3; $i++) {
            echo "<link rel='dns-prefetch' href='https://i{$i}.wp.com/'>";
            echo "<link rel='preconnect' href='https://i{$i}.wp.com/' crossorigin>";
        }
    }

    if (class_exists('WooCommerce')) {
        wp_register_style('fifu-woo', plugins_url('/html/css/woo.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-woo');
        wp_add_inline_style('fifu-woo', 'img.zoomImg {display:' . fifu_woo_zoom() . ' !important}');
    }

    // js
    if (fifu_is_flatsome_active() || class_exists('WooCommerce')) {
        wp_enqueue_script('fifu-image-js', plugins_url('/html/js/image.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-image-js', 'fifuImageVars', [
            'fifu_woo_lbox_enabled' => fifu_woo_lbox(),
            'fifu_is_product' => class_exists('WooCommerce') && is_product(),
            'fifu_is_flatsome_active' => fifu_is_flatsome_active(),
        ]);
    }
}

function fifu_add_social_tag_yoast($image_url) {
    if (get_post_meta(get_the_ID(), '_yoast_wpseo_opengraph-image', true) || get_post_meta(get_the_ID(), '_yoast_wpseo_twitter-image', true))
        return $image_url;
    $url = fifu_main_image_url(get_the_ID(), true);
    return $url ? $url : $image_url;
}

function fifu_add_social_tag_yoast_list($object) {
    if (get_post_meta(get_the_ID(), '_yoast_wpseo_opengraph-image', true) || get_post_meta(get_the_ID(), '_yoast_wpseo_twitter-image', true))
        return;
    $object->add_image(fifu_main_image_url(get_the_ID(), true));
}

function fifu_add_social_tags() {
    if (is_front_page() || is_home())
        return;

    $post_id = get_the_ID();
    $url = fifu_main_image_url($post_id, true);
    $url = $url ? $url : get_the_post_thumbnail_url($post_id, 'large');
    $title = str_replace("'", "&#39;", strip_tags(get_the_title($post_id)));
    $description = str_replace("'", "&#39;", wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));

    if ($url) {
        if (fifu_is_from_speedup($url))
            $url = fifu_speedup_get_signed_url($url, 1280, 672, null, null, false);
        elseif (fifu_is_on('fifu_photon')) {
            $url = fifu_cdn_adjust($url);
            $url = fifu_jetpack_photon_url($url, null, get_post_thumbnail_id($post_id));
        }
        include 'html/og-image.html';

        wp_enqueue_script('fifu-json-ld', plugins_url('/html/js/json-ld.js', __FILE__), array(), fifu_version_number_enq());
        wp_localize_script('fifu-json-ld', 'fifuJsonLd', [
            'url' => $url,
        ]);
    }

    if ($url) {
        if (fifu_is_from_speedup($url))
            $url = fifu_speedup_get_signed_url($url, 1280, 672, null, null, false);
        include 'html/twitter-image.html';
    }
}

function fifu_home_add_social_tags() {
    if (is_front_page()) {
        $url = get_option('fifu_default_url');
        if (!empty($url)) {
            $buffer_contents = ob_get_contents();
            if (strpos($buffer_contents, '<meta property="og:image"') === false) {
                $url = esc_url($url);
                include 'html/social-home.html';
            }
        }
    }
}

function fifu_apply_css() {
    if (fifu_is_off('fifu_wc_lbox'))
        echo '<style>[class$="woocommerce-product-gallery__trigger"] {display:none !important;}</style>';
}

add_filter('wp_get_attachment_image_attributes', 'fifu_wp_get_attachment_image_attributes', 10, 3);

function fifu_wp_get_attachment_image_attributes($attr, $attachment, $size) {
    global $FIFU_SESSION;

    // ignore themes
    if (in_array(strtolower(get_option('template')), array('jnews')))
        return $attr;

    if (!isset($attr['src']))
        return $attr;

    $url = $attr['src'];
    if (strpos($url, 'cdn.fifu.app') === false)
        return $attr;

    // "all products" page
    if (function_exists('get_current_screen') && isset(get_current_screen()->parent_file) && get_current_screen()->parent_file == 'edit.php?post_type=product') {
        $attr['src'] = fifu_optimized_column_image($url, $attachment->ID);
        return $attr;
    }

    $sizes = fifu_speedup_get_sizes($url);
    $width = $sizes[0];
    $height = $sizes[1];
    $is_video = $sizes[2];
    $clean_url = $sizes[3];

    $attr['src'] = fifu_speedup_get_signed_url($url, $width, $height, null, null, false);
    $attr['loading'] = 'lazy';
    $attr['srcset'] = fifu_speedup_get_set($url);

    return $attr;
}

add_filter('woocommerce_product_get_image', 'fifu_woo_replace', 10, 5);

function fifu_woo_replace($html, $product, $woosize, $attr, $placeholder) {
    if (empty($product) || !is_object($product))
        return $html;
    return fifu_replace($html, $product->get_id(), null, null, null);
}

add_filter('post_thumbnail_html', 'fifu_replace', 10, 5);

function fifu_replace($html, $post_id, $post_thumbnail_id, $size, $attr = null) {
    global $FIFU_SESSION;

    if (!$html)
        return $html;

    $width = fifu_get_attribute('width', $html);
    $height = fifu_get_attribute('height', $html);

    $src = fifu_get_attribute('src', $html);
    if (isset($FIFU_SESSION[$src])) {
        $data = $FIFU_SESSION[$src];
        if (strpos($html, 'fifu-replaced') !== false)
            return $html;
    }

    $url = get_post_meta($post_id, 'fifu_image_url', true);

    $delimiter = fifu_get_delimiter('src', $html);
    $alt = get_post_meta($post_id, 'fifu_image_alt', true);
    if (!$alt) {
        $alt = strip_tags(get_the_title($post_id));
        $custom_alt = 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $alt . $delimiter;
        $html = preg_replace('/alt=[\'\"][^[\'\"]*[\'\"]/', $custom_alt, $html);
        $html = fifu_check_alt_attribute($html, $custom_alt);
    } else {
        $alt = strip_tags($alt);
        if ($url && $alt) {
            $html = preg_replace('/alt=[\'\"][^[\'\"]*[\'\"]/', 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $alt . $delimiter, $html);
        }
    }

    if ($url)
        return $html;

    // hide internal featured images
    if (!$url && fifu_should_hide())
        return '';

    return !$url ? $html : fifu_get_html($url, $alt, $width, $height);
}

function fifu_check_alt_attribute($html, $custom_alt) {
    // Get the `<img>` tag in the string.
    $imgTag = preg_match('/<img (.+?)\/?>/', $html, $matches);

    if (!isset($matches[1]))
        return $html;

    // Check if the `<img>` tag has an alt attribute.
    $attributes = $matches[1];

    // If the alt attribute is empty, add it
    if (!preg_match('/alt=[\'\"][^[\'\"]*[\'\"]/', $attributes))
        $html = str_replace("<img ", "<img {$custom_alt} ", $html);

    return $html;
}

function fifu_get_html($url, $alt, $width, $height) {
    $css = '';
    if (fifu_should_hide()) {
        $css = 'display:none';
    }

    return sprintf('<img src="%s" alt="%s" title="%s" style="%s" data-large_image="%s" data-large_image_width="%s" data-large_image_height="%s" onerror="%s" width="%s" height="%s">', $url, $alt, $alt, $css, $url, "800", "600", "jQuery(this).hide();", $width, $height);
}

add_filter('the_content', 'fifu_remove_content_image');

function fifu_remove_content_image($content) {
    if (fifu_is_off('fifu_pcontent_remove'))
        return $content;

    $post_types_string = get_option('fifu_pcontent_types');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return $content;

    global $post;
    $post_id = $post->ID;
    $att_id = get_post_thumbnail_id($post_id);
    $att_url = wp_get_attachment_url($att_id);

    if (!empty($att_url)) {
        $pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
        preg_match_all($pattern, $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $content_img_url = html_entity_decode($match);
                if ($content_img_url == $att_url) {
                    $content = preg_replace('/<img[^>]+src=[\'"]' . preg_quote($match, '/') . '[\'"][^>]*>/i', '', $content, 1);
                    return $content;
                }
            }
        }
    }
    return $content;
}

add_filter('the_content', 'fifu_add_to_content');

function fifu_add_to_content($content) {
    if (fifu_is_off('fifu_pcontent_add'))
        return $content;

    $post_types_string = get_option('fifu_pcontent_types');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return $content;

    if (has_post_thumbnail())
        return '<div style="text-align:center">' . get_the_post_thumbnail() . '</div>' . $content;

    return $content;
}

add_filter('the_content', 'fifu_optimize_content');

function fifu_optimize_content($content) {
    if (fifu_is_off('fifu_cdn_content') || empty($content))
        return $content;

    wp_register_style('fifu-lazyload-style', plugins_url('/html/css/lazyload.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-lazyload-style');
    wp_enqueue_script('fifu-lazyload-js', plugins_url('/html/js/lazyload.js', __FILE__), array('jquery'), fifu_version_number_enq());

    global $post;
    $post_id = $post->ID;

    $srcType = "src";
    $imgList = array();
    preg_match_all('/<img[^>]*>/', $content, $imgList);

    foreach ($imgList[0] as $imgItem) {
        preg_match('/(' . $srcType . ')([^\'\"]*[\'\"]){2}/', $imgItem, $src);
        if (!$src)
            continue;

        $del = substr($src[0], - 1);
        $url = fifu_normalize(explode($del, $src[0])[1]);

        $url = fifu_cdn_adjust($url);

        if (!$url || fifu_jetpack_blocked($url) || strpos($url, 'data:image') === 0)
            continue;

        $new_url = fifu_jetpack_photon_url($url, null, get_post_thumbnail_id($post_id));
        $newImgItem = str_replace($url, $new_url, html_entity_decode($imgItem));
        $srcset = fifu_jetpack_get_set($new_url, false);

        // custom lazy load
        $newImgItem = str_replace('<img ', '<img fifu-lazy="1" fifu-data-sizes="auto" fifu-data-srcset="' . $srcset . '" ', $newImgItem);
        $newImgItem = str_replace(' src=', ' fifu-data-src=', $newImgItem);
        $newImgItem = str_replace('<img ', '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" ', $newImgItem);

        $content = str_replace($imgItem, $newImgItem, $content);
    }

    $content = fifu_remove_source_tags($content);

    return $content;
}

function fifu_remove_source_tags($content) {
    $pattern = '/<source\b[^>]*>(.*?)<\/source>|<source\b[^>]*\/?>/i';
    $cleaned_content = preg_replace($pattern, '', $content);
    return $cleaned_content;
}

function fifu_should_hide() {
    if (fifu_is_off('fifu_hide'))
        return false;

    if (class_exists('WooCommerce') && is_product())
        return false;

    global $post;
    if (isset($post->ID) && $post->ID != get_queried_object_id())
        return false;

    $post_types_string = get_option('fifu_hide_type');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return false;

    $formats = get_option('fifu_hide_format');
    if (isset($post->ID) && $formats) {
        $post_format = get_post_format($post->ID);
        if (false === $post_format)
            $post_format = 'standard';
        if (!in_array($post_format, explode(',', $formats)))
            return false;
    }

    return !is_front_page() && is_singular(get_post_type(get_the_ID()));
}

function fifu_is_cpt() {
    return in_array(get_post_type(get_the_ID()), array_diff(fifu_get_post_types(), array('post', 'page')));
}

function fifu_main_image_url($post_id, $front = false) {
    $url = get_post_meta($post_id, 'fifu_image_url', true);

    if (!$url && fifu_no_internal_image($post_id) && (get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url'))) {
        if (fifu_is_valid_default_cpt($post_id))
            $url = get_option('fifu_default_url');
    }

    if (!$url)
        return null;

    $url = htmlspecialchars_decode($url);

    return str_replace("'", "%27", $url);
}

function fifu_no_internal_image($post_id) {
    return get_post_meta($post_id, '_thumbnail_id', true) == -1 || get_post_meta($post_id, '_thumbnail_id', true) == null || get_post_meta($post_id, '_thumbnail_id', true) == get_option('fifu_default_attach_id');
}

function fifu_is_main_page() {
    return is_home() || (class_exists('WooCommerce') && is_shop());
}

function fifu_is_in_editor() {
    return !is_admin() || get_current_screen() == null ? false : get_current_screen()->parent_base == 'edit' || get_current_screen()->is_block_editor;
}

function fifu_get_default_url() {
    return wp_get_attachment_url(get_option('fifu_default_attach_id'));
}

// rss

add_action('pre_rss2_ns', function () {
    // Start capturing the output
    ob_start();
}, 1);

add_action('rss2_ns', function () {
    $rss_ns = ob_get_clean(); // Get the current namespace output
    if (strpos($rss_ns, 'xmlns:media="http://search.yahoo.com/mrss/"') === false) {
        // Use a regular expression to capture the <rss> tag and its version number
        $rss_ns = preg_replace(
                '/(<rss version="[^"]+")/',
                '$1' . PHP_EOL . "\t" . 'xmlns:media="http://search.yahoo.com/mrss/"',
                $rss_ns
        );
    }
    echo $rss_ns;
}, 9999);

add_action('rss2_item', 'fifu_add_rss');

function fifu_add_rss() {
    global $post;
    if (has_post_thumbnail($post->ID)) {
        $thumbnail = fifu_main_image_url($post->ID, true); // external (no CDN)
        if ($thumbnail) {
            if (fifu_is_from_speedup($thumbnail))
                $thumbnail = fifu_speedup_get_signed_url($thumbnail, 1280, 853, null, null, false);
            elseif (fifu_is_on('fifu_photon')) {
                $thumbnail = fifu_cdn_adjust($thumbnail);
                $thumbnail = fifu_jetpack_photon_url($thumbnail, null, get_post_thumbnail_id($post_id));
            }
        } else {
            $thumbnail = wp_get_attachment_url(get_post_thumbnail_id($post->ID)); // internal
        }
        if ($thumbnail) {
            // query strings should be removed for Google Publisher Center
            echo '<media:content url="' . explode('?', $thumbnail)[0] . '" medium="image"></media:content>
			';
        }
    }
}

// for ajax pagination
function fifu_posts_results($posts, $query) {
    if (!is_admin() && $query->is_main_query() && is_paged()) {
        foreach ($posts as $post) {
            fifu_add_parameters_single_post($post->ID);
        }
    }
    return $posts;
}

add_filter('posts_results', 'fifu_posts_results', 10, 2);

function fifu_wpseo_schema_graph($graph, $context) {
    if (is_singular()) {
        $post_id = get_the_ID();

        $url = fifu_main_image_url($post_id, true);
        $image_urls = $url ? [$url] : [];

        if (!empty($image_urls)) {
            foreach ($graph as &$item) {
                // Replace the image URLs for WebPage, Article, and Product types
                if (isset($item['@type']) && in_array($item['@type'], ['Article', 'WebPage', 'Product'])) {
                    if (isset($item['primaryImageOfPage'])) {
                        $item['primaryImageOfPage'] = $image_urls[0];
                    }

                    if (isset($item['image'])) {
                        $item['image'] = $image_urls;
                    }
                }

                // Replace the image URLs for ImageObject types
                if (isset($item['@type']) && $item['@type'] === 'ImageObject') {
                    if (isset($item['url'])) {
                        $item['url'] = $image_urls[0];
                    }
                    if (isset($item['contentUrl'])) {
                        $item['contentUrl'] = $image_urls[0];
                    }
                }
            }
        }
    }
    return $graph;
}

add_filter('wpseo_schema_graph', 'fifu_wpseo_schema_graph', 10, 2);

