<?php

function fifu_api_debug_slug(WP_REST_Request $request) {
    $slug = $request->get_param('slug');
    $posts = fifu_db_debug_slug($slug);
    return new WP_REST_Response($posts, 200);
}

function fifu_api_debug_postmeta(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $postmeta = fifu_db_debug_postmeta($post_id);
    return new WP_REST_Response($postmeta, 200);
}

function fifu_api_debug_posts(WP_REST_Request $request) {
    $id = $request->get_param('id');
    $posts = fifu_db_debug_posts($id);
    return new WP_REST_Response($posts, 200);
}

function fifu_api_debug_metain(WP_REST_Request $request) {
    $metain = fifu_db_debug_metain();
    return new WP_REST_Response($metain, 200);
}

function fifu_api_debug_metaout(WP_REST_Request $request) {
    $metaout = fifu_db_debug_metaout();
    return new WP_REST_Response($metaout, 200);
}

add_action('rest_api_init', function () {
    register_rest_route('featured-image-from-url/v2', '/debug-slug/(?P<slug>[a-z0-9-_]+)', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_debug_slug',
        'permission_callback' => function ($request) {
            return fifu_is_on('fifu_debug');
        },
        'args' => array(
            'slug' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return preg_match('/^[a-z0-9-_]+$/', $param); // Regex to validate the slug
                }
            ),
        ),
    ));
    register_rest_route('featured-image-from-url/v2', '/debug-postmeta/(?P<post_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_debug_postmeta',
        'permission_callback' => function ($request) {
            return fifu_is_on('fifu_debug');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param); // Validate that the parameter is numeric
                }
            ),
        ),
    ));
    register_rest_route('featured-image-from-url/v2', '/debug-posts/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_debug_posts',
        'permission_callback' => function ($request) {
            return fifu_is_on('fifu_debug');
        },
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param); // Validate that the parameter is numeric
                }
            ),
        ),
    ));
    register_rest_route('featured-image-from-url/v2', '/debug-metain/', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_debug_metain',
        'permission_callback' => function ($request) {
            return fifu_is_on('fifu_debug');
        },
    ));
    register_rest_route('featured-image-from-url/v2', '/debug-metaout/', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_debug_metaout',
        'permission_callback' => function ($request) {
            return fifu_is_on('fifu_debug');
        },
    ));
});
