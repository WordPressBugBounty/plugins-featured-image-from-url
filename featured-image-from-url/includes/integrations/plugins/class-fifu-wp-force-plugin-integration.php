<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Wp_Force_Plugin_Integration {

    private const REST_AUTHENTICATION_HOOK = 'rest_authentication_errors';
    private const WP_FORCE_REST_CALLBACK = 'v_forcelogin_rest_access';

    public static function register_hooks(): void {
        /*
         * Only install the integration when Force Login's exact REST
         * authentication callback is active.
         *
         * This keeps FIFU completely out of the authentication pipeline
         * on sites that do not use Force Login.
         */
        $priority = has_filter(
            self::REST_AUTHENTICATION_HOOK,
            self::WP_FORCE_REST_CALLBACK
        );

        if ($priority === false || !is_callable(self::WP_FORCE_REST_CALLBACK)) {
            return;
        }

        /*
         * Replace Force Login's REST callback with a FIFU-aware wrapper,
         * preserving its original priority.
         *
         * For non-FIFU routes the wrapper delegates to Force Login.
         * For FIFU routes it returns the incoming authentication result
         * unchanged, allowing WordPress authentication handlers that run
         * later (including REST cookie/nonce validation) to continue.
         */
        remove_filter(
            self::REST_AUTHENTICATION_HOOK,
            self::WP_FORCE_REST_CALLBACK,
            (int) $priority
        );

        add_filter(
            self::REST_AUTHENTICATION_HOOK,
            [self::class, 'allow_fifu_routes'],
            (int) $priority,
            1
        );
    }

    public static function allow_fifu_routes($result) {
        if (self::is_fifu_route()) {
            return $result;
        }

        return call_user_func(self::WP_FORCE_REST_CALLBACK, $result);
    }

    private static function is_fifu_route(): bool {
        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '';

        if ($request_uri === '') {
            return false;
        }

        $route_prefix = '/' . FIFU_SLUG . '/';
        $pretty_rest_prefix = '/wp-json' . $route_prefix;
        $path = parse_url($request_uri, PHP_URL_PATH);

        if (is_string($path) && strpos($path, $pretty_rest_prefix) !== false) {
            return true;
        }

        $query = parse_url($request_uri, PHP_URL_QUERY);

        if (!is_string($query) || $query === '') {
            return false;
        }

        $query_args = [];
        parse_str($query, $query_args);

        $rest_route = isset($query_args['rest_route'])
            ? '/' . ltrim((string) $query_args['rest_route'], '/')
            : '';

        return $rest_route !== ''
            && strpos($rest_route, $route_prefix) === 0;
    }
}
