<?php
declare(strict_types=1);

/**
 * Class Fifu_Theme_Detector
 *
 * Detects active themes.
 */
class Fifu_Theme_Detector {
    /**
     * Checks if Flatsome theme is active.
     *
     * @return bool
     */
    private static function templateRaw(): string {
        return (string) get_option('template', '');
    }

    private static function templateSlug(): string {
        return strtolower(self::templateRaw());
    }

    /**
     * Checks if Flatsome theme is active.
     *
     * @return bool
     */
    public static function is_flatsome_active(): bool {
        return 'flatsome' === self::templateRaw();
    }

    /**
     * Checks if Divi theme is active.
     *
     * @return bool
     */
    public static function is_divi_active(): bool {
        return 'divi' === self::templateSlug();
    }

    /**
     * Checks if Avada theme is active.
     *
     * @return bool
     */
    public static function is_avada_active(): bool {
        return 'avada' === self::templateSlug();
    }

    /**
     * Checks if Newspaper theme is active.
     *
     * @return bool
     */
    public static function is_newspaper_active(): bool {
        return 'newspaper' === self::templateSlug();
    }

    /**
     * Checks if Rey theme is active.
     *
     * @return bool
     */
    public static function is_rey_active(): bool {
        return 'rey' === self::templateSlug();
    }

    /**
     * Checks if Blocksy theme is active.
     *
     * @return bool
     */
    public static function is_blocksy_active(): bool {
        return 'blocksy' === self::templateSlug();
    }

    /**
     * Checks if Houzez theme is active.
     *
     * @return bool
     */
    public static function is_houzez_active(): bool {
        return 'houzez' === self::templateSlug();
    }

    /**
     * Checks if WpResidence theme is active.
     *
     * @return bool
     */
    public static function is_wpresidence_active(): bool {
        return 'wpresidence' === self::templateSlug();
    }

    /**
     * Checks if Photolio theme is active.
     *
     * @return bool
     */
    public static function is_photolio_active(): bool {
        return 'photolio' === self::templateSlug();
    }
}
