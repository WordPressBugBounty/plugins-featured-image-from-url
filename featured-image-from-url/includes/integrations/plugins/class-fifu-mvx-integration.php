<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles MVX product manager enhancements previously managed procedurally.
 */
final class Fifu_Mvx_Integration
{
    /**
     * Registers MVX-specific hooks.
     */
    public static function register_hooks(): void
    {
        remove_action(
            'mvx_product_manager_right_panel_after',
            [self::class, 'render_product_manager_image_field'],
            10
        );
        add_action(
            'mvx_product_manager_right_panel_after',
            [self::class, 'render_product_manager_image_field'],
            10,
            1
        );
        remove_action(
            'mvx_process_product_object',
            [self::class, 'on_process_product_object'],
            10
        );
        add_action(
            'mvx_process_product_object',
            [self::class, 'on_process_product_object'],
            10,
            1
        );
    }

    /**
     * Enqueues the assets required for MVX product manager fields.
     */
    private static function enqueue_assets(): void
    {
        wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

        wp_enqueue_script('fifu-convert-url-js', plugins_url('/html/js/convert-url.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());

    }

    /**
     * Normalizes MVX and reader values to a safe image URL string.
     *
     * @param mixed $value
     */
    private static function normalize_image_url_input($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === null || $value === false) {
            return '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        $url = trim((string) $value);
        return $url === '0' ? '' : $url;
    }

    /**
     * Returns the current image URL using the canonical FIFU reader.
     */
    private static function get_current_image_url(int $post_id): string
    {
        if (class_exists(Fifu_Post_Image_Url_Read_Service::class)) {
            return self::normalize_image_url_input(
                Fifu_Post_Image_Url_Read_Service::get_image_url($post_id)
            );
        }

        if (function_exists('get_post_meta')) {
            return self::normalize_image_url_input(get_post_meta($post_id, 'fifu_image_url', true));
        }

        return '';
    }

    /**
     * Displays the MVX panel field rendered after the right panel controls.
     *
     * @param int $post_id
     */
    public static function render_product_manager_image_field(int $post_id): void
    {
        $fifu = Fifu_Dokan_Strings::get_strings();
        $url = esc_url(self::get_current_image_url((int) $post_id));
        ?>

        <br>

        <div>
            <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
            <br>
            <input class="form-control" type="url" name="fifu_input_url" value="<?php echo $url; ?>" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
        </div>

        <?php
        self::enqueue_assets();
    }

    /**
     * Handles MVX product processing.
     * It will eventually replace fifu_mvx_process_product_object().
     *
     * @param mixed $data
     */
    public static function on_process_product_object($data): void
    {
        if (!is_object($data) || !method_exists($data, 'get_meta')) {
            return;
        }

        $post_id = 0;
        if (method_exists($data, 'get_id')) {
            $post_id = (int) $data->get_id();
        } elseif (isset($data->id) && is_numeric($data->id)) {
            $post_id = (int) $data->id;
        }

        if ($post_id <= 0) {
            return;
        }

        $url = self::normalize_image_url_input($data->get_meta('fifu_image_url'));
        Fifu_Developer_Media_Service::set_image($post_id, $url);
    }
}
