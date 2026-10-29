<?php

class Fifu_Rest_Search_Controller
{
    public static function register_routes(): void
    {
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/unsplash_search/', [
            'methods' => 'POST',
            'callback' => [self::class, 'unsplash_search'],
            'permission_callback' => [self::class, 'search_permission'],
        ]);
    }

    public static function search_permission(\WP_REST_Request $request)
    {
        if (!current_user_can('upload_files')) {
            return self::authorization_error();
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce !== '' && !wp_verify_nonce($nonce, 'wp_rest')) {
            return self::authorization_error();
        }

        return true;
    }

    private static function authorization_error(): \WP_Error
    {
        return new \WP_Error(
            'rest_forbidden',
            __('Sorry, you are not allowed to do that.'),
            ['status' => rest_authorization_required_code()]
        );
    }

    public static function unsplash_search(
        WP_REST_Request $request
    ): WP_REST_Response {
        $keywords = trim((string) $request->get_param('keywords'));

        if ($keywords === '') {
            return new WP_REST_Response([], 200);
        }

        $page = absint($request->get_param('page'));

        if ($page <= 0) {
            $page = 1;
        }

        $searchService = new Fifu_Unsplash_Image_Service();
        $results = $searchService->search($keywords, $page);

        if (!is_array($results)) {
            $results = [];
        }

        return new WP_REST_Response($results, 200);
    }
}
