<?php

defined('ABSPATH') || exit;

/**
 * Optimizes post content images via FIFO CDN and lazy-load replacements.
 */
class Fifu_Content_Image_Cdn_Optimizer {

    /**
     * Rewrites content images to use the public FIFU CDN plus lazy-load assets.
     *
     * @param string $content
     * @return string
     */
    public static function optimize(string $content): string {
        if (Fifu_Options_Utils::is_off('fifu_cdn_content') || empty($content)) {
            return $content;
        }

        wp_register_style('fifu-lazyload-style', plugins_url('/includes/html/css/lazyload.css', FIFU_PLUGIN_FILE), array(), Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-lazyload-style');
        wp_enqueue_script('fifu-lazyload-js', plugins_url('/includes/html/js/lazyload.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());

        global $post;
        if (!isset($post) || !isset($post->ID)) {
            return $content;
        }

        $post_id = $post->ID ?? 0;

        $srcType = 'src';
        $imgList = array();
        preg_match_all('/<img[^>]*>/', $content, $imgList);

        foreach (($imgList[0] ?? []) as $imgItem) {
            $srcPattern = '/(?<![\w:-])' . $srcType . '\s*=\s*([\'"])(.*?)\1/i';
            if (!preg_match($srcPattern, $imgItem, $src)) {
                continue;
            }

            $url = isset($src[2]) ? Fifu_Html_Attribute_Utils::normalize($src[2]) : '';

            if (!$url || Fifu_Jetpack_Cdn_Service::is_blocked_source($url) || strpos($url, 'data:image') === 0) {
                continue;
            }

            $new_url = Fifu_Jetpack_Cdn_Service::build_photon_url($url, null, get_post_thumbnail_id($post_id));

            $decodedImgItem = html_entity_decode($imgItem);
            $newImgItem = preg_replace_callback(
                $srcPattern,
                static function (array $matches) use ($new_url): string {
                    $quote = $matches[1] ?? '"';
                    return 'fifu-data-src=' . $quote . $new_url . $quote;
                },
                $decodedImgItem,
                1
            );

            if ($newImgItem === null) {
                continue;
            }

            $srcset = Fifu_Jetpack_Cdn_Service::build_srcset($new_url);

            $newImgItem = str_replace('<img ', '<img fifu-lazy="1" fifu-data-sizes="auto" fifu-data-srcset="' . $srcset . '" ', $newImgItem);
            $newImgItem = str_replace('<img ', '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" ', $newImgItem);

            $content = str_replace($imgItem, $newImgItem, $content);
        }

        $pattern = '/<source\\b[^>]*>(.*?)<\\/source>|<source\\b[^>]*\\/?>/i';
        $content = preg_replace($pattern, '', $content);

        return $content;
    }
}
