<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

final class Fifu_Metadata_Db2_Cleanup_Guard
{
    private static function normalize_scalar_value($value): ?string
    {
        if ($value === null || $value === false || !is_scalar($value)) return null;
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^(null|undefined)$/i', $value) === 1) return null;
        $value = trim(function_exists('wp_strip_all_tags') ? (string) wp_strip_all_tags($value) : strip_tags($value));
        if ($value === '' || preg_match('/^(null|undefined)$/i', $value) === 1) return null;
        return $value;
    }

    public static function values_match($legacyValue, $db2Value): bool
    {
        $legacy = self::normalize_scalar_value($legacyValue);
        $db2 = self::normalize_scalar_value($db2Value);
        return $legacy !== null && $db2 !== null && $legacy === $db2;
    }

    public static function can_cleanup_scalar($legacyValue, $db2Value): bool
    {
        return self::values_match($legacyValue, $db2Value);
    }

    public static function can_cleanup_url_and_alt($legacyUrl, $legacyAlt, $db2Url, $db2Alt): bool
    {
        if (!self::values_match($legacyUrl, $db2Url)) return false;
        $legacyAltValue = self::normalize_scalar_value($legacyAlt);
        return $legacyAltValue === null || self::values_match($legacyAltValue, $db2Alt);
    }
}
