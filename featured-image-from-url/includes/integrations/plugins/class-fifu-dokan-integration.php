<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides Dokan-specific form fields and asset management.
 */
final class Fifu_Dokan_Integration
{
    /**
     * Registers Dokan hooks that previously lived in admin/meta-box.php.
     */
    public static function register_hooks(): void
    {
        add_action(
            'dokan_new_product_after_product_tags',
            [self::class, 'render_new_product_image_field'],
            10
        );
        add_action(
            'dokan_product_edit_after_product_tags',
            [self::class, 'render_edit_product_image_field'],
            99,
            2
        );
        add_action(
            'dokan_new_product_added',
            [self::class, 'on_product_save'],
            10,
            2
        );
        add_action(
            'dokan_product_updated',
            [self::class, 'on_product_save'],
            10,
            2
        );
    }

    /**
     * Enqueues assets shared with both Dokan hooks.
     */
    private static function enqueue_assets(): void
    {
        wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

        wp_enqueue_script('fifu-convert-url-js', plugins_url('/html/js/convert-url.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());

    }

    private static function render_active_media_fields(?WP_Post $post): void
    {
        if (!$post instanceof WP_Post) {
            $post = new WP_Post((object) ['ID' => 0]);
        }

        Fifu_Admin_Meta_Box_Renderer::render_featured_image_box($post);
    }

    /**
     * Outputs the image field for the new Dokan product screen.
     */
    public static function render_new_product_image_field(): void
    {
        $fifu = Fifu_Dokan_Strings::get_strings();
        ?>

        <div class="dokan-form-group">
            <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
            <input type="text" class="dokan-form-control" name="fifu_input_url" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
        </div>

        <?php
        self::enqueue_assets();

        self::render_active_media_fields(get_post());
    }

    /**
     * Outputs the image field for editing an existing Dokan product.
     *
     * @param mixed $post
     * @param mixed $post_id
     */
    public static function render_edit_product_image_field($post, $post_id): void
    {
        $post_id = is_numeric($post_id) ? (int) $post_id : 0;
        if ($post_id <= 0) {
            return;
        }

        $fifu = Fifu_Dokan_Strings::get_strings();
        $url = esc_url(Fifu_Post_Image_Url_Read_Service::get_image_url((int) $post_id) ?? '');
        ?>

        <div class="dokan-form-group">
            <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
            <input type="text" class="dokan-form-control" name="fifu_input_url" value="<?php echo $url; ?>" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
        </div>

        <?php
        self::enqueue_assets();

        self::render_active_media_fields($post);
    }

    /**
     * Handles Dokan product creation/update.
     * It will eventually replace fifu_dokan_save_meta().
     *
     * @param mixed $postId
     * @param mixed $data
     */
    public static function on_product_save($postId, $data): void
    {
        $postId = is_numeric($postId) ? (int) $postId : 0;
        if ($postId <= 0 || !is_array($data)) {
            return;
        }

        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller(get_current_user_id())) {
            return;
        }

        $postMetaUpdater = FIFU_Post_Meta_Updater::instance();
        $rawUrl = $data['fifu_input_url'] ?? '';

        if ($rawUrl !== null && !is_scalar($rawUrl)) {
            return;
        }

        $url = esc_url_raw(rtrim((string) ($rawUrl ?? '')));
        $postMetaUpdater->update_or_delete($postId, 'fifu_image_url', $url);

        Fifu_Post_Attachment_Sync_Service::sync_attachments($postId);
    }
}
