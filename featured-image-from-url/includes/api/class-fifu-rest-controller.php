<?php
declare(strict_types=1);

final class Fifu_Rest_Controller {

    /**
     * Register REST API routes for general FIFU Cloud and license operations.
     */
    public static function register_routes(): void {
        register_rest_route(FIFU_REST_NAMESPACE_V1, '/url/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => [self::class, 'image_url'],
            'permission_callback' => [Fifu_Rest_Permissions::class, 'private_data'],
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        $private_permissions = [Fifu_Rest_Permissions::class, 'private_data'];

        register_rest_route(FIFU_REST_NAMESPACE_V2, '/sign_up/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'sign_up'],
            'permission_callback' => $private_permissions,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/connected/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'connected'],
            'permission_callback' => $private_permissions,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cancel/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'cancel'],
            'permission_callback' => $private_permissions,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/payment_info/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'payment_info'],
            'permission_callback' => $private_permissions,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/disable_default_api/', array(
            'methods' => 'POST',
            'callback' => [self::class, 'disable_default'],
            'permission_callback' => $private_permissions,
        ));
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/rest_url_api/', array(
            'methods' => array('GET', 'POST'),
            'callback' => [self::class, 'rest_url'],
            'permission_callback' => [Fifu_Rest_Permissions::class, 'public_access'],
        ));
    }

    /**
     * REST callback: return main image URL for a given post.
     */
    public static function image_url(\WP_REST_Request $request) {
        $post_id = (int) ($request['post_id'] ?? 0);
        if ($post_id <= 0) {
            return null;
        }

        /*
         * This endpoint hydrates the FIFU editor field.
         *
         * It must expose only the direct stored FIFU image URL.
         * Fifu_Post_Main_Image_Resolver may return the configured
         * Default Featured Image or another fallback, which must never
         * be written back into the FIFU URL field.
         */
        return Fifu_Post_Image_Url_Read_Service::get_image_url($post_id) ?? '';
    }

    /**
     * REST callback: handle signup.
     */
    public static function sign_up(\WP_REST_Request $request) {
        $email = self::string_param($request, 'email');
        return Fifu_Cloud_License_Service::sign_up($email);
    }

    private static function string_param(\WP_REST_Request $request, string $key): string {
        $value = $request[$key] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * REST callback: cancel subscription.
     */
    public static function cancel(\WP_REST_Request $request) {
        return Fifu_Cloud_License_Service::cancel();
    }

    /**
     * REST callback: get payment info.
     */
    public static function payment_info(\WP_REST_Request $request) {
        return Fifu_Cloud_License_Service::payment_info();
    }

    /**
     * REST callback: check if the site is connected to FIFU Cloud.
     */
    public static function connected(\WP_REST_Request $request) {
        return Fifu_Cloud_License_Service::connected();
    }

    /**
     * REST callback: disable the default URL.
     */
    public static function disable_default(\WP_REST_Request $request) {
        Fifu_Default_Image_Service::delete_default_image();
        return json_encode(array());
    }

    /**
     * REST callback: return the site REST URL.
     */
    public static function rest_url(\WP_REST_Request $request) {
        return get_rest_url();
    }

}
