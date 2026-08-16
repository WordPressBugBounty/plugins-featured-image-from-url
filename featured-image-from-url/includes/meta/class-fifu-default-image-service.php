<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('Fifu_Attachment_Update_Service', false)) {
    require_once __DIR__ . '/class-fifu-attachment-update-service.php';
}
if (!class_exists('Fifu_Attachment_Factory', false)) {
    require_once __DIR__ . '/class-fifu-attachment-factory.php';
}

/**
 * Service responsible for applying and maintaining the default FIFU image.
 */
final class Fifu_Default_Image_Service
{
    /**
     * Applies the configured default attachment ID as `_thumbnail_id`
     * for posts matching the configured post types and without a featured image.
     *
     * @return void
     */
    public static function apply_default_to_all_missing_thumbnails(): void
    {
        global $wpdb;

        $att_id = (int) get_option('fifu_default_attach_id');
        if (!$att_id) {
            return;
        }

        $default_url = trim(htmlspecialchars_decode((string) get_option('fifu_default_url')));
        if (!self::ensure_default_attachment_ready($att_id, $default_url)) {
            return;
        }

        $post_types_csv = Fifu_Db2_Sql_Helper::sanitize_post_types_list(
            (string) get_option('fifu_default_cpt'),
            Fifu_Db2_Sql_Helper::get_types_for_in_clause()
        );

        $meta_gap_repo = new Fifu_Meta_Gap_Repository();
        $tuples = [];
        $post_ids = [];
        foreach ($meta_gap_repo->get_posts_without_featured_image($post_types_csv) as $res) {
            $post_id = isset($res->post_id) ? (int) $res->post_id : (int) ($res->id ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $tuples[] = $wpdb->prepare("(%d, %s, %d)", $post_id, '_thumbnail_id', $att_id);
            $post_ids[] = $post_id;
        }

        if ($tuples) {
            self::insert_default_thumbnail_rows(implode(',', $tuples));

            foreach (array_unique($post_ids) as $post_id) {
                wp_cache_delete($post_id, 'post_meta');
            }
        }
    }

    /**
     * Updates the default attachment URL when the default URL option changes.
     *
     * @param string $url
     * @return void
     */
    public static function update_default_url(string $url): void
    {
        $old_att_id = (int) get_option('fifu_default_attach_id');
        if (!$old_att_id) {
            return;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            self::delete_default_image();
            return;
        }

        $old_url = Fifu_Attachment_Update_Service::get_attachment_remote_url($old_att_id);
        if ($old_url === $url) {
            self::ensure_fifu_owned_default_attachment_initialized($old_att_id, $url);
            return;
        }

        $factory = new Fifu_Attachment_Factory();
        $new_att_id = $factory->create_attachment_for_url($url, null, 0);
        if ($new_att_id <= 0) {
            return;
        }

        if (!self::can_use_default_attachment($new_att_id)) {
            return;
        }

        Fifu_Attachment_Update_Service::initialize_remote_attachment($new_att_id, $url, null);

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = %d WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
                $new_att_id,
                $old_att_id
            )
        );

        update_option('fifu_default_attach_id', $new_att_id, 'no');

        if (Fifu_Attachment_Update_Service::is_fifu_owned($old_att_id)) {
            wp_delete_attachment($old_att_id, true);
        }
    }

    /**
     * Deletes the default attachment and clears related options/meta.
     *
     * @return void
     */
    public static function delete_default_image(): void
    {
        $att_id = (int) get_option('fifu_default_attach_id');
        if (!$att_id) {
            return;
        }

        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            [
                'meta_key' => '_thumbnail_id',
                'meta_value' => $att_id,
            ]
        );

        delete_option('fifu_default_attach_id');

        if (Fifu_Attachment_Update_Service::is_fifu_owned($att_id)) {
            wp_delete_attachment($att_id, true);
        }
    }

    /**
     * Adds the default image as `_thumbnail_id` for a single post ID.
     *
     * @param int $post_id
     * @return void
     */
    public static function add_default_image_to_post(int $post_id): void
    {
        if (Fifu_Options_Utils::is_off('fifu_enable_default_url')) {
            return;
        }

        if (!self::is_default_post_type_allowed($post_id)) {
            return;
        }

        $att_id = (int) get_option('fifu_default_attach_id');
        if (!$att_id) {
            return;
        }

        $default_url = trim(htmlspecialchars_decode((string) get_option('fifu_default_url')));
        if (!self::ensure_default_attachment_ready($att_id, $default_url)) {
            return;
        }

        $current_thumbnail_id = (int) get_post_meta($post_id, '_thumbnail_id', true);
        if ($current_thumbnail_id > 0 && $current_thumbnail_id !== $att_id) {
            return;
        }

        update_post_meta($post_id, '_thumbnail_id', $att_id);
    }

    private static function is_default_post_type_allowed(int $post_id): bool
    {
        return Fifu_Post_Type_Utils::is_valid_default_cpt(
            $post_id
        );
    }

    private static function can_use_default_attachment(int $att_id): bool
    {
        if ($att_id <= 0) {
            return false;
        }

        $post = get_post($att_id);
        return $post instanceof WP_Post && $post->post_type === 'attachment';
    }

    private static function ensure_fifu_owned_default_attachment_initialized(int $att_id, string $url): bool
    {
        if (!self::can_use_default_attachment($att_id)) {
            return false;
        }

        if (!Fifu_Attachment_Update_Service::is_fifu_owned($att_id)) {
            return true;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            return true;
        }

        $current_url = Fifu_Attachment_Update_Service::get_attachment_remote_url($att_id);
        if ($current_url === '' || $current_url === $url) {
            Fifu_Attachment_Update_Service::initialize_remote_attachment($att_id, $url, null);
            return true;
        }

        return false;
    }

    private static function ensure_default_attachment_ready(int $att_id, string $url): bool
    {
        if (!self::can_use_default_attachment($att_id)) {
            return false;
        }

        if (!Fifu_Attachment_Update_Service::is_fifu_owned($att_id)) {
            return true;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            return true;
        }

        $current_url = Fifu_Attachment_Update_Service::get_attachment_remote_url($att_id);
        if ($current_url === '' || $current_url === $url) {
            Fifu_Attachment_Update_Service::initialize_remote_attachment($att_id, $url, null);
            return true;
        }

        return false;
    }

    private static function insert_default_thumbnail_rows(string $values_sql): void
    {
        if (!$values_sql) {
            return;
        }

        global $wpdb;
        $wpdb->query("
            INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
            VALUES {$values_sql}
        ");
    }
}
