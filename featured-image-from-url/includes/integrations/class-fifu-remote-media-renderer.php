<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Remote_Media_Renderer
{
    /**
     * Starts buffering remote media replacements.
     */
    public static function start_buffer(): void
    {
        ob_start([self::class, 'filter_buffer']);
    }

    /**
     * Filters the output buffer for remote media tags.
     */
    public static function filter_buffer(string $buffer): string
    {
        global $FIFU_SESSION;

        
            if (empty($buffer))
                return $buffer;
        
            /* plugins: Oxygen, Bricks */
            if (isset($_REQUEST['ct_builder']) || isset($_REQUEST['bricks']) || isset($_REQUEST['fb-edit']))
                return $buffer;
        
            /* img */
        
            $buffer = Fifu_Social_Meta_Filter::filter_og_images($buffer);
        
            $srcType = "src";
            $imgList = array();
            preg_match_all('/<img[^>]*>/', $buffer, $imgList);
        
            foreach (($imgList[0] ?? []) as $imgItem) {
                preg_match('/(' . $srcType . ')([^\'\"]*[\'\"]){2}/', $imgItem, $src);
                if (!$src)
                    continue;
                $del = substr($src[0] ?? '', - 1);
                $url = Fifu_Html_Attribute_Utils::normalize(explode($del, $src[0] ?? '')[1] ?? '');
                $post_id = null;

                // get parameters
                $data = null;
                $prev_url = $FIFU_SESSION['cdn-new-old'][$url] ?? null;

                if (isset($FIFU_SESSION[$url])) {
                    $data = $FIFU_SESSION[$url];
                } elseif ($prev_url && isset($FIFU_SESSION[$prev_url])) {
                    $data = $FIFU_SESSION[$prev_url];
                }
        
                if (!$data)
                    continue;
        
                if (strpos($imgItem, 'fifu-replaced') !== false)
                    continue;
        
                if ($data['local'] ?? false)
                    continue;
        
                $post_id = $data['post_id'] ?? null;
                $att_id = $data['att_id'] ?? null;
                $featured = $data['featured'] ?? null;
                $is_category = $data['category'] ?? false;
                $theme_width = $data['theme-width'] ?? null;
                $theme_height = $data['theme-height'] ?? null;
        
                if ($featured && is_single()) {
                    $buffer = str_replace('</head>', '<link rel="preload" as="image" href="' . esc_url($url) . '">' . "</head>\n", $buffer);
                }
        
                if ($featured) {
                    // add featured
                    $newImgItem = str_replace('<img ', '<img fifu-featured="' . $featured . '" ', $imgItem);

                    // add category
                    if ($is_category)
                        $newImgItem = str_replace('<img ', '<img fifu-category="1" ', $newImgItem);

                    // add post_id
                    if (get_post_type($post_id) == 'product')
                        $newImgItem = str_replace('<img ', '<img product-id="' . $post_id . '" ', $newImgItem);
                    else
                        $newImgItem = str_replace('<img ', '<img post-id="' . $post_id . '" ', $newImgItem);

                    // add theme sizes
                    if ($theme_width && $theme_height) {
                        $newImgItem = str_replace('<img ', '<img theme-width="' . $theme_width . '" ', $newImgItem);
                        $newImgItem = str_replace('<img ', '<img theme-height="' . $theme_height . '" ', $newImgItem);
                    }

                    // speed up (doesn't work with ajax calls)
                    if (Fifu_Speedup_Url_Service::is_speedup_url($url)) {
                        $newImgItem = str_replace('<img ', '<img srcset="' . Fifu_Speedup_Url_Service::get_srcset($url) . '" ', $newImgItem);
                        $newImgItem = str_replace('<img ', '<img sizes="(max-width:' . $theme_width . 'px) 100vw, ' . $theme_width . 'px" ', $newImgItem);
                    }

                    $buffer = str_replace($imgItem, Fifu_Featured_Image_Filter::filter_post_thumbnail_html($newImgItem, $post_id, null, null, null), $buffer);
                }
            }
        
            /* background-image */
        
            $imgList = array();
            preg_match_all('/<[^>]*background-image[^>]*>/', $buffer, $imgList);
            foreach (($imgList[0] ?? []) as $imgItem) {
                if (strpos($imgItem, 'style=') === false || strpos($imgItem, 'url(') === false)
                    continue;
        
                $mainDelimiter = substr(explode('style=', str_replace('\\', '', $imgItem))[1] ?? '', 0, 1);
                $subDelimiter = substr(explode('url(', str_replace('\\', '', $imgItem))[1] ?? '', 0, 1);
                if (in_array($subDelimiter, array('"', "'", ' ')))
                    $url = preg_split('/[\'\" ]{1}\)/', preg_split('/url\([\'\" ]{1}/', $imgItem, -1)[1] ?? '', -1)[0] ?? '';
                else {
                    $url = preg_split('/\)/', preg_split('/url\(/', $imgItem, -1)[1] ?? '', -1)[0] ?? '';
                    $subDelimiter = '';
                }
        
                $newImgItem = $imgItem;
        
                $url = Fifu_Html_Attribute_Utils::normalize($url);
                if (isset($FIFU_SESSION[$url])) {
                    $data = $FIFU_SESSION[$url];
        
                    if (strpos($imgItem, 'fifu-replaced') !== false)
                        continue;
        
                    if ($data['local'] ?? false)
                        continue;
        
                    $att_id = $data['att_id'] ?? null;
        
                    $post_id = $data['post_id'] ?? null;
                    $newImgItem = str_replace('>', ' ' . 'post-id="' . $post_id . '">', $newImgItem);
                }
        
                if ($newImgItem != $imgItem)
                    $buffer = str_replace($imgItem, $newImgItem, $buffer);
            }
        
            return $buffer;
    }

}
