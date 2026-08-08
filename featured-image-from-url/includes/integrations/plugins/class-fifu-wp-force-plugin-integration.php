<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Wp_Force_Plugin_Integration {

    public static function register_hooks(): void {
        // Register filter to bypass wp-force-plugin authentication for FIFU routes.
        add_filter(
            'rest_authentication_errors',
            [ self::class, 'allow_fifu_routes' ],
            5,
            1
        );
    }

    public static function allow_fifu_routes($result) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        if ($request_uri === '') {
            return $result;
        }

        $route_prefix = '/' . FIFU_SLUG . '/';
        $pretty_rest_prefix = '/wp-json' . $route_prefix;
        $path = parse_url($request_uri, PHP_URL_PATH);

        if (is_string($path) && strpos($path, $pretty_rest_prefix) !== false) {
            return true;
        }

        $query = parse_url($request_uri, PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            $query_args = [];
            parse_str($query, $query_args);

            $rest_route = isset($query_args['rest_route'])
                ? '/' . ltrim((string) $query_args['rest_route'], '/')
                : '';

            if ($rest_route !== '' && strpos($rest_route, $route_prefix) === 0) {
                return true;
            }
        }

        return $result;
    }
}
