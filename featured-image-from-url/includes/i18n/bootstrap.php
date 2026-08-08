<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap loader for the FIFU i18n helpers.
 *
 * @package Fifu_Free
 */
$files = array(
    'class-fifu-admin-strings.php',
    'class-fifu-api-strings.php',
    'class-fifu-block-strings.php',
    'class-fifu-cloud-strings.php',
    'class-fifu-dokan-strings.php',
    'class-fifu-elementor-strings.php',
    'class-fifu-gravity-forms-strings.php',
    'class-fifu-help-strings.php',
    'class-fifu-image-strings.php',
    'class-fifu-meta-box-php-strings.php',
    'class-fifu-meta-box-strings.php',
    'class-fifu-migration-strings.php',
    'class-fifu-plugins-strings.php',
    'class-fifu-quick-edit-strings.php',
    'class-fifu-settings-strings.php',
    'class-fifu-uninstall-strings.php',
    'class-fifu-widget-strings.php',
    'class-fifu-language-loader.php',
);

foreach ( $files as $file ) {
    require_once __DIR__ . '/' . $file;
}

add_action('init', ['Fifu_Language_Loader', 'load_textdomain']);
