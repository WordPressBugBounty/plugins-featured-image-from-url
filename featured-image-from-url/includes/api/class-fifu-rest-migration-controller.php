<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Rest_Migration_Controller
{
    private const DEACTIVATION_FEEDBACK_ENDPOINT = 'https://survey.featuredimagefromurl.com/deactivate/';

    public static function register_routes(): void
    {
        $routes = [
            ['route' => '/metadata_counter_api/', 'callback' => [self::class, 'metadata_counter']],
            ['route' => '/enable_fake_api/', 'callback' => [self::class, 'enable_fake']],
            ['route' => '/disable_fake_api/', 'callback' => [self::class, 'disable_fake']],
            ['route' => '/data_clean_api/', 'callback' => [self::class, 'data_clean']],
            ['route' => '/run_delete_all_api/', 'callback' => [self::class, 'run_delete_all']],
            ['route' => '/pre_deactivate/', 'callback' => [self::class, 'pre_deactivate']],
            ['route' => '/feedback/', 'callback' => [self::class, 'feedback']],
            ['route' => '/metain_status_api/', 'callback' => [self::class, 'metain_status']],
            ['route' => '/metain_batch_api/', 'callback' => [self::class, 'metain_batch']],
            ['route' => '/metaout_status_api/', 'callback' => [self::class, 'metaout_status']],
            ['route' => '/metaout_batch_api/', 'callback' => [self::class, 'metaout_batch']],
        ];

        foreach ($routes as $route) {
            register_rest_route(
                FIFU_REST_NAMESPACE_V2,
                $route['route'],
                [
                    'methods' => 'POST',
                    'callback' => $route['callback'],
                    'permission_callback' => [Fifu_Rest_Permissions::class, 'private_data'],
                ]
            );
        }
    }

    public static function metadata_counter(\WP_REST_Request $request)
    {
        $use_transient = filter_var($request['transient'], FILTER_VALIDATE_BOOLEAN);
        $total = $use_transient ? Fifu_Transient_Manager::get('fifu_metadata_counter') : false;
        if (false === $total) {
            $stats = new Fifu_Migration_Stats();
            $total = $stats->count_metadata_operations();
            Fifu_Transient_Manager::set('fifu_metadata_counter', $total, 0);
        }

        return $total;
    }

    public static function enable_fake(\WP_REST_Request $request)
    {
        update_option('fifu_fake_stop', false, 'no');
        Fifu_Meta_Maintenance_Controller::clear_meta_out();
        try {
            return new WP_REST_Response(Fifu_Manual_Metain_Service::start(), 200);
        } catch (Fifu_Manual_Metain_Busy_Exception $e) {
            return self::metain_busy_response();
        }
    }

    public static function disable_fake(\WP_REST_Request $request)
    {
        update_option('fifu_fake_stop', true, 'no');
        return new WP_REST_Response(Fifu_Manual_Metain_Service::pause(), 200);
    }

    public static function metain_status(\WP_REST_Request $request)
    {
        return new WP_REST_Response(Fifu_Manual_Metain_Service::status(), 200);
    }

    public static function metain_batch(\WP_REST_Request $request)
    {
        try {
            return new WP_REST_Response(Fifu_Manual_Metain_Service::process_next_batch(), 200);
        } catch (Fifu_Manual_Metain_Busy_Exception $e) {
            return self::metain_busy_response();
        }
    }

    public static function metaout_status(
        \WP_REST_Request $request
    ) {
        try {
            return new WP_REST_Response(
                Fifu_Manual_Metaout_Service::status(),
                200
            );
        } catch (
            Fifu_Manual_Metaout_Busy_Exception $e
        ) {
            return self::metaout_busy_response();
        }
    }

    public static function metaout_batch(
        \WP_REST_Request $request
    ) {
        try {
            return new WP_REST_Response(
                Fifu_Manual_Metaout_Service::
                    process_next_batch(),
                200
            );
        } catch (
            Fifu_Manual_Metaout_Busy_Exception $e
        ) {
            return self::metaout_busy_response();
        }
    }

    public static function data_clean(\WP_REST_Request $request)
    {
        try {
            return new WP_REST_Response(
                Fifu_Manual_Metaout_Service::start(),
                200
            );
        } catch (
            Fifu_Manual_Metaout_Busy_Exception $e
        ) {
            return self::metaout_busy_response();
        }
    }

    public static function run_delete_all(\WP_REST_Request $request)
    {
        $cleanup = new Fifu_Local_Media_Cleanup();
        $cleanup->delete_all_fifu_meta();
        update_option('fifu_run_delete_all', 'toggleoff', 'no');
        return json_encode([]);
    }

    public static function pre_deactivate(\WP_REST_Request $request)
    {
        self::send_deactivation_feedback($request, false);
        Fifu_Meta_Maintenance_Controller::enable_clean();

        $stats = new Fifu_Migration_Stats();
        $total = $stats->count_metadata_operations();
        Fifu_Transient_Manager::set('fifu_metadata_counter', $total, 0);
        $max_attempts = self::pre_deactivate_max_wait_attempts();
        $attempt = 0;

        while ($total > 0 && $attempt < $max_attempts) {
            wp_cache_flush();
            $total = (int) Fifu_Transient_Manager::get('fifu_metadata_counter');
            if ($total <= 0) {
                break;
            }

            $attempt++;
            sleep(3);
        }

        deactivate_plugins(self::free_plugin_basename());
        return json_encode([]);
    }

    public static function feedback(\WP_REST_Request $request)
    {
        self::send_deactivation_feedback($request, null);
        return json_encode([]);
    }

    private static function metain_busy_response(): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'success' => false,
                'code' => 'fifu_manual_metain_busy',
                'message' => 'Image Metadata is being processed by another request.',
            ],
            409
        );
    }

    private static function metaout_busy_response(): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'success' => false,
                'code' =>
                    'fifu_manual_metaout_busy',
                'message' =>
                    'Clear Metadata is busy. Please try again after the current metadata operation finishes.',
            ],
            409
        );
    }

    private static function free_plugin_basename(): string
    {
        if (defined('FIFU_PLUGIN_BASENAME')) {
            return FIFU_PLUGIN_BASENAME;
        }

        return 'featured-image-from-url/featured-image-from-url.php';
    }

    private static function send_deactivation_feedback(\WP_REST_Request $request, ?bool $temporary_override): void
    {
        $description = sanitize_textarea_field(wp_unslash((string) $request->get_param('description')));
        if ($description === '') {
            return;
        }

        $temporary = $temporary_override ?? filter_var($request->get_param('temporary'), FILTER_VALIDATE_BOOLEAN);
        $current_user = wp_get_current_user();
        $email = '';

        if (is_object($current_user) && method_exists($current_user, 'exists') && $current_user->exists()) {
            $email = sanitize_email((string) ($current_user->user_email ?? ''));
        }

        $image = Fifu_Meta_Stats_Utils::get_last_image_url();
        $payload = [
            'email' => $email,
            'description' => $description,
            'version' => Fifu_Plugin_Info::get_version(),
            'temporary' => $temporary,
            'image' => $image,
            'fifu_cdn_content' => Fifu_Options_Utils::is_on('fifu_cdn_content'),
            'fifu_confirm_delete_all' => (bool) FIFU_DELETE_ALL_URLS,
            'fifu_enable_default_url' => Fifu_Options_Utils::is_on('fifu_enable_default_url'),
            'fifu_fake' => Fifu_Options_Utils::is_on('fifu_fake'),
            'fifu_get_first' => Fifu_Options_Utils::is_on('fifu_get_first'),
            'fifu_hide' => Fifu_Options_Utils::is_on('fifu_hide'),
            'fifu_ovw_first' => Fifu_Options_Utils::is_on('fifu_ovw_first'),
            'fifu_pcontent_add' => Fifu_Options_Utils::is_on('fifu_pcontent_add'),
            'fifu_pcontent_remove' => Fifu_Options_Utils::is_on('fifu_pcontent_remove'),
            'fifu_photon' => Fifu_Options_Utils::is_on('fifu_photon'),
            'fifu_wc_lbox' => Fifu_Options_Utils::is_on('fifu_wc_lbox'),
            'fifu_wc_zoom' => Fifu_Options_Utils::is_on('fifu_wc_zoom'),
        ];
        $body = wp_json_encode($payload);
        if (!is_string($body)) {
            return;
        }

        $endpoint = apply_filters('fifu_deactivation_feedback_endpoint', self::DEACTIVATION_FEEDBACK_ENDPOINT);
        if (!is_string($endpoint) || trim($endpoint) === '') {
            return;
        }

        wp_safe_remote_post(trim($endpoint), [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => $body,
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => false,
            'timeout' => 5,
        ]);
    }

    private static function pre_deactivate_max_wait_attempts(): int
    {
        $attempts = 10;

        if (function_exists('apply_filters')) {
            $attempts = (int) apply_filters('fifu_pre_deactivate_max_wait_attempts', $attempts);
        }

        return max(0, $attempts);
    }

}
