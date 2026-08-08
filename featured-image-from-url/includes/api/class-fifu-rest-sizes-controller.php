<?php
declare(strict_types=1);

final class Fifu_Rest_Sizes_Controller {

    /**
     * Register REST API routes for image sizes detection and configuration.
     */
    public static function register_routes(): void {
        $permission = [Fifu_Rest_Permissions::class, 'private_data'];
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/load-sizes-api/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'load_sizes'],
            'permission_callback' => $permission,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/reset-sizes-api/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'reset_sizes'],
            'permission_callback' => $permission,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/save-sizes-api/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'save_sizes'],
            'permission_callback' => $permission,
        ));
    }

    /**
     * REST callback: load all image sizes.
     */
    public static function load_sizes(\WP_REST_Request $request) {
        return Fifu_Image_Size_Service::load_sizes();
    }

    /**
     * REST callback: reset image sizes.
     */
    public static function reset_sizes(\WP_REST_Request $request) {
        Fifu_Image_Size_Service::reset_sizes();
        return json_encode(array());
    }

    /**
     * REST callback: save image sizes from the request body.
     */
    public static function save_sizes(\WP_REST_Request $request) {
        $sizes = json_decode($request->get_body(), true);
        if (!is_array($sizes)) {
            return json_encode(array());
        }

        Fifu_Image_Size_Service::save_sizes($sizes);
        return json_encode(array());
    }
}
