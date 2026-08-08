<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates information about FIFU and other installed plugins.
 *
 * @package Fifu_Free
 */
class Fifu_Plugin_Info {

    /**
     * Get the human-readable version string for the plugin (e.g. "Name:Version").
     *
     * @return string Fully qualified plugin name and version.
     */
    public static function get_version_string(): string {
        $plugin_data = self::get_plugin_headers();
        $name = $plugin_data['Name'] ?? '';
        $version = $plugin_data['Version'] ?? '';

        return $name !== '' && $version !== '' ? $name . ':' . $version : '';
    }

    /**
     * Get the normalized plugin version number.
     *
     * @return string Plugin version number.
     */
    public static function get_version(): string {
        $plugin_data = self::get_plugin_headers();
        return $plugin_data['Version'] ?? '';
    }

    /**
     * Read the plugin headers without requiring the wp-admin plugin API.
     *
     * `get_file_data()` is part of the general WordPress bootstrap and is
     * available during frontend, REST, cron, AJAX, and admin requests.
     *
     * @return array{Name: string, Version: string}
     */
    private static function get_plugin_headers(): array {
        $data = get_file_data(
            FIFU_PLUGIN_DIR . 'featured-image-from-url.php',
            [
                'Name' => 'Plugin Name',
                'Version' => 'Version',
            ],
            'plugin'
        );

        return [
            'Name' => isset($data['Name']) ? (string) $data['Name'] : '',
            'Version' => isset($data['Version']) ? (string) $data['Version'] : '',
        ];
    }

    /**
     * Provide the version string used for enqueueing assets, respecting debug mode.
     *
     * @return string Asset enqueue version string.
     */
    public static function get_enqueue_version(): string {
        if ( Fifu_Options_Utils::is_on( 'fifu_debug' ) ) {
            return (string) mt_rand();
        }

        return self::get_version();
    }

    /**
     * Return a serialized list of available plugins for debugging and reporting.
     *
     * @return string Serialized list of available plugins.
     */
    public static function get_plugins_list(): string {
        $list = '';

        foreach ( get_plugins() as $domain ) {
            $name = $domain['Name'] . ' (' . $domain['TextDomain'] . ')';
            $list .= "\n - " . $name;
        }

        return $list;
    }

    /**
     * Return a serialized list of active plugins for diagnostics.
     *
     * @return string Serialized list of active plugins.
     */
    public static function get_active_plugins_list(): string {
        $list = '';
        $active_plugins = get_option( 'active_plugins', array() );
        $all_plugins = get_plugins();

        foreach ( $active_plugins as $basename ) {
            if ( isset( $all_plugins[ $basename ] ) ) {
                $data = $all_plugins[ $basename ];
                $name = $data['Name'] ?? $basename;
                $text_domain = $data['TextDomain'] ?? '';
                $author = isset( $data['Author'] ) ? wp_strip_all_tags( $data['Author'] ) : '';

                $display = $name;
                if ( $text_domain !== '' ) {
                    $display .= ' (' . $text_domain . ')';
                }
                if ( $author !== '' ) {
                    $display .= ': ' . $author;
                }
            } else {
                $parts = explode( '/', $basename );
                $display = $parts[0] ?? $basename;
            }

            $list .= "\n - " . $display;
        }

        return $list;
    }
}
