<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Featured image from URL meta box strings.
 *
 * @package Fifu_Free
 */
class Fifu_Meta_Box_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_featured_image_box_strings(): array {
        $box = array();


            // word
            $box['word']['remove'] = function () {
                _e("Remove", FIFU_SLUG);
            };

            // common
            $box['common']['alt'] = function () {
                _e("Alternative text", FIFU_SLUG);
            };
            $box['common']['image'] = function () {
                _e("Image URL", FIFU_SLUG);
            };
            $box['common']['ok'] = function () {
                _e("OK", FIFU_SLUG);
            };
            $box['common']['preview'] = function () {
                _e("Preview", FIFU_SLUG);
            };
            $box['common']['video'] = function () {
                _e("Video URL", FIFU_SLUG);
            };
            $box['common']['capture'] = function () {
                _e("Set frame as thumbnail", FIFU_SLUG);
            };

            // details
            $box['detail']['ratio'] = function () {
                _e("Ratio", FIFU_SLUG);
            };
            $box['detail']['eg'] = function () {
                _e("e.g.:", FIFU_SLUG);
            };

            // titles
            $box['title']['category']['video'] = function () {
                _e("Featured video", FIFU_SLUG);
            };
            $box['title']['category']['image'] = function () {
                _e("Featured image", FIFU_SLUG);
            };

            // video
            $box['video']['remove'] = function () {
                _e("Remove remote video", FIFU_SLUG);
            };
            $box['video']['url'] = function () {
                return __("Video URL", FIFU_SLUG);
            };
            $box['video']['ok'] = function () {
                return __("OK", FIFU_SLUG);
            };

            // image
            $box['image']['keywords'] = function () {
                _e("Image URL or Keywords", FIFU_SLUG);
            };
            $box['image']['remove'] = function () {
                _e("Remove remote image", FIFU_SLUG);
            };
            $box['image']['sirv']['add'] = function () {
                _e("Add image from Sirv", FIFU_SLUG);
            };
            $box['image']['sirv']['choose'] = function () {
                _e("Choose Sirv image", FIFU_SLUG);
            };
            $box['image']['upload'] = function () {
                _e("Upload to media library", FIFU_SLUG);
            };
            $box['image']['alt'] = function () {
                return __("Alternative text", FIFU_SLUG);
            };
            $box['image']['ifm'] = function () {
                return __("iframe URL", FIFU_SLUG);
            };
            $box['image']['url'] = function () {
                return __("Image URL", FIFU_SLUG);
            };
            $box['image']['ok'] = function () {
                return __("OK", FIFU_SLUG);
            };
            $box['alt']['help'] = function () {
                _e("This field is used to provide alternative text for images, enhancing accessibility and SEO. If it is empty, then FIFU will use the post title automatically.", FIFU_SLUG);
            };

            // placeholder
            $box['placeholder']['page'] = function () {
                _e("Web page URL", FIFU_SLUG);
            };
            $box['placeholder']['asin'] = function () {
                _e("ASIN", FIFU_SLUG);
            };
            $box['placeholder']['image-video'] = function () {
                return __("Image/Video URL", FIFU_SLUG);
            };


        return $box;
    }
}
