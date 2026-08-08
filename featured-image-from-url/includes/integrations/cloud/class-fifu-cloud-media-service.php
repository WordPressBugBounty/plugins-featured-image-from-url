<?php
declare(strict_types=1);

final class Fifu_Cloud_Media_Service {

    /**
     * @see fifu_create_thumbnails_list()
     */
    public static function create_thumbnails_list(array $images, bool $cron = false) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        if ($cron) {
            $code = get_option('fifu_cloud_upload_auto_code');
            if (!$code) {
                return (object) Fifu_Cloud_Config::get_no_credentials_payload();
            }
        }

        $sentUrls = [];
        $savedUrls = [];
        $rows = [];
        $urlSign = '';
        $invalidMediaRepository = function_exists('fifu_db2_invalid_media_repository') ? fifu_db2_invalid_media_repository() : null;

        foreach ($images as $image) {
            if (!$cron) {
                $postId = $image[0] ?? null;
                $url = $image[1] ?? null;
                $metaKey = $image[2] ?? null;
                $metaId = $image[3] ?? null;

                if (!array_key_exists(4, $image)) {
                    continue;
                }

                $categoryValue = $image[4];
                if (!in_array($categoryValue, [0, 1, '0', '1', false, true], true)) {
                    continue;
                }

                $isCategory = (int) $categoryValue === 1;
            } else {
                $postId = $image->post_id ?? null;
                $url = $image->url ?? null;
                $metaKey = $image->meta_key ?? null;
                $metaId = $image->meta_id ?? null;
                $isCategory = ($image->category ?? 0) == 1;
            }

            $postId = (int) $postId;
            $url = is_string($url) ? trim($url) : $url;
            $metaKey = is_string($metaKey) ? trim($metaKey) : $metaKey;

            if ($postId <= 0 || !is_string($url) || $url === '') {
                continue;
            }

            if ($metaKey !== 'fifu_image_url') {
                continue;
            }

            if ($cron) {
                if ($invalidMediaRepository && $invalidMediaRepository->get_attempts((string) $url) >= 5) {
                    continue;
                }

                $sentUrls[] = $url;
            }

            $encodedUrl = base64_encode($url);
            $rows[] = [$postId, $encodedUrl, 'fifu_image_url', $metaId, $isCategory, ''];
            $urlSign .= substr($encodedUrl, -10);

            Fifu_File_Logger::cloud([
                'create_thumbnails_list' => [
                    'post_id' => $postId,
                    'meta_key' => $metaKey,
                    'meta_id' => $metaId,
                    'is_category' => $isCategory,
                    'url' => $url,
                ],
            ]);
        }

        if (empty($rows)) {
            return (object) ['code' => 0, 'message' => 'No valid rows'];
        }

        self::preflight_migrate_posts_legacy_featured_image_to_db2_before_cloud_upload($rows);

        $time = time();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $signature = Fifu_License_Crypto::create_signature($urlSign . $site . $time . $ip);
        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'rows' => $rows,
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'upload_auto' => $cron,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 300,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/create-thumbnails/', $payload);
        if (is_wp_error($response)) {
            return;
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        $code = $json->code ?? 0;

        if ($code && $code > 0) {
            $categoryImages = [];
            $postImages = [];
            foreach ((array) ($json->thumbnails ?? []) as $thumbnail) {
                if ($thumbnail->is_category ?? false) {
                    $categoryImages[] = $thumbnail;
                } else {
                    $postImages[] = $thumbnail;
                }

                $savedUrls[] = $thumbnail->meta_value ?? '';
            }

            if (count($categoryImages) > 0 || count($postImages) > 0) {
                $repository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
                if ($repository) {
                    $service = new Fifu_Db2_Speed_Up_Service($repository);
                    $bucketId = isset($json->bucket_id) ? (string) $json->bucket_id : '';
                    if (count($categoryImages) > 0) {
                        $service->add_category_urls($bucketId, $categoryImages);
                    }

                    if (count($postImages) > 0) {
                        $service->add_urls($bucketId, $postImages);
                    }
                }
            }
        }

        if ($cron && $invalidMediaRepository) {
            foreach ($sentUrls as $sentUrl) {
                if (in_array($sentUrl, $savedUrls, true)) {
                    $invalidMediaRepository->delete_by_url($sentUrl);
                } else {
                    $invalidMediaRepository->increment_attempts($sentUrl);
                }
            }
        }

        return $json;
    }

    /**
     * Migrates only fifu_image_url and fifu_image_alt before a Free Cloud upload.
     *
     * @param array<int,array<int,mixed>> $rows
     * @return void
     */
    private static function preflight_migrate_posts_legacy_featured_image_to_db2_before_cloud_upload(array $rows): void
    {
        $postIds = [];

        foreach ($rows as $row) {
            $postId = (int) ($row[0] ?? 0);
            $isCategory = (bool) ($row[4] ?? false);

            if ($postId <= 0 || $isCategory) {
                continue;
            }

            $postIds[$postId] = true;
        }

        if ($postIds === []) {
            return;
        }

        if (
            !function_exists('fifu_db2_speed_up_repository')
            || !class_exists('Fifu_Db2_Speed_Up_Service')
        ) {
            return;
        }

        $repository = fifu_db2_speed_up_repository();
        if (!$repository instanceof Fifu_Db2_Speed_Up_Repository) {
            return;
        }

        $service = new Fifu_Db2_Speed_Up_Service($repository);
        $service->migrate_posts_legacy_featured_image_state_before_cloud_operation(array_keys($postIds));
    }

    /**
     * Migrates only fifu_image_url and fifu_image_alt before a Free Cloud deletion.
     *
     * @param string[] $storageIds
     * @return void
     */
    private static function preflight_migrate_posts_legacy_featured_image_to_db2_before_cloud_delete(array $storageIds): void
    {
        if ($storageIds === []) {
            return;
        }

        if (
            !function_exists('fifu_db2_speed_up_repository')
            || !class_exists('Fifu_Db2_Speed_Up_Service')
        ) {
            return;
        }

        $repository = fifu_db2_speed_up_repository();
        if (!$repository instanceof Fifu_Db2_Speed_Up_Repository) {
            return;
        }

        if (!method_exists($repository, 'get_post_ids_su_for_cloud_preflight')) {
            return;
        }

        $postIds = $repository->get_post_ids_su_for_cloud_preflight($storageIds);
        if ($postIds === []) {
            return;
        }

        $service = new Fifu_Db2_Speed_Up_Service($repository);
        $service->migrate_posts_legacy_featured_image_state_before_cloud_operation($postIds);
    }

    /**
     * @see fifu_delete_thumbnails()
     */
    private static function preflight_migrate_terms_legacy_media_to_db2_before_cloud_delete(array $storageIds): void
    {
        if (!function_exists('fifu_db2_speed_up_repository')) {
            return;
        }

        $repository = fifu_db2_speed_up_repository();
        if (
            !$repository
            instanceof Fifu_Db2_Speed_Up_Repository
        ) {
            return;
        }
        if (!method_exists($repository, 'get_term_ids_su_for_cloud_preflight')) {
            return;
        }

        $termIds = $repository->get_term_ids_su_for_cloud_preflight($storageIds);
        if ($termIds === [] || !class_exists('Fifu_Db2_Speed_Up_Service')) {
            return;
        }

        $service = new Fifu_Db2_Speed_Up_Service($repository);
        $service->migrate_terms_legacy_media_state_before_cloud_delete($termIds);
    }

    public static function delete_thumbnails(array $hexIds) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $code = get_option('fifu_cloud_delete_auto_code');
        if (!$code) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $rows = [];
        $hexIdSign = '';
        foreach ($hexIds as $hexId) {
            $rows[] = $hexId;
            $hexIdSign .= $hexId;

            Fifu_File_Logger::cloud(['delete_auto (send used)' => ['hex_id' => $hexId]]);
        }

        $time = time();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $signature = Fifu_License_Crypto::create_signature($hexIdSign . $site . $time . $ip);
        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'rows' => $rows,
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 300,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/delete-thumbnails/', $payload);
        if (is_wp_error($response)) {
            return;
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        Fifu_File_Logger::cloud(['delete_auto (response)' => ['json' => $json]]);
        $code = $json->code ?? 0;

        if ($code && $code > 0) {
            if (count((array) ($json->hex_ids ?? [])) > 0) {
                if (isset($json->hex_ids) && is_array($json->hex_ids)) {
                    $hexIds = (array) $json->hex_ids;

                    if (count($hexIds) > 0) {
                        $results = Fifu_Cloud_Usage_Verification_Service::find_used_hex_ids($hexIds);

                        foreach ($results as $metaValue) {
                            foreach ($hexIds as $key => $hexId) {
                                if (strpos($metaValue, $hexId) !== false) {
                                    unset($hexIds[$key]);
                                    Fifu_File_Logger::cloud(['found' => $hexId]);
                                }
                            }
                        }
                    }
                }

                foreach ($hexIds as $hexId) {
                    Fifu_File_Logger::cloud(['delete' => $hexId]);
                }

                $batches = array_chunk($hexIds, 1000);
                foreach ($batches as $batch) {
                    $batchRows = [];
                    $idSign = '';
                    foreach ($batch as $hexId) {
                        $batchRows[] = $hexId;
                        $idSign .= $hexId;

                        Fifu_File_Logger::cloud(['delete_auto (send unused back)' => ['hex_id' => $hexId]]);
                    }

                    $time = time();
                    $signature = Fifu_License_Crypto::create_signature($idSign . $site . $time . $ip);
                    $batchPayload = [
                        'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                        'body' => json_encode([
                            'rows' => $batchRows,
                            'site' => $site,
                            'signature' => $signature,
                            'time' => $time,
                            'ip' => $ip,
                            'slug' => Fifu_Cloud_Config::get_client_slug(),
                            'version' => Fifu_Plugin_Info::get_version(),
                        ]),
                        'method' => 'POST',
                        'data_format' => 'body',
                        'blocking' => true,
                        'timeout' => 300,
                    ];
                    $batchResponse = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/delete-thumbnails-confirm/', $batchPayload);
                    if (is_wp_error($batchResponse)) {
                        return;
                    }

                    sleep(5);
                }
            }
        }

        return $json;
    }

    /**
     * @see fifu_api_confirm_delete()
     */
    private static function confirm_delete(array $rows, string $site, string $ip, string $urlSign) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $time = time();
        $signature = Fifu_License_Crypto::create_signature($urlSign . $site . $time . $ip);

        Fifu_File_Logger::cloud(['confirm_delete' => ['rows' => $rows]]);

        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'rows' => $rows,
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 300,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/confirm-delete/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        return $json;
    }

    /**
     * @see fifu_api_delete()
     */
    public static function delete_selected(array $storageIds) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $rows = [];
        $urlSign = '';
        foreach ($storageIds as $storageId) {
            $storageId = is_scalar($storageId) ? trim((string) $storageId) : '';
            if ($storageId === '') {
                continue;
            }

            $rows[] = $storageId;
            $urlSign .= $storageId;
        }

        if (count($rows) === 0) {
            return (object) ['code' => 1];
        }

        self::preflight_migrate_posts_legacy_featured_image_to_db2_before_cloud_delete($rows);
        self::preflight_migrate_terms_legacy_media_to_db2_before_cloud_delete($rows);

        $time = time();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $signature = Fifu_License_Crypto::create_signature($urlSign . $site . $time . $ip);

        Fifu_File_Logger::cloud(['delete' => ['rows' => $rows]]);

        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'rows' => $rows,
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 60,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/delete/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        if (!$json) {
            return null;
        }

        $code = $json->code ?? 0;
        if ($code && $code > 0) {
            $urls = (array) ($json->urls ?? []);
            $videoUrls = (array) ($json->video_urls ?? []);

            if (count($urls) > 0 || count($videoUrls) > 0) {
                $map = [];
                $speedUpRepository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
                $posts = $speedUpRepository ? $speedUpRepository->get_posts_su($rows) : [];
                foreach ($posts as $post) {
                    $map[$post->storage_id] = $post;
                }

                $categoryImages = [];
                $postImages = [];
                foreach ($posts as $post) {
                    if ($post->category ?? false) {
                        $categoryImages[] = $post;
                        continue;
                    }
                    $postImages[] = $post;
                }

                if ($speedUpRepository) {
                    $speedUpService = new Fifu_Db2_Speed_Up_Service($speedUpRepository);
                    if (count($postImages) > 0) {
                        $speedUpService->remove_post_urls(
                            $postImages,
                            $urls,
                            $videoUrls
                        );
                    }
                    if (count($categoryImages) > 0) {
                        $speedUpService->remove_term_urls(
                            $categoryImages,
                            $urls,
                            $videoUrls
                        );
                    }
                }

                return self::confirm_delete($rows, $site, $ip, $urlSign);
            }
        }

        return $json;
    }

    /**
     * @see fifu_api_list_all_su()
     */
    public static function list_all(int $page, string $type = '', string $keyword = '') {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $time = time();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $signature = Fifu_License_Crypto::create_signature($site . $time . $ip);

        Fifu_File_Logger::cloud([
            'list_all_su' => [
                'site' => $site,
                'page' => $page,
                'type' => $type,
                'keyword' => $keyword,
            ],
        ]);

        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'page' => $page,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
                'type' => $type,
                'keyword' => $keyword,
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/list-all/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        if (($response['http_response']->get_response_object()->status_code ?? 0) == 404) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $map = [];
        $speedUpRepository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
        $posts = $speedUpRepository ? $speedUpRepository->get_posts_su([]) : [];
        foreach ($posts as $post) {
            $map[$post->storage_id] = $post;
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        if ($json && ($json->code ?? 0) > 0) {
            for ($i = 0; $i < count($json->photo_data ?? []); $i++) {
                $post = $json->photo_data[$i];
                if (isset($map[$post->storage_id])) {
                    $post->title = $map[$post->storage_id]->post_title;
                    $post->meta_id = $map[$post->storage_id]->meta_id;
                    $post->post_id = $map[$post->storage_id]->post_id;
                    $post->meta_key = $map[$post->storage_id]->meta_key;
                } else {
                    $post->title = $post->meta_id = $post->post_id = $post->meta_key = '';
                }

                $url = 'https://cdn.fifu.app/' . ($json->bucket_id ?? '') . '/' . ($post->storage_id ?? '');
                $post->proxy_url = Fifu_Speedup_Url_Service::get_signed_url(
                    $url,
                    128,
                    128,
                    $json->bucket_id ?? '',
                    $post->storage_id ?? '',
                    false
                );

                $post->storage_id = sanitize_text_field($post->storage_id ?? '');
                $post->title = sanitize_text_field($post->title ?? '');
                $post->date = sanitize_text_field($post->date ?? '');
                $post->meta_key = sanitize_text_field($post->meta_key ?? '');
                $post->proxy_url = esc_url_raw($post->proxy_url ?? '');
                $post->meta_id = intval($post->meta_id ?? 0);
                $post->post_id = intval($post->post_id ?? 0);
                $post->is_category = isset($post->is_category) ? (bool) $post->is_category : false;
            }
        }

        return $json;
    }

    /**
     * Returns the list of stored FIFU URLs from the local speed-up repository.
     *
     * @param int         $page
     * @param string|null $type
     * @param string|null $keyword
     * @return array
     */
    public static function list_all_fifu(int $page, ?string $type, ?string $keyword): array
    {
        $speedUpRepository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
        $urls = $speedUpRepository ? $speedUpRepository->get_all_urls($page, $type, $keyword) : [];

        if (is_array($urls)) {
            foreach ($urls as $item) {
                if (!is_object($item)) {
                    continue;
                }

                $item->url = esc_url_raw($item->url ?? '');
                $item->post_title = sanitize_text_field($item->post_title ?? '');
                $item->post_name = sanitize_text_field($item->post_name ?? '');
                $item->post_date = sanitize_text_field($item->post_date ?? '');
                $item->meta_key = sanitize_text_field($item->meta_key ?? '');
                $item->meta_id = intval($item->meta_id ?? 0);
                $item->post_id = intval($item->post_id ?? 0);
                $item->category = isset($item->category) ? (int) (!!$item->category) : 0;
            }
        }

        return $urls;
    }

    /**
     * @see fifu_api_list_daily_count()
     */
    public static function list_daily_count() {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $time = time();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $signature = Fifu_License_Crypto::create_signature($site . $time . $ip);

        Fifu_File_Logger::cloud(['list_daily_count' => ['site' => $site]]);

        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];

        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/list-daily-count/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        if (($response['http_response']->get_response_object()->status_code ?? 0) == 404) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        return $json;
    }

    /**
     * Runs the automatic cloud upload job, creating thumbnails for a batch of URLs.
     *
     * @return void
     */
    public static function run_upload_auto_job(): void
    {
        $semaphore = 'fifu_cloud_upload_auto_semaphore';

        if (Fifu_Cron_Job_Service::is_active($semaphore, 5)) {
            return;
        }

        Fifu_Transient_Manager::set($semaphore, new DateTime(), 0);

        try {
            $speedUpRepository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
            $urls = $speedUpRepository ? $speedUpRepository->get_all_urls(0, null, null) : [];
            $urls = array_slice($urls, 0, 100);

            foreach ($urls as $index => $url) {
                if (!isset($url->post_id) || (int) $url->post_id <= 0) {
                    $url->post_id = $index + 1;
                }
            }

            self::create_thumbnails_list($urls, true);
        } finally {
            Fifu_Transient_Manager::delete($semaphore);
        }
    }

    /**
     * Runs the automatic cloud delete job, removing thumbnails based on hex IDs.
     *
     * @return void
     */
    public static function run_delete_auto_job(): void
    {
        $semaphore = 'fifu_cloud_delete_auto_semaphore';

        if (Fifu_Cron_Job_Service::is_active($semaphore, 5)) {
            return;
        }

        Fifu_Transient_Manager::set($semaphore, new DateTime(), 0);

        try {
            $speedUpRepository = function_exists('fifu_db2_speed_up_repository') ? fifu_db2_speed_up_repository() : null;
            $hexIds = $speedUpRepository ? $speedUpRepository->get_all_hex_ids() : [];

            self::delete_thumbnails($hexIds);
        } finally {
            Fifu_Transient_Manager::delete($semaphore);
        }
    }
}
