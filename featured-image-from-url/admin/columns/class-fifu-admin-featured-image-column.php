<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinator for the featured image column UI inside the admin list tables.
 */
final class Fifu_Admin_Featured_Image_Column
{
    /**
     * Legacy column height guideline that replaces the old FIFU_COLUMN_HEIGHT constant.
     */
    private const COLUMN_HEIGHT = 40;

    /**
     * Registers filters and actions originally wired by the legacy fifu_column() helper.
     */
    public static function register(): void
    {
        if (!is_user_logged_in() || !current_user_can('publish_posts')) {
            return;
        }

        add_filter('manage_posts_columns', [self::class, 'filter_list_table_columns']);
        add_filter('manage_pages_columns', [self::class, 'filter_list_table_columns']);
        add_filter('manage_edit-product_cat_columns', [self::class, 'filter_list_table_columns']);

        self::registerCustomPostTypeColumns();

        add_action('manage_posts_custom_column', [self::class, 'render_post_column'], 10, 2);
        add_action('manage_pages_custom_column', [self::class, 'render_post_column'], 10, 2);
        add_action('manage_product_cat_custom_column', [self::class, 'render_term_column'], 10, 3);
    }

    /**
     * Enqueues the styles/scripts and localization values that were formerly added inside fifu_admin_add_css_js().
     */
    public static function enqueue_assets(): void
    {
        $screen_base = Fifu_Wp_Context::check_screen_base();

        if (!in_array($screen_base, ['list', 'edit', 'new'], true)) {
            return;
        }

        if ($screen_base === 'list' && self::is_featured_image_column_hidden()) {
            return;
        }

        global $pagenow;
        if (!is_admin() || ('edit.php' !== $pagenow && 'post.php' !== $pagenow && 'term.php' !== $pagenow && 'post-new.php' !== $pagenow && 'edit-tags.php' !== $pagenow)) {
            return;
        }

        if (isset($_REQUEST['page']) && strpos($_REQUEST['page'], 'bbapp') !== false) {
            return;
        }

        if (isset($_REQUEST['post'])) {
            $blocked_list = ['wppb-rf-cpt', 'wppb-epf-cpt'];
            $post_id = $_REQUEST['post'];
            $post_type = get_post_type($post_id);
            if (in_array($post_type, $blocked_list, true)) {
                return;
            }
        }

        if (isset($_REQUEST['post_type'])) {
            $blocked_list = ['wppb-rf-cpt', 'wppb-epf-cpt'];
            $post_type = $_REQUEST['post_type'];
            if (in_array($post_type, $blocked_list, true)) {
                return;
            }
        }

        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
        wp_enqueue_style('fifu-column-css', plugins_url('/admin/html/css/column.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_script(
            'fifu-shared-js',
            plugins_url('/admin/html/js/fifu-shared.js', FIFU_PLUGIN_FILE),
            array(),
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_register_style('fifu-search-lightbox-css', plugins_url('/admin/html/css/search-lightbox.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-search-lightbox-css');
        wp_enqueue_script('fifu-search-lightbox-js', plugins_url('/admin/html/js/search-lightbox.js', FIFU_PLUGIN_FILE), ['jquery'], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_script('fifu-column-js', plugins_url('/admin/html/js/column.js', FIFU_PLUGIN_FILE), ['jquery', 'fifu-shared-js', 'fifu-search-lightbox-js'], Fifu_Plugin_Info::get_enqueue_version());

        $fifu = Fifu_Quick_Edit_Strings::get_strings();
        $fifu_help = Fifu_Help_Strings::get_strings();

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        wp_localize_script('fifu-column-js', 'fifuColumnVars', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
            'labelImage' => $fifu['title']['image'](),
            'labelSearch' => $fifu['title']['search'](),
            'tipImage' => $fifu['tip']['image'](),
            'tipSearch' => $fifu['tip']['search'](),
            'urlImage' => $fifu['url']['image'](),
            'keywords' => $fifu['image']['keywords'](),
            'buttonSave' => $fifu['button']['save'](),
            'buttonClean' => $fifu['button']['clean'](),
            'isDebugEnabled' => Fifu_Options_Utils::is_on('fifu_debug'),
            'buttonCopyDebugData' => 'Copy debug data',
            'quickEditDebugDataUrl' => esc_url_raw(rest_url(self::quick_edit_rest_namespace() . '/quick_edit_debug_data_api/')),
            'onProductsPage' => Fifu_Woocommerce_Context::is_products_admin_list(),
            'onCategoriesPage' => Fifu_Woocommerce_Context::is_product_categories_admin_list(),
            'taxonomy' => $screen->taxonomy ?? null,
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
            'labelVariable' => $fifu['title']['variable']['product'](),
            'labelVariation' => $fifu['title']['variable']['variation'](),
            'labelName' => $fifu['title']['variable']['name'](),
            'convertUrlJs' => plugins_url('/admin/html/js/convert-url.js', FIFU_PLUGIN_FILE),
            'fifuVersionNumber' => Fifu_Plugin_Info::get_version(),
        ]);
    }

    private static function quick_edit_rest_namespace(): string
    {
        return FIFU_REST_NAMESPACE_V2;
    }

    /**
     * Prints the footer payload originally emitted by fifu_footer().
     */
    public static function print_footer_quick_edit_payload(): void
    {
        global $FIFU_SESSION;

        if (!empty($FIFU_SESSION['fifu-quick-edit']) || !empty($FIFU_SESSION['fifu-quick-edit-parent'])) {
            wp_enqueue_script('fifu-quick-edit', plugins_url('/admin/html/js/quick-edit.js', FIFU_PLUGIN_FILE), ['jquery'], Fifu_Plugin_Info::get_enqueue_version());
            wp_localize_script('fifu-quick-edit', 'fifuQuickEditVars', [
                'posts' => $FIFU_SESSION['fifu-quick-edit'] ?? null,
                'parent' => $FIFU_SESSION['fifu-quick-edit-parent'] ?? null,
            ]);
        }
    }

    /**
     * Adds the featured image column header as the legacy fifu_column_head() helper used to.
     */
    public static function filter_list_table_columns(array $columns): array
    {
        $fifu = Fifu_Quick_Edit_Strings::get_strings();
        $height = self::COLUMN_HEIGHT;

        $columns['featured_image'] =
            "<center style='max-width:{$height}px;min-width:{$height}px'>" .
            "<span class='dashicons dashicons-camera' " .
            "style='font-size:20px; cursor:help;' " .
            "title='" . esc_attr($fifu['tip']['column']()) . "'>" .
            '</span>' .
            '<div style="display:none">FIFU</div>' .
            '</center>';

        return $columns;
    }

    /**
     * Renders the featured image column for posts and pages, mirroring the original fifu_column_content().
     */
    public static function render_post_column(string $column, int $post_id): void
    {
        if ($column !== 'featured_image' || self::is_featured_image_column_hidden()) {
            return;
        }

        $db2Manager = null;

        if (function_exists('fifu_db2_manager')) {
            $candidate = fifu_db2_manager();

            if ($candidate instanceof Fifu_Db2_Manager) {
                $db2Manager = $candidate;
                $db2Manager->beginPostReadCacheScope();
            }
        }

        try {
            global $FIFU_SESSION;
            $is_variable_product = Fifu_Woocommerce_Context::is_variable_product($post_id);

            if (!$is_variable_product && isset($FIFU_SESSION['fifu-quick-edit'][$post_id])) {
                return;
            }

            $fifu = Fifu_Meta_Box_Strings::get_featured_image_box_strings();
            [$border, $height, $width, $media_url, $media_src, $is_ctgr, $is_variable, $image_url, $url, $vars] = self::buildPostFeaturedPayload(
                $post_id,
                $is_variable_product
            );
            include FIFU_ADMIN_DIR . '/html/column.html';

            $FIFU_SESSION['fifu-quick-edit-parent'][$post_id] = null;

            if (class_exists('WooCommerce')) {
                $product = wc_get_product($post_id);

                if ($product) {
                    if ($product->get_type() === 'variable') {
                        $parent_data = [
                            'border' => $border,
                            'height' => $height,
                            'width' => $width,
                            'media-url' => '',
                            'media-src' => '',
                            'is-ctgr' => $is_ctgr ?: '',
                            'is-variable' => '',
                            'image-url' => $image_url,
                            'url' => $url,
                        ];
                        $FIFU_SESSION['fifu-quick-edit-parent'][$post_id] = $parent_data;
                        $vars[$post_id]['title'] = method_exists($product, 'get_title') ? (string) $product->get_title() : '';
                    }

                    self::hydrate_product_quick_edit_image_payload((int) $post_id, $vars);

                }
            }

            $payload = isset($vars[$post_id]) && is_array($vars[$post_id]) ? $vars[$post_id] : [];
            self::merge_quick_edit_payload_into_session($post_id, $payload);
        } finally {
            if ($db2Manager instanceof Fifu_Db2_Manager) {
                $db2Manager->endPostReadCacheScope();
            }
        }
    }

    /**
     * Renders the featured image column for taxonomy rows, porting fifu_ctgr_column_content().
     */
    public static function render_term_column($internal_image, string $column, int $term_id): void
    {
        if ($column !== 'featured_image') {
            echo $internal_image;
            return;
        }

        if (self::is_featured_image_column_hidden()) {
            return;
        }

        global $FIFU_SESSION;

        [$border, $height, $width, $media_url, $media_src, $is_ctgr, $is_variable, $image_url, $url, $vars] = self::buildTermFeaturedPayload($term_id);
        $post_id = $term_id;
        include FIFU_ADMIN_DIR . '/html/column.html';

        $term_ids = [$term_id];
        foreach ($term_ids as $id) {
            $FIFU_SESSION['fifu-quick-edit-ctgr'][$id] = $vars[$id] ?? [];
        }

        wp_enqueue_script('fifu-quick-edit', plugins_url('/admin/html/js/quick-edit.js', FIFU_PLUGIN_FILE), ['jquery'], Fifu_Plugin_Info::get_enqueue_version());
        wp_localize_script('fifu-quick-edit', 'fifuQuickEditCtgrVars', [
            'terms' => $FIFU_SESSION['fifu-quick-edit-ctgr'] ?? [],
        ]);
    }

    /**
     * Adds featured image columns for registered custom post types, replacing fifu_column_custom_post_type().
     */
    private static function registerCustomPostTypeColumns(): void
    {
        foreach (Fifu_Post_Type_Utils::get_post_types() as $post_type) {
            add_filter("manage_edit-{$post_type}_columns", [self::class, 'filter_list_table_columns']);
        }
    }

    private static function is_featured_image_column_hidden(): bool
    {
        if (!function_exists('get_current_screen') || !function_exists('get_hidden_columns')) {
            return false;
        }

        $screen = get_current_screen();

        if (!is_object($screen)) {
            return false;
        }

        return in_array('featured_image', get_hidden_columns($screen), true);
    }

    /**
     * Builds the featured payload previously returned by fifu_column_featured().
     *
     * @return array{0:string,1:int,2:float,3:string|null,4:string|null,5:bool,6:bool,7:string|null,8:string|null,9:array<int,array<string,mixed>>}
     */
    private static function buildPostFeaturedPayload(int $post_id, bool $is_variable): array
    {
        $border = '';
        $height = self::COLUMN_HEIGHT;
        $width = $height * 1.0;
        $media_url = '';
        $media_src = '';
        $is_ctgr = false;
        $image_url = null;
        $vars = [];

        $url = '';
        if ($url === '') {
            $image_url = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, true);
            $image_alt = Fifu_Post_Image_Alt_Read_Service::get_image_alt((int) $post_id);
            if ($image_url === '' || $image_url === null) {
                $image_url = wp_get_attachment_url(get_post_thumbnail_id($post_id));
                $border = 'border-color: #ca4a1f !important; border: 2px; border-style: dotted; border-radius: 8px;';
            }

            $url = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url(
                (string) $image_url,
                get_post_thumbnail_id($post_id),
                150,
                1
            );

            $vars[$post_id]['fifu_image_url'] = Fifu_Post_Image_Url_Read_Service::get_image_url((int) $post_id);
            $vars[$post_id]['fifu_image_alt'] = $image_alt;
        } else {
            $image_url = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, true);
            $url = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url(
                (string) $image_url,
                get_post_thumbnail_id($post_id),
                150,
                1
            );
        }

        return [$border, $height, $width, '', '', $is_ctgr, $is_variable, $image_url, $url, $vars];
    }

    /**
     * @param array<int,array<string,mixed>> $vars
     */
    private static function hydrate_product_quick_edit_image_payload(int $post_id, array &$vars): void
    {
        $vars[$post_id] = $vars[$post_id] ?? [];
        $vars[$post_id]["fifu_image_url"] = Fifu_Post_Image_Url_Read_Service::get_image_url((int) $post_id);
        $vars[$post_id]["fifu_image_alt"] = Fifu_Post_Image_Alt_Read_Service::get_image_alt((int) $post_id);
    }

    private static function merge_quick_edit_payload_into_session(int $post_id, array $payload): void
    {
        global $FIFU_SESSION;

        if (!isset($FIFU_SESSION) || !is_array($FIFU_SESSION)) {
            $FIFU_SESSION = [];
        }

        if (!isset($FIFU_SESSION['fifu-quick-edit']) || !is_array($FIFU_SESSION['fifu-quick-edit'])) {
            $FIFU_SESSION['fifu-quick-edit'] = [];
        }

        if (!isset($FIFU_SESSION['fifu-quick-edit'][$post_id]) || !is_array($FIFU_SESSION['fifu-quick-edit'][$post_id])) {
            $FIFU_SESSION['fifu-quick-edit'][$post_id] = [];
        }

        if ((!array_key_exists('fifu_image_url', $payload) || !array_key_exists('fifu_image_alt', $payload)) && function_exists('get_post_type')) {
            $hydrated = [$post_id => []];
            self::hydrate_product_quick_edit_image_payload($post_id, $hydrated);
            $hydratedPayload = $hydrated[$post_id] ?? [];
            foreach (['fifu_image_url', 'fifu_image_alt'] as $key) {
                if (!array_key_exists($key, $payload) && array_key_exists($key, $hydratedPayload)) {
                    $payload[$key] = $hydratedPayload[$key];
                }
            }
        }

        $FIFU_SESSION['fifu-quick-edit'][$post_id] = array_replace(
            $FIFU_SESSION['fifu-quick-edit'][$post_id],
            $payload
        );
    }

    /**
     * Builds the featured payload for taxonomy rows that the legacy fifu_ctgr_column_featured() returned.
     *
     * @return array{0:string,1:int,2:float,3:string|null,4:string|null,5:bool,6:bool,7:string|null,8:string|null,9:array<int,array<string,mixed>>}
     */
    private static function buildTermFeaturedPayload(int $term_id): array
    {
        $border = '';
        $height = self::COLUMN_HEIGHT;
        $width = $height * 1.0;
        $media_url = '';
        $media_src = '';
        $is_ctgr = true;
        $is_variable = false;
        $image_url = null;
        $vars = [];

            $url = '';
            if ($url === '' || $url === null) {
            $image_url = Fifu_Term_Image_Url_Read_Service::get_image_url((int) $term_id);
            $image_alt = Fifu_Term_Image_Alt_Read_Service::get_image_alt((int) $term_id);
            if ($image_url === '' || $image_url === null) {
                $thumb_id = get_term_meta($term_id, 'thumbnail_id', true);
                $image_url = wp_get_attachment_url($thumb_id);
                if ($image_url === false) {
                    $image_url = '';
                }
                $border = 'border-color: #ca4a1f !important; border: 2px; border-style: dotted; border-radius: 8px;';
            }

            $url = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url(
                (string) $image_url,
                Fifu_Post_Meta_Utils::get_term_thumbnail_id($term_id),
                150,
                1
            );

            $vars[$term_id]['fifu_image_url'] = $image_url;
            $vars[$term_id]['fifu_image_alt'] = $image_alt;
        } else {
            $image_url = Fifu_Term_Image_Url_Read_Service::get_image_url((int) $term_id);
            $url = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url(
                (string) $image_url,
                Fifu_Post_Meta_Utils::get_term_thumbnail_id($term_id),
                150,
                1
            );
        }

        return [$border, $height, $width, '', '', $is_ctgr, $is_variable, $image_url, $url, $vars];
    }

}
