<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Deprecated generic debug REST controller.
 *
 * The old generic debug endpoints were intentionally removed because they exposed
 * database/debug details and log files through REST. Keep the class as an inert
 * compatibility shim for old bootstrap references, tests, and third-party code
 * that may check for its existence.
 */
final class Fifu_Meta_Debug_Controller
{
    public static function register_routes(): void
    {
        // Intentionally no-op. Generic debug REST endpoints were removed.
    }

    public static function setup_rest_routes(): void
    {
        // Intentionally no-op. Generic debug REST endpoints were removed.
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public static function alias_rest_endpoints(array $endpoints): array
    {
        return $endpoints;
    }

    public static function permission_callback()
    {
        return false;
    }

    public static function ensure_debug_permission()
    {
        return false;
    }

    public static function handle_slug(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    public static function handle_postmeta(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    public static function handle_posts(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    public static function handle_metain(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    public static function handle_metaout(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    public static function handle_log(WP_REST_Request $request): WP_REST_Response
    {
        return self::removed_response();
    }

    private static function removed_response(): WP_REST_Response
    {
        return new WP_REST_Response('Generic debug REST endpoint removed', 410);
    }
}
