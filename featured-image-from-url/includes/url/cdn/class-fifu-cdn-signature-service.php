<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Shared signature helper for the public FIFU CDN URL format.
 *
 * This class only signs the public FIFU CDN URL format.
 */
final class Fifu_Cdn_Signature_Service
{
    public static function get_signature(string $url, string $token): string
    {
        $hash = hash_hmac('sha256', $url, $token, true);
        return substr(bin2hex($hash), 0, 12);
    }
}
