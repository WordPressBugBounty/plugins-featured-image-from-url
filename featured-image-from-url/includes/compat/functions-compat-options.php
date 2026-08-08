<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-fifu-options-cleanup.php';

function fifu_db_delete_deprecated_data(): void {
    global $wpdb;
    Fifu_Options_Cleanup::delete_deprecated_options( $wpdb );
}

if ( ! function_exists( 'fifu_free_pro_only_locked_options' ) ) {
    /**
     * PRO-only options that must stay inert in the free plugin.
     *
     * The admin UI may keep selected sections visible as upgrade hints, but the
     * runtime feature must remain unavailable and impossible to enable here.
     */
    function fifu_free_pro_only_locked_options() {
        return array(
            'fifu_buy'                     => 'toggleoff',
            'fifu_order_email'             => 'toggleoff',
        );
    }
}

if ( ! function_exists( 'fifu_free_quick_buy_locked_options' ) ) {
    /**
     * Backward-compatible helper for tests/call sites that still use the old name.
     */
    function fifu_free_quick_buy_locked_options() {
        return array_intersect_key(
            fifu_free_pro_only_locked_options(),
            array(
                'fifu_buy'         => true,
                'fifu_order_email' => true,
            )
        );
    }
}

if ( ! function_exists( 'fifu_free_lock_pro_only_option_reads' ) ) {
    function fifu_free_lock_pro_only_option_reads() {
        foreach ( fifu_free_pro_only_locked_options() as $option => $locked_value ) {
            add_filter(
                "option_{$option}",
                static function () use ( $locked_value ) {
                    return $locked_value;
                }
            );

            add_filter(
                "default_option_{$option}",
                static function () use ( $locked_value ) {
                    return $locked_value;
                }
            );

            add_filter(
                "pre_update_option_{$option}",
                static function () use ( $locked_value ) {
                    return $locked_value;
                }
            );
        }
    }
}

if ( ! function_exists( 'fifu_free_lock_quick_buy_option_reads' ) ) {
    /**
     * Backward-compatible wrapper for the old helper name.
     */
    function fifu_free_lock_quick_buy_option_reads() {
        fifu_free_lock_pro_only_option_reads();
    }
}

fifu_free_lock_pro_only_option_reads();

if ( ! function_exists( 'fifu_is_on' ) ) {
    /**
     * Compatibility wrapper for legacy call sites that expect the global helper.
     *
     * @param string $option
     * @return bool
     */
    function fifu_is_on( string $option ): bool {
        return class_exists( 'Fifu_Options_Utils', false ) && Fifu_Options_Utils::is_on( $option );
    }
}
