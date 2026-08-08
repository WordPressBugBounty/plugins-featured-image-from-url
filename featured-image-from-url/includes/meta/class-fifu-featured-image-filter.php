<?php

defined('ABSPATH') || exit;

/**
 * Replaces featured image HTML with FIFU managed content.
 */
class Fifu_Featured_Image_Filter {

    /**
     * Filters post thumbnail HTML to inject FIFU managed media.
     *
     * @param string       $html
     * @param int          $post_id
     * @param int|null     $post_thumbnail_id
     * @param array|string|null $size
     * @param array|null   $attr
     * @return string
     */
    public static function filter_post_thumbnail_html(string $html, int $post_id, ?int $post_thumbnail_id, $size = null, $attr = null): string {
        global $FIFU_SESSION;

        if (!$html || $post_id <= 0) {
            return $html;
        }

        $delimiter = Fifu_Html_Attribute_Utils::get_delimiter('src', $html);
        $src = Fifu_Html_Attribute_Utils::get_attribute('src', $html);

        if (isset($FIFU_SESSION) && isset($FIFU_SESSION[$src])) {
            if (strpos($html, 'fifu-replaced') !== false) {
                return $html;
            }
        }

        $directImageUrl = self::get_direct_image_url((int) $post_id);

        $url = $directImageUrl;

        $title = null;

        $alt = null;
        if ($directImageUrl) {
            $alt = Fifu_Post_Image_Alt_Read_Service::get_image_alt((int) $post_id);
        }
        if (!$alt) {
            $alt = esc_attr(strip_tags(get_the_title($post_id)));
            $title = esc_attr($title ? $title : $alt);
            $custom_alt = 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $title . $delimiter;
            $html = preg_replace('/alt=[\'\"][^\'\"]*[\'\"]/', $custom_alt, $html);
            $html = Fifu_Html_Attribute_Utils::ensure_alt_attribute($html, $custom_alt);
        } else {
            $alt = esc_attr(strip_tags($alt));
            $title = esc_attr($title ? $title : $alt);
            if ($url && $alt) {
                $html = preg_replace('/alt=[\'\"][^\'\"]*[\'\"]/', 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $title . $delimiter, $html);
            }
        }

        if ($url) {
            return $html;
        }

        if (Fifu_Image_Display_Policy::should_hide_featured_media()) {
            return '';
        }

        return $html;
    }

    private static function get_direct_image_url(int $post_id): ?string {
        if (!class_exists('Fifu_Post_Image_Url_Read_Service')) {
            return null;
        }

        return Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);
    }

}
