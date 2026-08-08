<?php

class Fifu_Rest_Metadata_Worker_Controller {

    /**
     * Registers metadata worker routes migrated from admin/api.php.
     */
    public static function register_routes(): void {
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/metaout/', array(
            'methods'             => 'POST',
            'callback'            => array(self::class, 'process_meta_out'),
            'permission_callback' => array(self::class, 'meta_out_permission_check'),
            'args'                => array(
                'post_id' => array(
                    'required'          => true,
                    'validate_callback' => array(self::class, 'validate_meta_out_post_id'),
                ),
            ),
        ));

        register_rest_route(FIFU_REST_NAMESPACE_V2, '/metain/', array(
            'methods'             => 'POST',
            'callback'            => array(self::class, 'process_meta_in'),
            'permission_callback' => array(self::class, 'meta_in_permission_check'),
            'args'                => array(
                'post_id' => array(
                    'required'          => true,
                    'validate_callback' => array(self::class, 'validate_meta_in_post_id'),
                ),
            ),
        ));
    }

    /**
     * Migrates fifu_api_content() into the OO controller.
     */
    public static function process_meta_out(WP_REST_Request $request): WP_REST_Response {
        $id = $request->get_param('post_id');

        $queueRepository = new Fifu_Metadata_Queue_Repository();
        $type = $queueRepository->get_meta_out_type($id);

        switch ($type) {
            case 'att':
                Fifu_Metadata_Import_Service::process_att_meta_out_row($id);
                break;

            case 'term':
                Fifu_Metadata_Import_Service::process_term_meta_out_row($id);
                break;

            default:
                // Leave an unknown row untouched instead of recursively
                // selecting the same row forever.
                return new WP_REST_Response('', 200);
        }

        self::update_metadata_counter();

        return self::process_next_meta_out($queueRepository);
    }

    public static function process_meta_in(WP_REST_Request $request): WP_REST_Response {
        if (
            filter_var(
                get_option('fifu_fake_stop', false),
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            return new WP_REST_Response('', 200);
        }

        $id = $request->get_param('post_id');

        $queueRepository = new Fifu_Metadata_Queue_Repository();
        $type = $queueRepository->get_meta_in_type($id);

        switch ($type) {
            case 'post':
                Fifu_Metadata_Import_Service::process_post_meta_in_row($id);
                break;

            case 'term':
                Fifu_Metadata_Import_Service::process_term_meta_in_row($id);
                break;

            default:
                // Leave an unknown row untouched instead of recursively
                // selecting the same row forever.
                return new WP_REST_Response('', 200);
        }

        self::update_metadata_counter();

        return self::process_next_meta_in($queueRepository);
    }

    private static function process_next_meta_out(
        Fifu_Metadata_Queue_Repository $queueRepository
    ): WP_REST_Response {
        $remaining = $queueRepository->get_meta_out();
        $nextEntry = $remaining[0] ?? null;
        $nextId = is_object($nextEntry)
            ? (string) ($nextEntry->post_id ?? '')
            : '';

        if ($nextId !== '' && is_numeric($nextId)) {
            $nextRequest = new WP_REST_Request();
            $nextRequest->set_param('post_id', $nextId);

            return self::process_meta_out($nextRequest);
        }

        return new WP_REST_Response('', 200);
    }

    private static function process_next_meta_in(
        Fifu_Metadata_Queue_Repository $queueRepository
    ): WP_REST_Response {
        $remaining = $queueRepository->get_meta_in();
        $nextEntry = $remaining[0] ?? null;
        $nextId = is_object($nextEntry)
            ? (string) ($nextEntry->post_id ?? '')
            : '';

        if ($nextId !== '' && is_numeric($nextId)) {
            $nextRequest = new WP_REST_Request();
            $nextRequest->set_param('post_id', $nextId);

            return self::process_meta_in($nextRequest);
        }

        return new WP_REST_Response('', 200);
    }

    /**
     * Checks that the incoming X-FIFU-Authorization header matches the stored key for content routes.
     */
    public static function meta_in_permission_check(WP_REST_Request $request): bool {
        Fifu_File_Logger::plugin([
            '/metain/' => [
                'checking...' => 'permission',
            ],
        ]);

        $authorized = self::validate_local_worker_token(
            $request,
            'fifu_api_metain_auth_token'
        );

        Fifu_File_Logger::plugin([
            '/metain/' => [
                'authorized...' => $authorized,
            ],
        ]);

        return $authorized;
    }

    public static function meta_out_permission_check(WP_REST_Request $request): bool {
        Fifu_File_Logger::plugin([
            '/metaout/' => [
                'checking...' => 'permission',
            ],
        ]);

        $authorized = self::validate_local_worker_token(
            $request,
            'fifu_api_metaout_auth_token'
        );

        Fifu_File_Logger::plugin([
            '/metaout/' => [
                'authorized...' => $authorized,
            ],
        ]);

        return $authorized;
    }

    /**
     * Returns true when the token matches the saved FIFU WS key.
     */
    private static function validate_local_worker_token(
        WP_REST_Request $request,
        string $transientKey
    ): bool {
        $providedToken = (string) $request->get_header(
            'X-FIFU-Authorization'
        );

        if ($providedToken === '') {
            return false;
        }

        $storedToken = Fifu_Transient_Manager::get($transientKey);

        if (
            $storedToken === false
            || !is_scalar($storedToken)
        ) {
            return false;
        }

        $storedToken = (string) $storedToken;

        if (
            $storedToken === ''
            || !hash_equals($storedToken, $providedToken)
        ) {
            return false;
        }

        Fifu_Transient_Manager::delete($transientKey);

        return true;
    }

    private static function update_metadata_counter(): void {
        $stats = new Fifu_Migration_Stats();
        $total = $stats->count_metadata_operations();

        Fifu_Transient_Manager::set(
            'fifu_metadata_counter',
            $total,
            0
        );
    }

    /**
     * Logs validation attempts and ensures the ID is numeric for meta-in requests.
     */
    public static function validate_meta_in_post_id($param, WP_REST_Request $request, $key): bool {
        Fifu_File_Logger::plugin(['/metain/' => ['validating...' => 'data',]]);
        return self::validate_numeric_id($param, $request, $key);
    }

    /**
     * Logs validation attempts and ensures the ID is numeric for meta-out requests.
     */
    public static function validate_meta_out_post_id($param, WP_REST_Request $request, $key): bool {
        Fifu_File_Logger::plugin(['/metaout/' => ['validating...' => 'data',]]);
        return self::validate_numeric_id($param, $request, $key);
    }

    /**
     * Ensures the provided parameter is numeric.
     */
    public static function validate_numeric_id($param, WP_REST_Request $request, $key): bool {
        return is_numeric($param);
    }
}
