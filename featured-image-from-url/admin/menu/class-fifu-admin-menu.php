<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Fifu_Attachment_Update_Service', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-attachment-update-service.php';
}
if (!class_exists('Fifu_Attachment_Factory', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-attachment-factory.php';
}
if (!class_exists('Fifu_Default_Image_Service', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-default-image-service.php';
}

final class Fifu_Admin_Menu
{
    /**
     * Settings page action slug kept for backwards compatibility.
     */
    public const ACTION_SETTINGS = '/wp-admin/admin.php?page=featured-image-from-url';

    /**
     * Cloud page action slug kept for backwards compatibility.
     */
    public const ACTION_CLOUD = '/wp-admin/admin.php?page=fifu-cloud';

    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        if (is_multisite()) {
            add_action('network_admin_menu', [self::class, 'add_network_admin_menu']);
        }
        add_action('admin_enqueue_scripts', [self::class, 'maybe_enqueue_assets']);
    }

    public static function add_admin_menu(): void
    {
        self::insert_menu_common('manage_options', false);
    }

    public static function add_network_admin_menu(): void
    {
        if (!is_multisite()) {
            return;
        }

        Fifu_Network_Utils::with_main_site(static function () {
            self::insert_menu_common('manage_network_options', true);
        });
    }

    private static function insert_menu_common(string $capability, bool $is_network): void
    {
        $fifu = Fifu_Admin_Strings::get_settings_strings();

        // Assets enqueued separately via admin_enqueue_scripts for better control.

        $menu_callback = $is_network ? [ 'Fifu_Network_Utils', 'get_network_menu_html' ] : [ self::class, 'render_menu_page' ];
        $cloud_callback = $is_network ? [ 'Fifu_Network_Utils', 'cloud' ] : [ Fifu_Admin_Cloud_Page::class, 'render' ];
        $troubleshooting_callback = $is_network ? [ 'Fifu_Network_Utils', 'troubleshooting' ] : [ Fifu_Admin_Troubleshooting_Page::class, 'render' ];
        $status_callback = $is_network ? [ 'Fifu_Network_Utils', 'support_data' ] : [ Fifu_Admin_Support_Data_Page::class, 'render' ];

        add_menu_page('Featured Image from URL', 'FIFU', $capability, FIFU_SLUG, $menu_callback, 'dashicons-camera', 57);
        add_submenu_page(FIFU_SLUG, 'FIFU Settings', $fifu['options']['settings'](), $capability, FIFU_SLUG, $menu_callback);
        if (!$is_network) {
            add_submenu_page(FIFU_SLUG, 'FIFU Cloud', $fifu['options']['cloud'](), $capability, 'fifu-cloud', $cloud_callback);
            add_submenu_page(FIFU_SLUG, 'FIFU Troubleshooting', $fifu['options']['troubleshooting'](), $capability, 'fifu-troubleshooting', $troubleshooting_callback);
            add_submenu_page(FIFU_SLUG, 'FIFU Status', $fifu['options']['status'](), $capability, 'fifu-support-data', $status_callback);
        }

        add_action('admin_init', [ 'Fifu_Settings_Manager', 'register_menu_settings' ]);
    }

    public static function render_menu_page(): void
    {
        flush();

        $fifu = Fifu_Admin_Strings::get_settings_strings();
        $fifucloud = Fifu_Cloud_Strings::get_strings();

        wp_enqueue_style('fifu-base-ui-css', plugins_url('/admin/html/css/base-ui.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-menu-css', plugins_url('/admin/html/css/menu.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-pro-css', plugins_url('/admin/html/css/pro.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_script('fifu-menu-js', plugins_url('/admin/html/js/menu.js', FIFU_PLUGIN_FILE), ['jquery', 'jquery-ui'], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-auto-share-css', plugins_url('/admin/html/css/auto-share.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());

        wp_localize_script('fifu-menu-js', 'fifuScriptVars', [
            'restUrl' => esc_url_raw(rest_url()),
            'homeUrl' => esc_url_raw(home_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'restNamespaceV1' => defined('FIFU_REST_NAMESPACE_V1') ? FIFU_REST_NAMESPACE_V1 : FIFU_SLUG . '/v1',
            'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
            'wait' => $fifu['php']['message']['wait'](),
            'wait1' => $fifu['php']['message']['wait1'](),
            'saving' => $fifu['word']['saving'](),
            'saved' => $fifu['word']['saved'](),
            'error' => $fifu['word']['error'](),
            'reset' => $fifu['word']['reset'](),
            'save' => $fifu['word']['save'](),
            'pluginUrl' => esc_url_raw(plugins_url('', FIFU_PLUGIN_FILE)),
            'networkAdmin' => is_network_admin(),
        ]);

        $enable_auto_share = 'toggleoff';
        $enable_auto_share_facebook = 'toggleoff';
        $enable_auto_share_instagram = 'toggleoff';
        $enable_auto_share_x = 'toggleoff';
        $enable_auto_set = 'toggleoff';
        $max_auto_set_width = 600;
        $max_auto_set_height = 315;
        $auto_set_blocklist = '';
        $auto_set_cpt = 'post';
        $auto_set_source = '';
        $auto_set_layout = 'all';
        $skip = esc_attr(get_option('fifu_skip'));
        $html_cpt = esc_attr(get_option('fifu_html_cpt'));
        $enable_asin = 'toggleoff';
        $asin_custom_field = '';
        $asin_credentials_partner = '';
        $asin_credentials_access = '';
        $asin_credentials_secret = '';
        $asin_credentials_locale = '';
        $auto_share_x_clientid = '';
        $square_mobile = esc_attr(get_option('fifu_square_mobile'));
        $square_desktop = esc_attr(get_option('fifu_square_desktop'));
        $screenshot_custom_field = '';
        $screenshot_size = '1280x960';
        $enable_customfield = 'toggleoff';
        $customfield_custom_field = '';
        $finder_custom_field = '';
        $enable_finder = 'toggleoff';
        $enable_video_finder = 'toggleoff';
        $enable_amazon_finder = 'toggleoff';
        $enable_screenshot = 'toggleoff';
        $enable_debug = get_option('fifu_debug');
        $enable_audio = 'toggleoff';
        $enable_photon = get_option('fifu_photon');
        $enable_cdn_content = get_option('fifu_cdn_content');
        $enable_reset = get_option('fifu_reset');
        $enable_fake = get_option('fifu_fake');
        $enable_order_email = 'toggleoff';
        $enable_gallery = 'toggleoff';
        $enable_adaptive_height = 'toggleoff';
        $enable_videos_before = 'toggleoff';
        $enable_variations_merge = 'toggleoff';
        $enable_slider = 'toggleoff';
        $enable_slider_stop = 'toggleoff';
        $enable_slider_ctrl = 'toggleoff';
        $enable_slider_auto = 'toggleoff';
        $enable_slider_gallery = 'toggleoff';
        $enable_slider_thumb = 'toggleoff';
        $enable_slider_counter = 'toggleoff';
        $enable_slider_crop = 'toggleoff';
        $enable_slider_single = 'toggleoff';
        $enable_slider_vertical = 'toggleoff';
        $slider_speed = 1000;
        $slider_pause = 3000;
        $slider_left = '';
        $slider_right = '';
        $default_url = esc_url(get_option('fifu_default_url'));
        $default_cpt = esc_attr(get_option('fifu_default_cpt'));
        $pcontent_types = esc_attr(get_option('fifu_pcontent_types'));
        $hide_format = esc_attr(get_option('fifu_hide_format'));
        $hide_type = esc_attr(get_option('fifu_hide_type'));
        $enable_default_url = get_option('fifu_enable_default_url');
        $enable_wc_lbox = get_option('fifu_wc_lbox');
        $enable_wc_zoom = get_option('fifu_wc_zoom');
        $enable_hide = get_option('fifu_hide');
        $enable_pcontent_add = get_option('fifu_pcontent_add');
        $enable_pcontent_remove = get_option('fifu_pcontent_remove');
        $enable_get_first = get_option('fifu_get_first');
        $enable_ovw_first = get_option('fifu_ovw_first');
        $enable_update_all = 'toggleoff';
        $enable_run_delete_all = get_option('fifu_run_delete_all');
        $enable_run_delete_all_time = get_option('fifu_run_delete_all_time');
        $enable_auto_category = 'toggleoff';
        $enable_taxonomy = 'toggleoff';
        $enable_data_clean = 'toggleoff';
        include FIFU_ADMIN_DIR . '/html/menu.html';

        $arr = Fifu_Settings_Manager::update_from_request();

        if (!$arr['fifu_default_cpt']) {
            $default_url = $arr['fifu_default_url'];
            if (!empty($default_url) && Fifu_Options_Utils::is_on('fifu_enable_default_url') && Fifu_Options_Utils::is_on('fifu_fake')) {
                $att_id = (int) get_option('fifu_default_attach_id');
                $current_url = $att_id ? Fifu_Attachment_Update_Service::get_attachment_remote_url($att_id) : '';
                if (!$att_id || !$current_url) {
                    $attachment_factory = new Fifu_Attachment_Factory();
                    $att_id = $attachment_factory->create_attachment_for_url($default_url);
                    if ($att_id > 0) {
                        Fifu_Attachment_Update_Service::initialize_remote_attachment($att_id, $default_url, null);
                        update_option('fifu_default_attach_id', $att_id, 'no');
                    }
                    Fifu_Default_Image_Service::apply_default_to_all_missing_thumbnails();
                } else {
                    Fifu_Default_Image_Service::update_default_url($default_url);
                }
            }
        }

        if (Fifu_Options_Utils::is_on('fifu_reset')) {
            Fifu_Settings_Manager::reset_settings();
            update_option('fifu_reset', 'toggleoff', 'no');
        }
    }

    public static function maybe_enqueue_assets($hook): void
    {
        $page = $_GET['page'] ?? '';

        if (is_array($page)) {
            return;
        }

        $page = sanitize_key(wp_unslash((string) $page));

        $allowed_pages = [
            FIFU_SLUG,
            'fifu-cloud',
            'fifu-troubleshooting',
            'fifu-support-data',
        ];

        if (!in_array($page, $allowed_pages, true)) {
            return;
        }

        $fifu = Fifu_Admin_Strings::get_settings_strings();
        self::enqueue_assets_if_needed($fifu);
    }

    private static function enqueue_assets_if_needed(array $fifu): void
    {
        wp_enqueue_script('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/js/all.min.js');
        wp_enqueue_style('jquery-ui-style1', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css');
        wp_enqueue_style('jquery-ui-style2', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.structure.min.css');
        wp_enqueue_style('jquery-ui-style3', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.theme.min.css');

        wp_enqueue_script('jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js');
        wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');

        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

        wp_enqueue_style('datatable-css', '//cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css');
        wp_enqueue_style('datatable-select-css', '//cdn.datatables.net/select/1.3.3/css/select.dataTables.min.css');
        wp_enqueue_style('datatable-buttons-css', '//cdn.datatables.net/buttons/2.0.1/css/buttons.dataTables.min.css');
        wp_enqueue_script('datatable-js', '//cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js');
        wp_enqueue_script('datatable-select', '//cdn.datatables.net/select/1.3.3/js/dataTables.select.min.js');
        wp_enqueue_script('datatable-buttons', '//cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js');

        wp_enqueue_script(
            'fifu-rest-route-js',
            plugins_url('/admin/html/js/rest-route.js', FIFU_PLUGIN_FILE),
            ['jquery'],
            Fifu_Plugin_Info::get_enqueue_version()
        );

        wp_localize_script('fifu-rest-route-js', 'fifuScriptVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'restNamespaceV1' => defined('FIFU_REST_NAMESPACE_V1') ? FIFU_REST_NAMESPACE_V1 : FIFU_SLUG . '/v1',
        'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
        'networkAdmin' => is_network_admin(),
        ]);

    }

    /**
     * Indicates whether the SU private key has been stored.
     */
    public static function is_su_sign_up_complete(): bool
    {
        $privkey_option = get_option('fifu_su_privkey');
        return isset($privkey_option[0]) ? true : false;
    }

    /**
     * Returns the stored SU email after decoding it.
     */
    public static function get_su_email(): string
    {
        $su_email_option = get_option('fifu_su_email');
        return base64_decode($su_email_option[0] ?? '');
    }

    /**
     * Retrieves a short summary of the latest entries for a given meta key.
     */
    public static function get_last_meta_entries_summary(string $meta_key, int $limit = 3): string
    {
        $list = '';
        $rows = Fifu_Meta_Stats_Utils::get_last_meta_entries((string) $meta_key, $limit);
        foreach ($rows as $row) {
            $aux = $row->meta_value . "\n → " . get_permalink($row->id);
            $list .= "\n - " . $aux;
        }
        return $list;
    }

    /**
     * Returns a formatted summary describing all detected image sizes.
     */
    public static function get_registered_sizes_summary(): string
    {
        $options = new Fifu_Options_Query_Utils();
        $raw_sizes = $options->select_by_prefix('fifu_detected_size_');
        $formatted_list = '';

        if ($raw_sizes && is_array($raw_sizes)) {
            foreach ($raw_sizes as $size) {
                $name = str_replace('fifu_detected_size_', '', $size->option_name);
                $data = maybe_unserialize($size->option_value);
                if (is_array($data) && isset($data['w']) && isset($data['h']) && isset($data['c'])) {
                    $crop_value = $data['c'] ? '1' : '0';
                    $formatted_list .= "\n - "
                        . $name
                        . ': '
                        . $data['w']
                        . 'x'
                        . $data['h']
                        . 'x'
                        . $crop_value;
                }
            }
        }

        return $formatted_list ?: "\n - No registered sizes found";
    }

}
