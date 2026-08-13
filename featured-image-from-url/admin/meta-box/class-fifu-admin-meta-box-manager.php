<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Admin_Meta_Box_Manager {

    public static function get_current_editor_post_id(): int {
        if (isset($_REQUEST['post'])) {
            return absint($_REQUEST['post']);
        }

        if (isset($_REQUEST['post_ID'])) {
            return absint($_REQUEST['post_ID']);
        }

        return 0;
    }

    public static function should_hide_native_featured_image_for_post(int $post_id): bool {
        if ($post_id <= 0) {
            return false;
        }

        $url = Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);
        if (!$url) {
            return false;
        }

        $rank_math_active = class_exists('Fifu_Plugin_Detector') && Fifu_Plugin_Detector::is_rank_math_seo_active();
        if ($rank_math_active) {
            return false;
        }

        return true;
    }

    public static function register_meta_boxes(): void {
        if (Fifu_Web_Stories_Integration::is_web_story() || Fifu_Search_Filter_Pro_Integration::is_search_filter_pro()) {
            return;
        }

        if (Fifu_Options_Utils::is_on('fifu_lock')) {
            return;
        }

        $fifu = Fifu_Meta_Box_Php_Strings::get_strings();
        $post_types = Fifu_Post_Type_Utils::get_post_types();

        foreach ($post_types as $post_type) {
            if ($post_type == 'product') {
                add_meta_box(
                    'urlMetaBox',
                    $fifu['title']['product']['image'](),
                    [ Fifu_Admin_Meta_Box_Renderer::class, 'render_featured_image_box' ],
                    $post_type,
                    'side',
                    'default'
                );

            } else {
                if ($post_type) {
                    add_meta_box(
                        'imageUrlMetaBox',
                        $fifu['title']['post']['image'](),
                        [ Fifu_Admin_Meta_Box_Renderer::class, 'render_featured_image_box' ],
                        $post_type,
                        'side',
                        'default'
                    );

                }
            }
        }
    }

    public static function remove_native_metaboxes(): void {
        global $post;

        if (!$post) {
            return;
        }

        $post_id = (int) $post->ID;
        $post_type = get_post_type($post_id);

        if (self::should_hide_native_featured_image_for_post($post_id)) {
            remove_meta_box('postimagediv', $post_type, 'side');
        }

    }

    public static function filter_admin_body_class($classes) {
        if (!is_string($classes)) {
            return $classes;
        }

        if (!Fifu_Wp_Context::is_gutenberg_screen()) {
            return $classes;
        }

        $post_id = self::get_current_editor_post_id();
        if ($post_id <= 0) {
            return $classes;
        }

        if (!self::should_hide_native_featured_image_for_post($post_id)) {
            return $classes;
        }

        if (strpos($classes, 'fifu-hide-native-featured-image') === false) {
            $classes = trim($classes . ' fifu-hide-native-featured-image');
        }

        return $classes;
    }

    public static function enqueue_block_editor_assets(): void {
        if (!Fifu_Wp_Context::is_gutenberg_screen()) {
            return;
        }

        wp_register_style(
            'fifu-block-editor-featured-image',
            plugins_url('/admin/html/css/block-editor-featured-image.css', FIFU_PLUGIN_FILE),
            array(),
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_style('fifu-block-editor-featured-image');
    }

    public static function enqueue_assets(): void {
        // for edition
        if (isset($_REQUEST['post'])) {
            $blocked_list = array('wppb-rf-cpt', 'wppb-epf-cpt');
            $post_id = $_REQUEST['post'];
            $post_type = get_post_type($post_id);
            if (in_array($post_type, $blocked_list)) {
                return;
            }
        }
        // for new posts
        if (isset($_REQUEST['post_type'])) {
            $blocked_list = array('wppb-rf-cpt', 'wppb-epf-cpt');
            $post_type = $_REQUEST['post_type'];
            if (in_array($post_type, $blocked_list)) {
                return;
            }
        }

        $fifu = Fifu_Meta_Box_Php_Strings::get_strings();
        $fifu_help = Fifu_Help_Strings::get_strings();

        wp_enqueue_script('fifu-cookie', 'https://cdnjs.cloudflare.com/ajax/libs/js-cookie/latest/js.cookie.min.js');

        wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

        wp_enqueue_script('fifu-rest-route-js', plugins_url('/admin/html/js/rest-route.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_script(
            'fifu-shared-js',
            plugins_url('/admin/html/js/fifu-shared.js', FIFU_PLUGIN_FILE),
            array(),
            Fifu_Plugin_Info::get_enqueue_version()
        );
        $meta_script_dependencies = Fifu_Wp_Context::is_gutenberg_screen()
            ? array('jquery', 'wp-edit-post', 'fifu-shared-js')
            : array('jquery', 'fifu-shared-js');
        wp_enqueue_script(
            'fifu-meta-box-js',
            plugins_url('/admin/html/js/meta-box.js', FIFU_PLUGIN_FILE),
            array_merge($meta_script_dependencies, array('fifu-search-lightbox-js')),
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_script('fifu-convert-url-js', plugins_url('/admin/html/js/convert-url.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());

        wp_register_style('fifu-search-lightbox-css', plugins_url('/admin/html/css/search-lightbox.css', FIFU_PLUGIN_FILE), array(), Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-search-lightbox-css');
        wp_enqueue_script('fifu-search-lightbox-js', plugins_url('/admin/html/js/search-lightbox.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());

        // Keep the screen context available for downstream localization logic.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        // register custom variables for the AJAX script
        wp_localize_script('fifu-rest-route-js', 'fifuScriptVars', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'restNamespaceV1' => defined('FIFU_REST_NAMESPACE_V1') ? FIFU_REST_NAMESPACE_V1 : FIFU_SLUG . '/v1',
            'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
        ]);

        if (Fifu_Plugin_Detector::is_sirv_active()) {
            wp_enqueue_script('fifu-sirv-js', 'https://scripts.sirv.com/sirv.js');
        }

        $termId = isset($_GET['tag_ID']) ? absint(wp_unslash($_GET['tag_ID'])) : 0;

        $current_post_type = '';
        if ($screen && !empty($screen->post_type)) {
            $current_post_type = (string) $screen->post_type;
        } else {
            $current_post_id = self::get_current_editor_post_id();
            if ($current_post_id > 0) {
                $current_post_type = (string) get_post_type($current_post_id);
            }
        }

        wp_localize_script('fifu-meta-box-js', 'fifuMetaBoxVars', [
            'get_the_ID' => get_the_ID(),
            'term_id' => $termId,
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'is_sirv_active' => Fifu_Plugin_Detector::is_sirv_active(),
            'wait' => $fifu['common']['wait'](),
            'is_taxonomy' => $screen->taxonomy ?? null,
            'is_product' => $current_post_type === 'product',
            'is_gutenberg' => Fifu_Wp_Context::is_gutenberg_screen(),
            'is_classic_editor' => is_plugin_active('classic-editor/classic-editor.php'),
            'txt_title_examples' => $fifu_help['title']['examples'](),
            'txt_title_keywords' => $fifu_help['title']['keywords'](),
            'txt_title_more' => $fifu_help['title']['more'](),
            'txt_title_url' => $fifu_help['title']['url'](),
            'txt_title_empty' => $fifu_help['title']['empty'](),
            'txt_desc_more' => $fifu_help['desc']['more'](),
            'txt_desc_url' => $fifu_help['desc']['url'](),
            'txt_desc_keywords' => $fifu_help['desc']['keywords'](),
            'txt_desc_empty' => $fifu_help['desc']['empty'](),
            'txt_loading' => $fifu_help['search']['loading'](),
            'alt_text_label' => $fifu['common']['alt'](),
        ]);

    }

    public static function enqueue_editor_styles(): void {
        wp_register_style('fifu-editor', plugins_url('/admin/html/css/editor.css', FIFU_PLUGIN_FILE), array(), Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-editor');
    }
}
