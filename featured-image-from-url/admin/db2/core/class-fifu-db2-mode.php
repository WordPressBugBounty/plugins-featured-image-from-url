<?php

declare(strict_types=1);

/**
 * Handles the current DB2 mode selection for FIFU.
 */
if (!class_exists('Fifu_Db2_Mode', false)) {
    class Fifu_Db2_Mode {
    public const MODE_LEGACY = 'legacy';
    public const MODE_DB2 = 'db2';
    public const MODE_HYBRID = 'hybrid';

    private const OPTION_NAME = 'fifu_db2_mode';

    private const ALLOWED_MODES = [
        self::MODE_LEGACY,
        self::MODE_DB2,
        self::MODE_HYBRID,
    ];

    /**
     * Cache for the current mode to avoid redundant calculations.
     *
     * @var string|null
     */
    private static ?string $modeCache = null;

    /**
     * Retrieves the active DB2 mode.
     */
    public static function get(): string {
        if (self::$modeCache !== null) {
            return self::$modeCache;
        }

        $rawMode = self::fetch_raw_mode();
        $mode = self::sanitize_mode($rawMode);
        $filtered = apply_filters('fifu_db2_mode', $mode);
        if (!is_string($filtered)) {
            $filtered = '';
        }
        self::$modeCache = self::sanitize_mode($filtered);

        return self::$modeCache;
    }

    /**
     * Resets the cached mode so the next request recomputes it.
     */
    public static function reset_cache(): void
    {
        self::$modeCache = null;
    }

    /**
     * Checks if the mode is legacy-only.
     */
    public static function is_legacy(): bool {
        return self::get() === self::MODE_LEGACY;
    }

    /**
     * Checks if the mode is DB2-only.
     */
    public static function is_db2(): bool {
        return self::get() === self::MODE_DB2;
    }

    /**
     * Checks if the mode is hybrid.
     */
    public static function is_hybrid(): bool {
        return self::get() === self::MODE_HYBRID;
    }

    /**
     * Reads the raw mode value from constants or options.
     */
    private static function fetch_raw_mode(): string {
        if (defined('FIFU_DB2_MODE')) {
            return (string) constant('FIFU_DB2_MODE');
        }

        $option = get_option(self::OPTION_NAME, self::MODE_HYBRID);
        if ($option === false) {
            return self::MODE_HYBRID;
        }

        return (string) $option;
    }

    /**
     * Normalizes and validates a mode value.
     */
    private static function sanitize_mode(string $value): string {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || !in_array($normalized, self::ALLOWED_MODES, true)) {
            return self::MODE_HYBRID;
        }

        return $normalized;
    }
}

}

if (! function_exists('fifu_db2_mode')) {
    /**
     * Global helper to read the current DB2 mode.
     */
    function fifu_db2_mode(): string {
        return Fifu_Db2_Mode::get();
    }
}
