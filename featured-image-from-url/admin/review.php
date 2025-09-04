<?php

// Review notice logic (polite, dismissible, plugin pages only)

add_action('admin_init', 'fifu_handle_review_action');
add_action('admin_notices', 'fifu_maybe_show_review_notice');
add_action('in_admin_header', 'fifu_remove_notices_on_fifu_pages', PHP_INT_MAX);

function fifu_is_fifu_screen() {
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if (!$page) {
        return false;
    }
    if ($page === 'featured-image-from-url') {
        return true;
    }
    return strpos($page, 'fifu-') === 0;
}

function fifu_review_wporg_url() {
    return 'https://wordpress.org/support/plugin/featured-image-from-url/reviews/?filter=5#new-post';
}

function fifu_remove_notices_on_fifu_pages() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!fifu_is_fifu_screen()) {
        return;
    }
    remove_all_actions('admin_notices');
    remove_all_actions('user_admin_notices');
    remove_all_actions('network_admin_notices');
    remove_all_actions('all_admin_notices');
}

function fifu_handle_review_action() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['fifu_review_action'])) {
        if (!get_option('fifu_installed_time')) {
            update_option('fifu_installed_time', time());
        }
        return;
    }

    $action = sanitize_text_field(wp_unslash($_GET['fifu_review_action']));
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'fifu_review_nonce')) {
        return;
    }

    $now = time();
    if ($action === 'later') {
        update_option('fifu_review_snooze_until', $now + 30 * DAY_IN_SECONDS);
    } elseif ($action === 'done') {
        update_option('fifu_review_done', 1);
        delete_option('fifu_review_snooze_until');
    }

    $redirect = remove_query_arg(array('fifu_review_action', '_wpnonce'));
    wp_safe_redirect($redirect);
    exit;
}

/*
  SQL to reset the review notice timing and make it show immediately:
  DELETE FROM wp_options WHERE option_name IN ('fifu_review_done','fifu_review_snooze_until');
  INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('fifu_installed_time', UNIX_TIMESTAMP() - 15*86400, 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value);
 */

function fifu_maybe_show_review_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (fifu_is_fifu_screen()) {
        return;
    }

    $installed = intval(get_option('fifu_installed_time', time()));
    $done = intval(get_option('fifu_review_done')) === 1;
    $snooze_until = intval(get_option('fifu_review_snooze_until', 0));
    $now = time();

    if ($done || $now < $installed + 14 * DAY_IN_SECONDS || $now < $snooze_until) {
        return;
    }

    $base = add_query_arg(array());
    $later_url = wp_nonce_url(add_query_arg(array('fifu_review_action' => 'later'), $base), 'fifu_review_nonce');
    $done_url = wp_nonce_url(add_query_arg(array('fifu_review_action' => 'done'), $base), 'fifu_review_nonce');
    $review_url = fifu_review_wporg_url();
    $support_url = 'https://wordpress.org/support/plugin/featured-image-from-url/';

    $strings = fifu_get_strings_plugins();
    echo '<div class="notice notice-info fifu-keep-notice" style="padding:12px 15px;">';
    echo '<div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px;">';
    echo '<p style="margin:0; display:flex; align-items:center;">'
    . '<span class="dashicons dashicons-camera" style="font-size:20px; margin-right:6px;"></span>'
    . '<strong>' . esc_html($strings['review']['title']()) . '</strong>&nbsp;'
    . esc_html($strings['review']['message']())
    . '</p>';
    echo '</div>';
    echo '<p style="margin:0;">';
    echo '<a class="button button-primary" style="margin-right:6px;" target="_blank" rel="noopener noreferrer" href="' . esc_url($review_url) . '">' . esc_html($strings['review']['leave']()) . '</a>';
    echo '<a class="button" style="margin-right:6px;" href="' . esc_url($later_url) . '">' . esc_html($strings['review']['later']()) . '</a>';
    echo '<a class="button" style="margin-right:6px;" href="' . esc_url($done_url) . '">' . esc_html($strings['review']['done']()) . '</a>';
    echo '<a class="button-link" target="_blank" rel="noopener noreferrer" href="' . esc_url($support_url) . '">' . esc_html($strings['review']['help']()) . '</a>';
    echo '</p>';
    echo '</div>';
}

