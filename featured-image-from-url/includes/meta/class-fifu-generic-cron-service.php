<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fifu-cron-job-service.php';

/**
 * Routes generic FIFU cron jobs identified by specific IDs.
 */
final class Fifu_Generic_Cron_Service
{
    private const REGISTRATION_EMPTY = 'empty';
    private const REGISTRATION_SUCCESS = 'success';
    private const REGISTRATION_FAILED = 'failed';

    /**
     * Runs a generic FIFU cron job identified by an ID.
     *
     * Supported IDs include:
     * - metain
     * - metaout
     *
 * This method will:
 * - Read supported metadata queues.
 * - Dispatch local loopback metadata workers.
 * - Manage semaphores and logging.
     *
     * @param string $id
     * @return void
     */
    public static function run(string $id): void
    {
        $id = trim($id);
        if (!self::is_supported_id($id)) {
            return;
        }

        if (Fifu_Cron_Job_Service::is_active("fifu_{$id}_semaphore", 5)) {
            return;
        }

        Fifu_Transient_Manager::set("fifu_{$id}_semaphore", new DateTime(), 0);

        try {
            self::run_single_registration($id);
        } finally {
            Fifu_Transient_Manager::delete("fifu_{$id}_semaphore");
        }
    }


    private static function run_single_registration(string $id): string
    {
        $queueRepository = new Fifu_Metadata_Queue_Repository();
        $result = [];

        switch ($id) {
            case 'metain':
                $result = $queueRepository->get_meta_in();

                if ($result === []) {
                    Fifu_Metadata_Import_Service::prepare_meta_in_queue('', false);
                    $result = $queueRepository->get_meta_in();
                }
                break;

            case 'metaout':
                $result = $queueRepository->get_meta_out();

                if ($result === []) {
                    Fifu_Metadata_Import_Service::prepare_meta_out_queue('', false);
                    $result = $queueRepository->get_meta_out();
                }
                break;

            default:
                return self::REGISTRATION_EMPTY;
        }

        $firstEntry = $result[0] ?? null;
        $queueRowId = is_object($firstEntry)
            ? (int) ($firstEntry->post_id ?? 0)
            : 0;

        if ($queueRowId <= 0) {
            return self::REGISTRATION_EMPTY;
        }

        $token = self::generate_local_worker_token();
        $tokenKey = "fifu_api_{$id}_auth_token";
        $expiration = defined('MINUTE_IN_SECONDS')
            ? MINUTE_IN_SECONDS
            : 60;

        if (
            $token === ''
            || !Fifu_Transient_Manager::set(
                $tokenKey,
                $token,
                $expiration
            )
        ) {
            Fifu_File_Logger::plugin([
                'fifu-create-generic-hook' => [
                    'SERVICE' => $id,
                    'STATUS' => 'token_error',
                ],
            ]);

            return self::REGISTRATION_FAILED;
        }

        $url = get_rest_url() . FIFU_REST_NAMESPACE_V2 . "/{$id}/";
        $payload = [
            'post_id' => $queueRowId,
        ];

        $response = wp_remote_post(
            $url,
            [
                'method' => 'POST',
                'body' => wp_json_encode($payload),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-FIFU-Authorization' => $token,
                ],
                'blocking' => true,
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();
            Fifu_File_Logger::plugin([
                'fifu-create-generic-hook' => [
                    'SERVICE' => $id,
                    'STATUS' => $errorMessage,
                ],
            ]);
            return self::REGISTRATION_FAILED;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode !== 200) {
            Fifu_File_Logger::plugin([
                'fifu-create-generic-hook' => [
                    'SERVICE' => $id,
                    'STATUS' => $responseCode,
                ],
            ]);

            return self::REGISTRATION_FAILED;
        }

        Fifu_File_Logger::plugin([
            'fifu-create-generic-hook' => [
                'SERVICE' => $id,
                'STATUS' => $responseCode,
                'IDS' => 1,
            ],
        ]);

        return self::REGISTRATION_SUCCESS;
    }

    private static function generate_local_worker_token(): string
    {
        if (function_exists('wp_generate_password')) {
            $token = wp_generate_password(8, false);

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        try {
            return bin2hex(random_bytes(4));
        } catch (Throwable $throwable) {
            return substr(
                hash('sha256', uniqid('fifu-local-worker-', true)),
                0,
                8
            );
        }
    }

    private static function is_supported_id(string $id): bool
    {
        return in_array($id, [
            'metain',
            'metaout',
        ], true);
    }


}
