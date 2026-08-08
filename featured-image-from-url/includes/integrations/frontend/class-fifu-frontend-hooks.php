<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

class Fifu_Frontend_Hooks {

    /**
     * Register all frontend hooks.
     */
    public static function register(): void {
        self::register_head_assets();
        self::register_social_tags();
        self::register_seo_integrations();
        self::register_local_media_renderer();
        self::register_remote_media_renderer();
    }

    /**
     * Register frontend head assets (scripts, styles, etc.).
     */
    private static function register_head_assets(): void {
        add_action('wp_head', [Fifu_Frontend_Assets::class, 'add_head_assets']);
        add_action('wp_head', [Fifu_Woocommerce_Display_Configuration::class, 'output_lightbox_trigger_css']);
    }

    /**
     * Register social tag related hooks (Open Graph, Twitter cards, etc.).
     */
    private static function register_social_tags(): void {
        global $pagenow;

        $rank_math_active = class_exists('Fifu_Plugin_Detector')
            && Fifu_Plugin_Detector::is_rank_math_seo_active();

        $is_frontend_request = !isset($pagenow)
            || !in_array(
                $pagenow,
                [
                    'post.php',
                    'post-new.php',
                    'admin-ajax.php',
                    'wp-cron.php',
                ],
                true
            );

        if ($is_frontend_request && !$rank_math_active) {
            if (
                class_exists('Fifu_Plugin_Detector')
                && Fifu_Plugin_Detector::is_yoast_seo_active()
            ) {
                add_action(
                    'wpseo_opengraph_image',
                    [
                        Fifu_Yoast_Image_Integration::class,
                        'filter_og_image',
                    ]
                );

                add_action(
                    'wpseo_twitter_image',
                    [
                        Fifu_Yoast_Image_Integration::class,
                        'filter_og_image',
                    ]
                );

                add_action(
                    'wpseo_add_opengraph_images',
                    [
                        Fifu_Yoast_Image_Integration::class,
                        'filter_og_images',
                    ]
                );
            } else {
                add_action(
                    'wp_head',
                    [
                        Fifu_Post_Social_Tags::class,
                        'render_post_image_tags',
                    ]
                );
            }
        }

        if (!$rank_math_active) {
            add_action(
                'wp_head',
                [
                    Fifu_Home_Social_Tags::class,
                    'render_home_social_tags',
                ],
                9999
            );

            add_action(
                'wp_head',
                [
                    Fifu_Category_Social_Tags::class,
                    'render_category_social_tags',
                ]
            );
        }
    }

    /**
     * Register SEO related filters and integrations (Yoast, Rank Math, etc.).
     */
    private static function register_seo_integrations(): void {
        add_filter('wp_get_attachment_image_attributes', [Fifu_Attachment_Image_Attributes_Filter::class, 'filter_attributes'], 10, 3);
        add_filter('post_thumbnail_html', [Fifu_Featured_Image_Filter::class, 'filter_post_thumbnail_html'], 10, 5);
        add_filter('the_content', [Fifu_Content_Image_Cdn_Optimizer::class, 'optimize']);
        add_action('rss2_item', [Fifu_Rss_Image_Item::class, 'render_media_content']);
        add_filter('wpseo_schema_graph', [Fifu_Yoast_Schema_Graph_Integration::class, 'filter_schema_graph'], 10, 2);
        add_filter('rank_math/opengraph/facebook/image', [Fifu_Rank_Math_Integration::class, 'filter_facebook_image']);
        add_filter('rank_math/opengraph/twitter/image', [Fifu_Rank_Math_Integration::class, 'filter_twitter_image']);
        add_filter('rank_math/sitemap/enable_caching', [Fifu_Rank_Math_Integration::class, 'filter_sitemap_caching'], 10, 1);
        add_filter('rank_math/sitemap/xml_img_src', [Fifu_Rank_Math_Integration::class, 'filter_sitemap_xml_img_src'], 10, 2);
    }

    /**
     * Register the local media rendering bootstrap.
     */
    private static function register_local_media_renderer(): void {
        add_filter('posts_results', [Fifu_Local_Media_Renderer::class, 'register_posts_results'], 10, 2);
        add_action('template_redirect', [Fifu_Local_Media_Renderer::class, 'start_buffer'], 10);
    }

    /**
     * Registers the remote media renderer buffer logic.
     */
    private static function register_remote_media_renderer(): void {
        add_action('template_redirect', [Fifu_Remote_Media_Renderer::class, 'start_buffer'], 11);
    }
}
