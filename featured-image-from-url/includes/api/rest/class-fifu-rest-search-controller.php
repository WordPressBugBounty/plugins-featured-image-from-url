<?php

class Fifu_Rest_Search_Controller
{
    public static function register_routes(): void
    {
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/unsplash_search/', [
            'methods' => 'POST',
            'callback' => [self::class, 'unsplash_search'],
            'permission_callback' => [
                Fifu_Rest_Permissions::class,
                'private_data',
            ],
        ]);
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
