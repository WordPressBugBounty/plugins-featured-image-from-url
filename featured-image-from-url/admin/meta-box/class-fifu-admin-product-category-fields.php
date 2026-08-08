<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Admin UI for WooCommerce product category image fields.
 *
 * Video-specific category fields were removed from the free build.
 */
class Fifu_Admin_Product_Category_Fields
{
    /**
     * Register admin hooks for product category fields.
     */
    public static function register_hooks(): void
    {
        // UI.
        add_action('product_cat_edit_form_fields', [self::class, 'render_image_edit_fields']);
        add_action('product_cat_add_form_fields', [self::class, 'render_image_add_fields']);

        // Persistence (image only) for WooCommerce product categories.
        add_action('created_product_cat', [self::class, 'save_image_meta'], 10, 1);
        add_action('edited_product_cat', [self::class, 'save_image_meta'], 10, 1);
    }

    /**
     * Enqueue styles for product category edit/add forms.
     */
    private static function enqueue_assets(): void
    {
        $cssUrl = plugins_url('../html/css/category.css', __FILE__);
        wp_enqueue_style('fifu-category-css', $cssUrl, [], Fifu_Plugin_Info::get_enqueue_version());
    }

    /**
     * Render image fields on product category edit form.
     *
     * @param WP_Term $term
     */
    public static function render_image_edit_fields($term): void
    {
        self::enqueue_assets();

        $margin = 'margin-top:10px;';
        $width = 'width:100%;';
        $height = 'height:200px;';
        $align = 'text-align:left;';
        $url = $alt = null;

        if (is_object($term) && isset($term->term_id)) {
            $url = Fifu_Term_Image_Url_Read_Service::get_image_url((int) $term->term_id);
            $alt = Fifu_Term_Image_Alt_Read_Service::get_image_alt((int) $term->term_id);
        }

        if ($url) {
            $show_button = 'display:none;';
            $show_alt = $show_image = $show_link = '';
        } else {
            $show_button = '';
            $show_alt = $show_image = $show_link = 'display:none;';
        }

        $fifu = Fifu_Meta_Box_Strings::get_featured_image_box_strings();
        include FIFU_ADMIN_DIR . '/html/category.html';
    }

    /**
     * Render image fields on product category add form.
     */
    public static function render_image_add_fields(): void
    {
        self::enqueue_assets();

        $margin = 'margin-top:10px;';
        $width = 'width:100%;';
        $height = 'height:200px;';
        $align = 'text-align:left;';

        $show_button = $url = $alt = '';
        $show_alt = $show_image = $show_link = 'display:none;';

        $fifu = Fifu_Meta_Box_Strings::get_featured_image_box_strings();
        include FIFU_ADMIN_DIR . '/html/category.html';
    }

    /**
     * Persist image URL and ALT from the product category form into term meta.
     *
     * This mirrors the legacy behavior of fifu_ctgr_save_properties() but
     * uses the new FIFU_Term_Meta_Updater and attachment sync service.
     *
     * @param int $term_id The term ID.
     * @return void
     */
    public static function save_image_meta(int $term_id): void
    {
        // Only handle WooCommerce product categories.
        if (!isset($_POST['taxonomy']) || $_POST['taxonomy'] !== 'product_cat') {
            return;
        }

        if (!class_exists('FIFU_Term_Meta_Updater')) {
            return;
        }

        $updater = FIFU_Term_Meta_Updater::instance();
        $hasImageAltPayload = isset($_POST['fifu_input_alt']);
        $hasImageUrlPayload = isset($_POST['fifu_input_url']);
        $expectedImageAlt = null;
        $expectedImageUrl = null;

        // ALT.
        if ($hasImageAltPayload) {
            $alt_raw = wp_unslash((string) ($_POST['fifu_input_alt'] ?? ''));
            $alt     = sanitize_text_field($alt_raw);
            $expectedImageAlt = $alt === '' ? null : $alt;

            if ($alt === '') {
                $updater->delete($term_id, 'fifu_image_alt');
            } else {
                $updater->update_or_delete_term($term_id, 'fifu_image_alt', $alt);
            }
        }

        // URL.
        if ($hasImageUrlPayload) {
            $url_raw = wp_unslash((string) ($_POST['fifu_input_url'] ?? ''));
            $url     = esc_url_raw(rtrim($url_raw));
            $expectedImageUrl = $url === '' ? null : self::normalize_term_scalar(
                function_exists('fifu_convert') ? fifu_convert($url) : $url
            );

            if ($url === '') {
                $updater->update_or_delete_term($term_id, 'fifu_image_url', null);

                if (class_exists('Fifu_Post_Attachment_Sync_Service')) {
                    Fifu_Post_Attachment_Sync_Service::sync_category_attachment($term_id);
                }

                return;
            }

            $updater->update_or_delete_term(
                $term_id,
                'fifu_image_url',
                $url
            );
        }

        // Keep the fake attachment in sync with the new term meta values.
        if (class_exists('Fifu_Post_Attachment_Sync_Service')) {
            Fifu_Post_Attachment_Sync_Service::
                sync_category_attachment($term_id);
        }

        self::cleanup_legacy_image_meta_after_db2_sync(
            $term_id,
            $expectedImageUrl,
            $hasImageUrlPayload,
            $expectedImageAlt,
            $hasImageAltPayload
        );
    }

    private static function cleanup_legacy_image_meta_after_db2_sync(
        int $termId,
        ?string $expectedImageUrl,
        bool $hasImageUrlPayload,
        ?string $expectedImageAlt,
        bool $hasImageAltPayload
    ): void {
        if (!function_exists('fifu_db2_manager')) {
            return;
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return;
        }

        if ($hasImageUrlPayload) {
            $mapping = $manager->getTermMapping($termId, 'image');
            $db2ImageUrl = self::normalize_term_scalar(
                is_array($mapping) ? (string) ($mapping['url'] ?? '') : null
            );

            if ($db2ImageUrl === $expectedImageUrl) {
                delete_term_meta($termId, 'fifu_image_url');
            }
        }

        if ($hasImageAltPayload) {
            $mapping = $manager->getTermAltMapping($termId, 'image');
            $db2ImageAlt = self::normalize_term_scalar(
                is_array($mapping) ? (string) (($mapping['alt'] ?? $mapping['value'] ?? '')) : null
            );

            if ($db2ImageAlt === $expectedImageAlt) {
                delete_term_meta($termId, 'fifu_image_alt');
            }
        }
    }

    private static function normalize_term_scalar(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
