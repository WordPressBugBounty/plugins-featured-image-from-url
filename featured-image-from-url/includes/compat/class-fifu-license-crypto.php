<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_License_Crypto
{
    public static function create_keys(string $email): string
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);

        $pubKeyDetails = openssl_pkey_get_details($res);
        $pubKey = $pubKeyDetails['key'] ?? '';

        update_option('fifu_su_email', [base64_encode($email)], 'no');

        $encrypted = openssl_encrypt(
            $privKey,
            'AES-128-ECB',
            $email . Fifu_Image_Url_Utils::get_home_url_no_scheme()
        );

        update_option('fifu_su_privkey', [base64_encode($encrypted)], 'no');

        return base64_encode($pubKey);
    }

    public static function create_signature(string $data): string
    {
        $emailOption = get_option('fifu_su_email');
        $privateKeyOption = get_option('fifu_su_privkey');

        $email = base64_decode(
            is_array($emailOption) ? (string) ($emailOption[0] ?? '') : ''
        );

        $encryptedPrivateKey = base64_decode(
            is_array($privateKeyOption) ? (string) ($privateKeyOption[0] ?? '') : ''
        );

        $privateKey = openssl_decrypt(
            $encryptedPrivateKey,
            'AES-128-ECB',
            $email . Fifu_Image_Url_Utils::get_home_url_no_scheme()
        );

        $signature = '';

        if (is_string($privateKey) && $privateKey !== '') {
            openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        }

        return base64_encode($signature);
    }
}
