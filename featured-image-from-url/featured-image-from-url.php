<?php

/*
 * Plugin Name: Featured Image from URL (FIFU)
 * Plugin URI: https://fifu.app/
 * Description: Use remote media as the featured image and beyond.
 * Version: 6.0.3
 * Author: fifu.app
 * Author URI: https://fifu.app/
 * Requires at least: 5.6
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * WC requires at least: 4.0
 * WC tested up to: 11.0.1
 * Text Domain: featured-image-from-url
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

$premium_handoff_file = __DIR__ . '/includes/compat/class-fifu-free-premium-handoff.php';

if (is_file($premium_handoff_file)) {
    require_once $premium_handoff_file;

    if (class_exists(Fifu_Free_Premium_Handoff::class, false)
        && Fifu_Free_Premium_Handoff::maybe_yield(__FILE__)) {
        return;
    }
}

define('FIFU_PLUGIN_FILE', __FILE__);
define('FIFU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FIFU_INCLUDES_DIR', FIFU_PLUGIN_DIR . 'includes');
define('FIFU_ADMIN_DIR', FIFU_PLUGIN_DIR . 'admin');
define('FIFU_LANGUAGES_DIR', WP_CONTENT_DIR . '/uploads/fifu/languages/');
define('FIFU_SLUG', 'featured-image-from-url');
define('FIFU_CLOUD_DEBUG', false);
define('FIFU_CLIENT', 'featured-image-from-url');
if (!defined('FIFU_REST_NAMESPACE_V1')) {
    define('FIFU_REST_NAMESPACE_V1', FIFU_SLUG . '/v1');
}
if (!defined('FIFU_REST_NAMESPACE_V2')) {
    define('FIFU_REST_NAMESPACE_V2', FIFU_SLUG . '/v2');
}

if (!defined('FIFU_PLUGIN_BASENAME')) {
    define('FIFU_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Author ID used to identify FIFU-managed attachment posts.
 */
define('FIFU_AUTHOR', get_option('fifu_author') ?: 77777);

if (!defined('FIFU_DELETE_ALL_URLS')) {
    define('FIFU_DELETE_ALL_URLS', false);
}

if (!defined('FIFU_DISABLE_AUTO_MIGRATION')) {
    define('FIFU_DISABLE_AUTO_MIGRATION', true);
}

$FIFU_SESSION = array();

// Required includes with error handling
$helper_includes = [
    FIFU_INCLUDES_DIR . '/url/class-fifu-html-attribute-utils.php',
    FIFU_INCLUDES_DIR . '/url/class-fifu-image-url-utils.php',
    FIFU_INCLUDES_DIR . '/url/class-fifu-upload-dir-utils.php',
    FIFU_INCLUDES_DIR . '/url/class-fifu-url-resolver.php',
    FIFU_INCLUDES_DIR . '/url/class-fifu-content-url-scanner.php',
    FIFU_INCLUDES_DIR . '/url/cdn/bootstrap-cdn.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-type-utils.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-meta-utils.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-main-image-resolver.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-term-image-alt-read-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-term-image-url-read-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-image-url-read-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-image-alt-read-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-image-urls.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-image-display-policy.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-media-placeholders.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-featured-image-filter.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-image-attributes-filter.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-content-image-cdn-optimizer.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-content-image-controller.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-product-variation-attributes-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-local-media-cleanup.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-update-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-attachment-sync-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-post-save-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-meta-maintenance-controller.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-default-image-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-repository.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-factory.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-product-variation-meta-repository.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-dimensions-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-meta-stats-utils.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-meta-gap-repository.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-metadata-queue-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-metadata-import-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-media-maintenance-service.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-meta-debug-controller.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-file-filters.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-ajax-filters.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-attachment-query-filters.php',
    FIFU_INCLUDES_DIR . '/meta/class-fifu-image-size-service.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-wp-context.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-options-utils.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-options-query-utils.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-db-compat.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-generic-utils.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-network-utils.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-settings-manager.php',
    FIFU_INCLUDES_DIR . '/compat/bootstrap-attachment-filters.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-plugin-info.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-attachment-legacy-fixer.php',
    FIFU_INCLUDES_DIR . '/compat/class-fifu-attachment-compat-filters.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-woocommerce-context.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-rss-image-namespace.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-home-social-tags.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-category-social-tags.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-woocommerce-display-configuration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-woocommerce-gallery-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-amp-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-web-stories-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-search-filter-pro-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-local-media-renderer.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-remote-media-renderer.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-frontend-assets.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-structured-data-renderer.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-post-social-tags.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-rss-image-item.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-block-editor-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/class-fifu-social-meta-filter.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-config.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-http-client.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-license-service.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-media-service.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-cron-service.php',
    FIFU_INCLUDES_DIR . '/integrations/cloud/class-fifu-cloud-usage-verification-service.php',
    FIFU_INCLUDES_DIR . '/integrations/frontend/class-fifu-frontend-hooks.php',
    FIFU_INCLUDES_DIR . '/integrations/rest/class-fifu-rest-routing-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/rest/class-fifu-rest-permissions.php',
    FIFU_INCLUDES_DIR . '/integrations/bootstrap-structured-data.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-content-egg-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-datafeedr-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-polylang-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-woobe-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-yoast-image-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-vg-sheet-editor-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-wp-force-plugin-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-wpml-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/seo/class-fifu-rank-math-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/seo/class-fifu-yoast-schema-graph-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-dokan-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-mvx-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-yoast-duplicate-post-integration.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-unsplash-image-service.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-bootstrap.php',
    FIFU_INCLUDES_DIR . '/api/rest/class-fifu-rest-quick-edit-controller.php',
    FIFU_INCLUDES_DIR . '/api/rest/class-fifu-rest-search-controller.php',
    FIFU_INCLUDES_DIR . '/api/rest/class-fifu-rest-metadata-worker-controller.php',
    FIFU_INCLUDES_DIR . '/i18n/bootstrap.php',
];

$required_includes = array_merge($helper_includes, [
    FIFU_INCLUDES_DIR . '/meta/fifu-meta-functions.php',
    FIFU_INCLUDES_DIR . '/meta/bootstrap-external-media-auto-detect.php',
    FIFU_INCLUDES_DIR . '/meta/bootstrap-meta-protection.php',
    FIFU_INCLUDES_DIR . '/meta/bootstrap-post-save.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-plugin-detector.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-yith-wishlist-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/class-fifu-wordpress-importer-integration.php',
    FIFU_INCLUDES_DIR . '/integrations/plugins/bootstrap-plugins.php',
    FIFU_INCLUDES_DIR . '/integrations/themes/class-fifu-theme-detector.php',
    FIFU_INCLUDES_DIR . '/integrations/themes/class-fifu-houzez-dt-integration.php',
    FIFU_INCLUDES_DIR . '/compat/functions-compat-detectors.php',
    FIFU_INCLUDES_DIR . '/compat/functions-compat-options.php',
    FIFU_INCLUDES_DIR . '/url/fifu-url-functions.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-internal-media-write-service.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-developer-media-service.php',
    FIFU_INCLUDES_DIR . '/api/fifu-developer-functions.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-migration-controller.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-controller.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-sizes-controller.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-media-controller.php',
    FIFU_INCLUDES_DIR . '/api/class-fifu-rest-cloud-controller.php',
    FIFU_INCLUDES_DIR . '/integrations/rest/bootstrap-rest.php',
]);

foreach ($required_includes as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

Fifu_Cloud_Cron_Service::register_hooks();

// Comments must be in English.
$post_attachment_sync_bootstrap = FIFU_INCLUDES_DIR . '/meta/bootstrap-post-attachment-sync.php';
if (file_exists($post_attachment_sync_bootstrap)) {
    require_once $post_attachment_sync_bootstrap;
}

$should_register_wpml_integration = Fifu_Plugin_Detector::is_wcml_active()
    || defined('ICL_SITEPRESS_VERSION')
    || defined('WPML_VERSION');

if ($should_register_wpml_integration) {
    Fifu_WPML_Integration::register_hooks();
}

if (class_exists('Fifu_Houzez_Dt_Integration')) {
    Fifu_Houzez_Dt_Integration::register_hooks();
}

if (class_exists('Fifu_VG_Sheet_Editor_Integration')) {
    Fifu_VG_Sheet_Editor_Integration::register_hooks();
}

$db2_bootstrap_file = FIFU_ADMIN_DIR . '/db2/bootstrap-db2.php';
if (file_exists($db2_bootstrap_file)) {
    require_once $db2_bootstrap_file;
}

require_once FIFU_INCLUDES_DIR . '/compat/class-fifu-transient-manager.php';
require_once FIFU_INCLUDES_DIR . '/compat/class-fifu-file-logger.php';
require_once FIFU_INCLUDES_DIR . '/compat/class-fifu-license-crypto.php';
require_once FIFU_ADMIN_DIR . '/menu/class-fifu-admin-menu.php';
require_once FIFU_ADMIN_DIR . '/menu/pages/class-fifu-admin-troubleshooting-page.php';
require_once FIFU_ADMIN_DIR . '/menu/pages/class-fifu-admin-support-data-page.php';
require_once FIFU_ADMIN_DIR . '/debug/class-fifu-debug-logs-package-service.php';
require_once FIFU_ADMIN_DIR . '/debug/class-fifu-debug-logs-download-controller.php';
require_once FIFU_ADMIN_DIR . '/menu/pages/class-fifu-admin-cloud-page.php';
require_once FIFU_ADMIN_DIR . '/migration/core/class-fifu-migration-stats.php';

$auto_migration_bootstrap = __DIR__ . '/admin/migration/bootstrap-auto.php';
if (file_exists($auto_migration_bootstrap)) {
    require_once $auto_migration_bootstrap;
}

$required_admin = [
    FIFU_ADMIN_DIR . '/migration/bootstrap-admin.php',
    FIFU_ADMIN_DIR . '/migration/bootstrap-legacy-tables.php',
    // Bootstraps the featured image columns.
    FIFU_ADMIN_DIR . '/columns/bootstrap-columns.php',
    FIFU_ADMIN_DIR . '/meta-box/class-fifu-meta-box-dimensions-reader.php',
    FIFU_ADMIN_DIR . '/meta-box/bootstrap-meta-box.php',
    FIFU_ADMIN_DIR . '/widgets/bootstrap-widgets.php',
    FIFU_ADMIN_DIR . '/widgets/class-fifu-widget-image.php',
    FIFU_ADMIN_DIR . '/review/class-fifu-review-notice.php',
];

foreach ($required_admin as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

if (class_exists(Fifu_Review_Notice::class)) {
    Fifu_Review_Notice::register_hooks();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    $cli_bootstrap = FIFU_ADMIN_DIR . '/cli/bootstrap-cli.php';
    if ( file_exists( $cli_bootstrap ) ) {
        require_once $cli_bootstrap;
    }

    // Migration CLI bootstrap that keeps migration logic within admin/migration.
    $migration_cli_bootstrap = FIFU_ADMIN_DIR . '/migration/bootstrap-cli.php';
    if ( file_exists( $migration_cli_bootstrap ) ) {
        require_once $migration_cli_bootstrap;
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('Fifu_File_Logger')) {
        Fifu_File_Logger::register_hooks();
    }

    if (function_exists('dokan') && class_exists('Fifu_Dokan_Integration')) {
        Fifu_Dokan_Integration::register_hooks();
    }

    if (function_exists('mvx_is_module_active') && class_exists('Fifu_Mvx_Integration')) {
        Fifu_Mvx_Integration::register_hooks();
    }

    if (class_exists('Fifu_Yoast_Duplicate_Post_Integration')) {
        Fifu_Yoast_Duplicate_Post_Integration::register_hooks();
    }

    if (is_admin()) {
        if (class_exists('Fifu_Admin_Menu')) {
            Fifu_Admin_Menu::register_hooks();
        }
        if (class_exists('Fifu_Debug_Logs_Download_Controller')) {
            Fifu_Debug_Logs_Download_Controller::register_hooks();
        }
    }
});

add_action('plugins_loaded', [Fifu_Frontend_Hooks::class, 'register']);
register_activation_hook(__FILE__, 'fifu_activate');

function fifu_activate($network_wide) {
    // https://multilingualpress.org/docs/how-to-install-wordpress-multisite/
    if (is_multisite() && $network_wide) {
        delete_site_option(fifu_schema_migration_network_version_option_name());
        delete_site_option(fifu_db2_key_seed_network_version_option_name());

        $blog_ids = fifu_get_network_blog_ids();
        foreach ($blog_ids as $blog_id) {
            fifu_run_in_blog_context((int) $blog_id, static function (): void {
                fifu_activate_actions();
                Fifu_Options_Utils::set_author();
            });
        }

        fifu_record_network_schema_migration_version_if_complete($blog_ids, fifu_current_plugin_version());
        fifu_record_network_key_seed_version_if_complete($blog_ids);
    } else {
        fifu_activate_actions();
        Fifu_Options_Utils::set_author();
        // Set redirect transient only for non-multisite
        set_transient('fifu_redirect_to_settings', true, 30);
    }
}

// Redirect to plugin settings page after activation (non-multisite only)
add_action('admin_init', function () {
    if (!is_multisite() && get_transient('fifu_redirect_to_settings')) {
        delete_transient('fifu_redirect_to_settings');
        if (is_admin() && !isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=' . FIFU_SLUG));
            exit;
        }
    }
});

function fifu_activate_actions() {
    add_option(
        'fifu_first_activation',
        true,
        '',
        'no'
    );

    add_option(
        'fifu_installed_time',
        time(),
        '',
        'no'
    );

    fifu_db_create_table_invalid_media_su();
    fifu_db_maybe_create_table_meta_in();
    fifu_db_maybe_create_table_meta_out();
    if (function_exists('fifu_legacy_tables_manager')) {
        fifu_legacy_tables_manager()->ensure_all_tables();
    }

    // Run new FIFU schema migrations for this blog on activation.
    fifu_run_schema_migrations_for_blog();
    fifu_record_db2_key_seed_version_if_ready();
    fifu_record_schema_migration_version_if_ready();
}

register_deactivation_hook(__FILE__, 'fifu_deactivation');

/**
 * Runs FIFU database schema migrations (new tables) for the current blog.
 *
 * This ONLY creates or updates the new FIFU tables (fifu_url, fifu_key, fifu_map).
 * It does NOT run any data migrations.
 *
 * @return void
 */
function fifu_run_schema_migrations_for_blog(?array $schema_files = null) {
    $schema_manager_file = FIFU_ADMIN_DIR . '/migration/core/class-fifu-schema-manager.php';

    if (file_exists($schema_manager_file)) {
        require_once $schema_manager_file;
    }

    if (class_exists('Fifu_Schema_Manager')) {
        $schema_manager = new Fifu_Schema_Manager();
        if ($schema_files === null) {
            $schema_manager->run_all();
        } else {
            $schema_manager->run_files($schema_files);
        }
    }
}

function fifu_current_plugin_version(): string {
    $plugin_data = get_file_data(
        FIFU_PLUGIN_FILE,
        ['Version' => 'Version'],
        'plugin'
    );

    return (string) ($plugin_data['Version'] ?? '');
}

function fifu_db2_key_seed_version_option_name(): string {
    return 'fifu_db2_key_seed_version';
}

function fifu_db2_key_seed_network_version_option_name(): string {
    return 'fifu_db2_key_seed_network_version';
}

function fifu_schema_migration_network_version_option_name(): string {
    return 'fifu_schema_migration_network_version';
}

function fifu_schema_migration_runtime_upgrade_attempted_version_option_name(): string {
    return 'fifu_schema_migration_runtime_upgrade_attempted_version';
}

function fifu_schema_migration_network_runtime_upgrade_attempted_version_option_name(): string {
    return 'fifu_schema_migration_network_runtime_upgrade_attempted_version';
}

function fifu_db2_key_seed_revision(): string {
    return '1';
}

function fifu_db2_required_key_seed(): array {
    return [
        1 => 'image',
        2 => 'slider',
        3 => 'video',
        4 => 'audio',
        5 => 'iframe',
        6 => 'custom_video',
        7 => 'finder',
        8 => 'redirect',
    ];
}

function fifu_db2_required_key_seed_exists_for_current_blog(): bool {
    global $wpdb;

    $key_rows = $wpdb->get_results(
        "SELECT key_id, key_type
         FROM {$wpdb->prefix}fifu_key
         WHERE key_id BETWEEN 1 AND 8",
        ARRAY_A
    );

    if (!is_array($key_rows) || $wpdb->last_error !== '') {
        return false;
    }

    $stored_keys = [];

    foreach ($key_rows as $row) {
        $stored_keys[(int) $row['key_id']] = (string) $row['key_type'];
    }

    foreach (fifu_db2_required_key_seed() as $key_id => $key_type) {
        if (($stored_keys[$key_id] ?? null) !== $key_type) {
            return false;
        }
    }

    return true;
}

function fifu_db2_required_schema_exists_for_current_blog(): bool {
    global $wpdb;

    foreach ([
        'fifu_url',
        'fifu_key',
        'fifu_map',
        'fifu_term_map',
        'fifu_alt',
        'fifu_alt_map',
        'fifu_alt_term_map',
        'fifu_sent',
        'fifu_sent_event',
        'fifu_identifier_type',
        'fifu_identifier',
    ] as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            return false;
        }

        $usable = $wpdb->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
        if ($usable === false) {
            return false;
        }
    }

    return fifu_db2_required_key_seed_exists_for_current_blog();
}

/**
 * Returns the completed Free schema-migration version.
 *
 * When the neutral marker is absent, an existing Premium marker is
 * accepted only as evidence that the required DB2 schema was previously
 * completed. The imported neutral marker always uses the current Free
 * plugin version because Premium and Free use different version series.
 *
 * The legacy Premium option is intentionally preserved.
 */
function fifu_get_schema_migration_version(): string {
    $version = trim(
        (string) get_option(
            'fifu_schema_migration_version',
            ''
        )
    );

    if ($version !== '') {
        return $version;
    }

    $legacy_version = trim(
        (string) get_option(
            'fifu_premium_version',
            ''
        )
    );

    if ($legacy_version === '') {
        return '0.0.0';
    }

    if (!fifu_db2_required_schema_exists_for_current_blog()) {
        return '0.0.0';
    }

    $current_version = fifu_current_plugin_version();

    if ($current_version === '') {
        return '0.0.0';
    }

    update_option(
        'fifu_schema_migration_version',
        $current_version
    );

    return $current_version;
}

/**
 * Records the current Free version after the required DB2 schema is usable.
 */
function fifu_record_schema_migration_version_if_ready(): bool {
    $current_version = fifu_current_plugin_version();

    if ($current_version === '') {
        return false;
    }

    if (!fifu_db2_required_schema_exists_for_current_blog()) {
        return false;
    }

    update_option(
        'fifu_schema_migration_version',
        $current_version
    );

    return true;
}

function fifu_maybe_initialize_db2_key_seed_for_current_blog(): bool {
    $current_version = fifu_current_plugin_version();

    if ($current_version === '') {
        return false;
    }

    $seed_version = (string) get_option(
        fifu_db2_key_seed_version_option_name(),
        '0'
    );
    $seed_revision = fifu_db2_key_seed_revision();

    if (version_compare($seed_version, $seed_revision, '>=')) {
        return true;
    }

    if (!fifu_db2_required_key_seed_exists_for_current_blog()) {
        fifu_run_schema_migrations_for_blog([
            '002_create_fifu_key.sql',
        ]);
    }

    if (!fifu_db2_required_key_seed_exists_for_current_blog()) {
        error_log(
            'FIFU: the DB2 key dictionary does not match the canonical FIFU key mapping.'
        );

        return false;
    }

    update_option(
        fifu_db2_key_seed_version_option_name(),
        $seed_revision
    );

    return true;
}

function fifu_record_db2_key_seed_version_if_ready(): bool {
    if (!fifu_db2_required_key_seed_exists_for_current_blog()) {
        return false;
    }

    update_option(
        fifu_db2_key_seed_version_option_name(),
        fifu_db2_key_seed_revision()
    );

    return true;
}

function fifu_record_network_key_seed_version_if_complete(array $blog_ids): bool {
    if (fifu_current_plugin_version() === '') {
        return false;
    }

    $seed_revision = fifu_db2_key_seed_revision();
    $network_seed_complete = true;

    foreach ($blog_ids as $blog_id) {
        fifu_run_in_blog_context(
            (int) $blog_id,
            static function () use (&$network_seed_complete, $seed_revision): void {
                if (version_compare(
                    (string) get_option(
                        fifu_db2_key_seed_version_option_name(),
                        '0'
                    ),
                    $seed_revision,
                    '<'
                )) {
                    $network_seed_complete = false;
                }
            }
        );
    }

    if ($network_seed_complete) {
        update_site_option(
            fifu_db2_key_seed_network_version_option_name(),
            $seed_revision
        );
    }

    return $network_seed_complete;
}

function fifu_record_network_schema_migration_version_if_complete(
    array $blog_ids,
    string $current_version
): bool {
    if ($current_version === '') {
        return false;
    }

    $network_version_complete = true;

    foreach ($blog_ids as $blog_id) {
        fifu_run_in_blog_context(
            (int) $blog_id,
            static function () use (&$network_version_complete, $current_version): void {
                if (version_compare(
                    (string) get_option(
                        'fifu_schema_migration_version',
                        '0.0.0'
                    ),
                    $current_version,
                    '<'
                )) {
                    $network_version_complete = false;
                }
            }
        );
    }

    if ($network_version_complete) {
        update_site_option(
            fifu_schema_migration_network_version_option_name(),
            $current_version
        );
    } else {
        delete_site_option(
            fifu_schema_migration_network_version_option_name()
        );
    }

    return $network_version_complete;
}

/**
 * Completes upgrade work for the current site when its marker is stale
 * or the required DB2 schema is not usable.
 */
function fifu_maybe_run_upgrade_routines_for_current_blog(): void {
    $current_version = fifu_current_plugin_version();

    if ($current_version === '') {
        return;
    }

    /*
     * This check must happen before fifu_get_schema_migration_version().
     * The Free getter can validate the DB2 schema while importing the legacy
     * Premium marker.
     */
    $runtime_attempted_version = (string) get_option(
        fifu_schema_migration_runtime_upgrade_attempted_version_option_name(),
        '0.0.0'
    );

    if (version_compare($runtime_attempted_version, $current_version, '>=')) {
        return;
    }

    $installed_version = fifu_get_schema_migration_version();

    try {
        if (version_compare($installed_version, $current_version, '>=')) {
            fifu_maybe_initialize_db2_key_seed_for_current_blog();
            return;
        }

        if (version_compare($installed_version, '6.0.0', '<')) {
            fifu_upgrade_actions();
            return;
        }

        fifu_maybe_initialize_db2_key_seed_for_current_blog();
        fifu_record_schema_migration_version_if_ready();
    } finally {
        update_option(
            fifu_schema_migration_runtime_upgrade_attempted_version_option_name(),
            $current_version
        );
    }
}

/**
 * Completes upgrade work for the active site or every network site.
 */
function fifu_maybe_run_upgrade_routines(): void {
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    if (is_multisite()) {
        $sitewide_plugins = (array) get_site_option('active_sitewide_plugins', []);
        $is_network_active = array_key_exists(plugin_basename(FIFU_PLUGIN_FILE), $sitewide_plugins);

        if ($is_network_active) {
            $current_version = fifu_current_plugin_version();

            if ($current_version === '') {
                return;
            }

            $network_runtime_attempted_version = (string) get_site_option(
                fifu_schema_migration_network_runtime_upgrade_attempted_version_option_name(),
                '0.0.0'
            );

            if (version_compare($network_runtime_attempted_version, $current_version, '>=')) {
                return;
            }

            $network_version = (string) get_site_option(
                fifu_schema_migration_network_version_option_name(),
                '0.0.0'
            );
            $seed_revision = fifu_db2_key_seed_revision();
            $network_seed_version = (string) get_site_option(
                fifu_db2_key_seed_network_version_option_name(),
                '0'
            );

            if (
                version_compare($network_version, $current_version, '>=')
                && version_compare($network_seed_version, $seed_revision, '>=')
            ) {
                return;
            }

            try {
                $network_seed_complete = true;
                $blog_ids = fifu_get_network_blog_ids();
                foreach ($blog_ids as $blog_id) {
                    fifu_run_in_blog_context((int) $blog_id, static function () use (&$network_seed_complete, $seed_revision): void {
                        fifu_maybe_run_upgrade_routines_for_current_blog();
                        if (version_compare(
                            (string) get_option(fifu_db2_key_seed_version_option_name(), '0'),
                            $seed_revision,
                            '<'
                        )) {
                            $network_seed_complete = false;
                        }
                    });
                }

                if ($network_seed_complete) {
                    update_site_option(fifu_db2_key_seed_network_version_option_name(), $seed_revision);
                }

                fifu_record_network_schema_migration_version_if_complete($blog_ids, $current_version);
            } finally {
                update_site_option(
                    fifu_schema_migration_network_runtime_upgrade_attempted_version_option_name(),
                    $current_version
                );
            }

            return;
        }
    }

    fifu_maybe_run_upgrade_routines_for_current_blog();
}

add_action(
    'plugins_loaded',
    'fifu_maybe_run_upgrade_routines',
    1
);

function fifu_deactivation() {
    if (class_exists(Fifu_Cloud_Cron_Service::class)) {
        Fifu_Cloud_Cron_Service::clear_schedules();
    }

    if (function_exists('fifu_db2_orphan_gc_service')) {
        $service = fifu_db2_orphan_gc_service();
        if ($service instanceof Fifu_Db2_Orphan_Gc_Service) {
            $service->disableAutomaticRuntime();
        }
    }
}

add_action('upgrader_process_complete', 'fifu_upgrade', 10, 2);

function fifu_upgrade($upgrader_object, $options) {
    if (!is_array($options)) {
        return;
    }

    $current_plugin_path_name = plugin_basename(__FILE__);
    if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
        return;
    }

    $plugins = [];

    if (isset($options['plugins'])) {
        $plugins = array_merge($plugins, (array) $options['plugins']);
    }

    if (isset($options['plugin'])) {
        $plugins[] = $options['plugin'];
    }

    if (!in_array($current_plugin_path_name, $plugins, true)) {
        return;
    }

    if (is_multisite()) {
        delete_site_option(fifu_schema_migration_network_version_option_name());

        $blog_ids = fifu_get_network_blog_ids();
        foreach ($blog_ids as $blog_id) {
            fifu_run_in_blog_context((int) $blog_id, static function (): void {
                fifu_upgrade_actions();
            });
        }
        fifu_record_network_schema_migration_version_if_complete($blog_ids, fifu_current_plugin_version());
        fifu_record_network_key_seed_version_if_complete($blog_ids);
        return;
    }

    fifu_upgrade_actions();
}

function fifu_upgrade_actions() {
    fifu_db_create_table_invalid_media_su();
    fifu_db_maybe_create_table_meta_in();
    fifu_db_maybe_create_table_meta_out();
    fifu_db_delete_deprecated_data();

    if (function_exists('fifu_legacy_tables_manager')) {
        fifu_legacy_tables_manager()->ensure_all_tables();
    }

    // Ensure new FIFU schema is also updated on plugin upgrade.
    fifu_run_schema_migrations_for_blog();
    fifu_record_db2_key_seed_version_if_ready();
    fifu_record_schema_migration_version_if_ready();
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fifu_action_links');
add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), 'fifu_action_links');

function fifu_action_links($links) {
    if (!is_array($links)) {
        return $links;
    }

    $strings = Fifu_Plugins_Strings::get_strings();
    $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=featured-image-from-url')) . '">' . $strings['settings']() . '</a>';
    $links[] = '<a href="https://fifu.app/" target="_blank" rel="noopener noreferrer">' . wp_kses_post($strings['upgrade']()) . '</a>';
    return $links;
}

function fifu_plugin_row_meta($plugin_meta, $plugin_file, $plugin_data, $status) {
    if (!is_array($plugin_meta)) {
        return $plugin_meta;
    }

    if (!is_string($plugin_file)) {
        return $plugin_meta;
    }

    if (strpos($plugin_file, 'featured-image-from-url.php') !== false) {
        $strings = Fifu_Plugins_Strings::get_strings();
        $new_links = array(
            'email' => '<a style="color:#2271b1">support@fifu.app</a>',
            'rate' => '<a href="' . esc_url(Fifu_Review_Notice::review_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html($strings['rate']()) . '</a>',
        );
        $plugin_meta = array_merge($plugin_meta, $new_links);
    }
    return $plugin_meta;
}

add_filter('plugin_row_meta', 'fifu_plugin_row_meta', 10, 4);

if (class_exists('Fifu_Rest_Bootstrap')) {
    Fifu_Rest_Bootstrap::init();
}

// TODO: wire FIFU OO handlers (structure only).
add_filter( 'the_content', [ Fifu_Content_Image_Controller::class, 'append_featured_image' ] );
add_filter( 'the_content', [ Fifu_Content_Image_Controller::class, 'remove_duplicate_featured_from_content' ] );
add_action( 'pre_rss2_ns', [ Fifu_Rss_Image_Namespace::class, 'start_buffer' ], 1 );
add_action( 'rss2_ns', [ Fifu_Rss_Image_Namespace::class, 'inject_media_namespace' ], 9999 );
add_filter( 'woocommerce_product_get_image', [ Fifu_Woocommerce_Gallery_Integration::class, 'filter_product_image' ], 10, 5 );
add_action( 'woocommerce_product_duplicate', [ Fifu_Woocommerce_Gallery_Integration::class, 'on_product_duplicate' ], 10, 1 );
add_action(
    'plugins_loaded',
    [ Fifu_Wp_Force_Plugin_Integration::class, 'register_hooks' ]
);

add_action(
    'plugins_loaded',
    [ Fifu_Block_Editor_Integration::class, 'register_hooks' ]
);

function fifu_uninstall() {
    global $pagenow;
    if ($pagenow !== 'plugins.php')
        return;

    $strings = Fifu_Uninstall_Strings::get_strings();

    wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
    wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
    wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
    wp_enqueue_style('fifu-uninstall-css', plugins_url('includes/html/css/uninstall.css', __FILE__), array(), Fifu_Plugin_Info::get_enqueue_version());
    wp_enqueue_script('fifu-uninstall-js', plugins_url('includes/html/js/uninstall.js', __FILE__), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());
    wp_localize_script('fifu-uninstall-js', 'fifuUninstallVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
        'pluginBasename' => FIFU_PLUGIN_BASENAME,
        'buttonTextClean' => $strings['button']['text']['clean'](),
        'buttonTextDeactivate' => $strings['button']['text']['deactivate'](),
        'buttonDescriptionClean' => $strings['button']['description']['clean'](),
        'buttonDescriptionDeactivate' => $strings['button']['description']['deactivate'](),
        'textWhy' => $strings['text']['why'](),
        'textEmail' => $strings['text']['email'](),
        'textReasonConflict' => $strings['text']['reason']['conflict'](),
        'textReasonPro' => $strings['text']['reason']['pro'](),
        'textReasonSeo' => $strings['text']['reason']['seo'](),
        'textReasonLocal' => $strings['text']['reason']['local'](),
        'textReasonUnderstand' => $strings['text']['reason']['undestand'](),
        'textReasonOthers' => $strings['text']['reason']['others'](),
    ]);
}

add_action('admin_footer', 'fifu_uninstall');

// https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

function fifu_prepare_network_state_for_new_site($new_site): void {
    if (!is_multisite() || !fifu_is_network_active()) {
        return;
    }

    $blog_id = is_object($new_site) && isset($new_site->blog_id)
        ? (int) $new_site->blog_id
        : 0;

    if (!$blog_id) {
        return;
    }

    $state = $GLOBALS['fifu_network_pre_insert_state'] ?? [];
    $state[$blog_id] = [
        'schema' => version_compare(
            (string) get_site_option(
                fifu_schema_migration_network_version_option_name(),
                '0.0.0'
            ),
            fifu_current_plugin_version(),
            '>='
        ),
        'key_seed' => version_compare(
            (string) get_site_option(
                fifu_db2_key_seed_network_version_option_name(),
                '0'
            ),
            fifu_db2_key_seed_revision(),
            '>='
        ),
    ];

    $GLOBALS['fifu_network_pre_insert_state'] = $state;

    delete_site_option(fifu_schema_migration_network_version_option_name());
    delete_site_option(fifu_db2_key_seed_network_version_option_name());
    delete_site_option(fifu_schema_migration_network_runtime_upgrade_attempted_version_option_name());
}

add_action('wp_insert_site', 'fifu_prepare_network_state_for_new_site', 10, 1);

function fifu_custom_action_after_site_initialization($new_site) {
    if (!is_multisite()) {
        return;
    }

    if (!fifu_is_network_active()) {
        return;
    }

    $blog_id = is_object($new_site) && isset($new_site->blog_id) ? (int) $new_site->blog_id : 0;
    if (!$blog_id) {
        return;
    }

    $state = $GLOBALS['fifu_network_pre_insert_state'] ?? [];
    $pre_insert_state = $state[$blog_id] ?? [];
    $was_network_schema_complete = !empty($pre_insert_state['schema']);
    $was_network_seed_complete = !empty($pre_insert_state['key_seed']);
    unset($state[$blog_id]);
    $GLOBALS['fifu_network_pre_insert_state'] = $state;

    delete_site_option(fifu_schema_migration_network_version_option_name());
    delete_site_option(fifu_db2_key_seed_network_version_option_name());
    delete_site_option(fifu_schema_migration_network_runtime_upgrade_attempted_version_option_name());

    $seed_initialized = false;
    $schema_initialized = false;
    fifu_run_in_blog_context($blog_id, static function () use (&$seed_initialized, &$schema_initialized): void {
        fifu_activate_actions();
        Fifu_Options_Utils::set_author();
        $seed_initialized = version_compare(
            (string) get_option(fifu_db2_key_seed_version_option_name(), '0'),
            fifu_db2_key_seed_revision(),
            '>='
        );
        $schema_initialized = fifu_record_schema_migration_version_if_ready();
    });

    if ($was_network_seed_complete && $seed_initialized) {
        update_site_option(
            fifu_db2_key_seed_network_version_option_name(),
            fifu_db2_key_seed_revision()
        );
    }

    if ($was_network_schema_complete && $schema_initialized) {
        update_site_option(
            fifu_schema_migration_network_version_option_name(),
            fifu_current_plugin_version()
        );
    }
}

add_action('wp_initialize_site', 'fifu_custom_action_after_site_initialization');

function fifu_get_network_blog_ids(): array {
    if (!is_multisite()) {
        return [];
    }

    if (function_exists('get_sites')) {
        $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
        if (!empty($site_ids)) {
            return array_map('intval', $site_ids);
        }
    }

    global $wpdb;

    return array_map('intval', (array) $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs"));
}

function fifu_run_in_blog_context(int $blog_id, callable $callback): void {
    $current_blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
    $switched = false;

    if ($blog_id !== 0 && $blog_id !== $current_blog_id && function_exists('switch_to_blog')) {
        switch_to_blog($blog_id);
        $switched = true;
    }

    try {
        $callback();
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }
}

function fifu_is_network_active(): bool {
    if (!is_multisite()) {
        return false;
    }

    if (function_exists('is_plugin_active_for_network')) {
        return is_plugin_active_for_network(FIFU_PLUGIN_BASENAME);
    }

    $active_sitewide_plugins = (array) get_site_option('active_sitewide_plugins', []);
    return array_key_exists(FIFU_PLUGIN_BASENAME, $active_sitewide_plugins);
}
