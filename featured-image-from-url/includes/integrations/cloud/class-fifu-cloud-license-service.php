<?php
declare(strict_types=1);

final class Fifu_Cloud_License_Service {

    /**
     * Handle the signup flow against the FIFU Cloud service.
     */
    public static function sign_up(string $email) {
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();

        Fifu_File_Logger::cloud(['sign_up' => ['site' => $site]]);

        $payload = array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'public_key' => Fifu_License_Crypto::create_keys($email),
                    'slug' => Fifu_Cloud_Config::get_client_slug(),
                    'version' => Fifu_Plugin_Info::get_version(),
                )
            ),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 120,
        );
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/sign-up/', $payload);
        if (is_wp_error($response) || ($response['response']['code'] ?? 0) == 404) {
            self::delete_credentials();
            return Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        if (($json->code ?? 0) <= 0) {
            self::delete_credentials();
            return $json;
        }

        return $json;
    }

    /**
     * Cancel the current subscription in FIFU Cloud.
     */
    public static function cancel() {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $time . $ip);

        Fifu_File_Logger::cloud(['cancel' => ['site' => $site]]);

        $payload = array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => Fifu_Cloud_Config::get_client_slug(),
                    'version' => Fifu_Plugin_Info::get_version(),
                )
            ),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        );
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/cancel/', $payload);
        if (is_wp_error($response)) {
            return Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');

        return $json;
    }

    /**
     * Retrieve payment info from FIFU Cloud.
     */
    public static function payment_info() {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $time . $ip);

        Fifu_File_Logger::cloud(['payment_info' => ['site' => $site]]);

        $payload = array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => Fifu_Cloud_Config::get_client_slug(),
                    'version' => Fifu_Plugin_Info::get_version(),
                )
            ),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        );
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/payment-info/', $payload);
        if (is_wp_error($response)) {
            return Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');

        return $json;
    }

    /**
     * Check if the site is connected to FIFU Cloud and retrieve connection info.
     */
    public static function connected() {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $email = Fifu_Admin_Menu::get_su_email();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $email . $time . $ip);

        Fifu_File_Logger::cloud(['connected' => ['site' => $site]]);

        $payload = array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'proxy_auth' => get_option('fifu_proxy_auth') ? true : false,
                    'slug' => Fifu_Cloud_Config::get_client_slug(),
                    'version' => Fifu_Plugin_Info::get_version(),
                )
            ),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        );
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/connected/', $payload);
        if (is_wp_error($response)) {
            return Fifu_Cloud_Config::get_try_again_later_payload();
        }

        if (($response['http_response']->get_response_object()->status_code ?? 0) == 404) {
            return Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');

        if (isset($json->proxy_key)) {
            $privKey = openssl_decrypt(base64_decode((get_option('fifu_su_privkey')[0] ?? '')), "AES-128-ECB", $email . $site);
            if ($privKey) {
                openssl_private_decrypt(base64_decode($json->proxy_key ?? ''), $key, $privKey);
                openssl_private_decrypt(base64_decode($json->proxy_salt ?? ''), $salt, $privKey);
                update_option('fifu_proxy_auth', array($key, $salt));
            }
        }

        return $json;
    }

    /**
     * Delete locally stored credentials related to FIFU Cloud.
     */
    protected static function delete_credentials(): void {
        delete_option('fifu_su_privkey');
        delete_option('fifu_su_email');
        delete_option('fifu_proxy_auth');
    }

    /**
     * Clear stored credentials from outside the service.
     */
    public static function clear_credentials(): void {
        self::delete_credentials();
    }

    /**
     * @param string $email
     *
     * @see fifu_api_reset_credentials()
     */
    public static function reset_credentials(string $email) {
        self::clear_credentials();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();

        Fifu_File_Logger::cloud(['reset_credentials' => ['site' => $site]]);

        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'email' => $email,
                'public_key' => Fifu_License_Crypto::create_keys($email),
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/reset-credentials/', $payload);
        if (is_wp_error($response)) {
            self::clear_credentials();
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        if (($json->code ?? 0) == -21) {
            self::clear_credentials();
        }

        return $json;
    }

    /**
     * @param bool $enabled
     *
     * @see fifu_api_cloud_upload_auto()
     */
    public static function toggle_upload_auto(bool $enabled) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $email = Fifu_Admin_Menu::get_su_email();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $email . $time . $ip);
        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'email' => $email,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'enabled' => $enabled,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];

        Fifu_File_Logger::cloud(['cloud_upload_auto' => ['site' => $site]]);
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/upload-auto/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        $uploadAutoCode = $json->upload_auto_code ?? null;
        $success = (($json->code ?? 0) > 0);

        if ($enabled) {
            if ($success && is_string($uploadAutoCode) && trim($uploadAutoCode) !== '') {
                update_option('fifu_cloud_upload_auto_code', [$uploadAutoCode]);
            }
        } else {
            delete_option('fifu_cloud_upload_auto_code');
        }

        return $json;
    }

    /**
     * @param bool $enabled
     *
     * @see fifu_api_cloud_delete_auto()
     */
    public static function toggle_delete_auto(bool $enabled) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $email = Fifu_Admin_Menu::get_su_email();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $email . $time . $ip);
        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'email' => $email,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'enabled' => $enabled,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];

        Fifu_File_Logger::cloud(['cloud_delete_auto' => ['site' => $site]]);
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/delete-auto/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $json = json_decode($response['http_response']->get_response_object()->body ?? '');
        $deleteAutoCode = $json->delete_auto_code ?? null;
        $success = (($json->code ?? 0) > 0);

        if ($enabled) {
            if ($success && is_string($deleteAutoCode) && trim($deleteAutoCode) !== '') {
                update_option('fifu_cloud_delete_auto_code', [$deleteAutoCode]);
            }
        } else {
            delete_option('fifu_cloud_delete_auto_code');
        }

        return $json;
    }

    /**
     * @param bool $enabled
     *
     * @see fifu_api_cloud_hotlink()
     */
    public static function toggle_hotlink(bool $enabled) {
        if (!Fifu_Admin_Menu::is_su_sign_up_complete()) {
            return (object) Fifu_Cloud_Config::get_no_credentials_payload();
        }

        $email = Fifu_Admin_Menu::get_su_email();
        $site = Fifu_Image_Url_Utils::get_home_url_no_scheme();
        $ip = Fifu_Cloud_Http_Client::get_public_ip();
        $time = time();
        $signature = Fifu_License_Crypto::create_signature($site . $email . $time . $ip);
        $payload = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode([
                'site' => $site,
                'email' => $email,
                'signature' => $signature,
                'time' => $time,
                'ip' => $ip,
                'enabled' => $enabled,
                'slug' => Fifu_Cloud_Config::get_client_slug(),
                'version' => Fifu_Plugin_Info::get_version(),
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'timeout' => 30,
        ];

        Fifu_File_Logger::cloud(['cloud_hotlink' => ['site' => $site]]);
        $response = Fifu_Cloud_Http_Client::post(Fifu_Cloud_Config::get_su_address() . '/hotlink/', $payload);
        if (is_wp_error($response)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        if (
            !isset($response['http_response'])
            || !is_object($response['http_response'])
            || !method_exists($response['http_response'], 'get_response_object')
        ) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        $responseObject = $response['http_response']->get_response_object();
        $body = $responseObject->body ?? '';
        $json = json_decode($body);

        if (!is_object($json)) {
            return (object) Fifu_Cloud_Config::get_try_again_later_payload();
        }

        return $json;
    }
}
