<?php
declare(strict_types=1);

final class Fifu_Rest_Media_Controller {

    /**
     * Register REST API routes for media-related endpoints.
     */
    public static function register_routes(): void {
        $permission = [Fifu_Rest_Permissions::class, 'private_data'];
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/create_thumbnails_list/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'create_thumbnails_list'],
            'permission_callback' => $permission,
        ));

        register_rest_route(FIFU_REST_NAMESPACE_V2, '/list_all_media_library/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'list_all_media_library'],
            'permission_callback' => $permission,
        ));

    }

    /**
     * REST callback: create a thumbnails list from selected images.
     */
    public static function create_thumbnails_list(\WP_REST_Request $request) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $images = $request['selected'] ?? [];
        return Fifu_Cloud_Media_Service::create_thumbnails_list($images, false);
    }

    public static function list_all_media_library(\WP_REST_Request $request)
    {
        $page = (int) ($request['page'] ?? 0);
        $type = $request['type'] ?? null;
        $keyword = $request['keyword'] ?? null;

        $repository = function_exists('fifu_db2_speed_up_repository')
            ? fifu_db2_speed_up_repository()
            : null;

        $results = $repository
            ? $repository->get_posts_with_internal_featured_image($page, $type, $keyword)
            : [];

        if (is_array($results)) {
            foreach ($results as $item) {
                if (!is_object($item)) {
                    continue;
                }

                $item->url = esc_url_raw($item->url ?? '');
                $item->post_title = sanitize_text_field($item->post_title ?? '');
                $item->post_name = sanitize_text_field($item->post_name ?? '');
                $item->post_date = sanitize_text_field($item->post_date ?? '');
                $item->gallery_ids = isset($item->gallery_ids)
                    ? sanitize_text_field($item->gallery_ids)
                    : null;
                $item->post_id = intval($item->post_id ?? 0);
                $item->thumbnail_id = intval($item->thumbnail_id ?? 0);
                $item->category = isset($item->category)
                    ? (int) (!!$item->category)
                    : 0;
            }
        }

        return new \WP_REST_Response($results);
    }

}
