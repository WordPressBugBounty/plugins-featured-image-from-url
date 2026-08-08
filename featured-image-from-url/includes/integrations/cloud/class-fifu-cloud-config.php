<?php
declare(strict_types=1);

final class Fifu_Cloud_Config {

    /**
     * Return the base address of the FIFU Cloud signup/cancel/payment service.
     */
    public static function get_su_address(): string {
        return FIFU_CLOUD_DEBUG ? 'http://192.168.0.31:8080' : 'https://ws.fifu.app';
    }

    /**
     * Return the client slug used to identify this plugin to the cloud service.
     */
    public static function get_client_slug(): string {
        return defined('FIFU_CLIENT')
            ? (string) FIFU_CLIENT
            : 'featured-image-from-url';
    }

    /**
     * Return a standard "no credentials" payload as an array.
     */
    public static function get_no_credentials_payload(): array {
        return ['code' => 'no_credentials'];
    }

    /**
     * Return a standard "try again later" payload as an array.
     */
    public static function get_try_again_later_payload(): array {
        $strings = Fifu_Api_Strings::get_strings();
        return [
            'code' => 0,
            'message' => $strings['info']['try'](),
            'color' => 'orange',
        ];
    }
}
