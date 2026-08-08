<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads bundled local translation files for FIFU.
 */
final class Fifu_Language_Loader
{
    /**
     * Loads the plugin textdomain from the bundled local .mo file.
     */
    public static function load_textdomain(): void
    {
        $locale = self::get_locale();

        if (self::is_default_english_locale($locale)) {
            return;
        }

        $mo_file_path = self::get_local_mo_file_path($locale);

        if (file_exists($mo_file_path)) {
            load_textdomain(FIFU_SLUG, $mo_file_path);
            return;
        }

        if (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING) {
            error_log("FIFU: Local translation file for {$locale} not found. Defaulting to English.");
        }
    }

    /**
     * Resolves the WordPress locale for the current request.
     */
    private static function get_locale(): string
    {
        return function_exists('determine_locale')
            ? determine_locale()
            : (is_admin() ? get_user_locale() : get_locale());
    }

    /**
     * en_US is the default source language, so no .mo file is required.
     */
    private static function is_default_english_locale(string $locale): bool
    {
        return $locale === 'en_US' || $locale === 'en';
    }

    /**
     * Builds the local bundled .mo path using WordPress locale names.
     */
    public static function get_local_mo_file_path(string $locale): string
    {
        return self::get_languages_dir() . 'featured-image-from-url-' . $locale . '.mo';
    }

    /**
     * Returns the bundled languages directory.
     */
    private static function get_languages_dir(): string
    {
        $plugin_dir = defined('FIFU_PLUGIN_DIR') ? FIFU_PLUGIN_DIR : dirname(__DIR__, 2) . '/';

        return rtrim($plugin_dir, "/\\") . '/languages/';
    }

    /**
     * Backward-compatible helper retained for old callers/tests.
     * Local translations use the original WordPress locale.
     */
    public static function get_language_code(string $locale): string
    {
        return $locale;
    }
}
