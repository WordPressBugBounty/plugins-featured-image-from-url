<?php

class fifu_cli extends WP_CLI_Command {

    // admin

    function reset() {
        fifu_reset_settings();
        //WP_CLI::line($args[0]);
    }

    function debug($args) {
        switch ($args[0]) {
            case 'on':
                update_option('fifu_debug', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_debug', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // automatic

    function content($args, $assoc_args) {
        if (!empty($assoc_args['skip'])) {
            update_option('fifu_skip', $args[0], 'no');
            return;
        }
        if (!empty($assoc_args['cpt'])) {
            update_option('fifu_html_cpt', $args[0], 'no');
            return;
        }
        if (!empty($assoc_args['overwrite'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_ovw_first', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_ovw_first', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        switch ($args[0]) {
            case 'on':
                update_option('fifu_get_first', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_get_first', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // featured image

    function image($args, $assoc_args) {
        if (!empty($assoc_args['pcontent-add'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_pcontent_add', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_pcontent_add', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['pcontent-remove'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_pcontent_remove', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_pcontent_remove', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['pcontent-types'])) {
            update_option('fifu_pcontent_types', $args[0], 'no');
            return;
        }
        if (!empty($assoc_args['hide'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_hide', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_hide', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['hide-types'])) {
            update_option('fifu_hide_type', $args[0], 'no');
            return;
        }
        if (!empty($assoc_args['hide-formats'])) {
            update_option('fifu_hide_format', $args[0], 'no');
            return;
        }
        if (!empty($assoc_args['default'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_enable_default_url', 'toggleon', 'no'); // toggle
                    $default_url = get_option('fifu_default_url');
                    if (!$default_url)
                        fifu_db_delete_default_url();
                    elseif (fifu_is_on('fifu_fake')) {
                        if (!wp_get_attachment_url(get_option('fifu_default_attach_id'))) {
                            $att_id = fifu_db_create_attachment($default_url);
                            update_option('fifu_default_attach_id', $att_id);
                            fifu_db_set_default_url();
                        } else
                            fifu_db_update_default_url($default_url);
                    }
                    break;
                case 'off':
                    update_option('fifu_enable_default_url', 'toggleoff', 'no'); // toggle
                    fifu_db_delete_default_url();
                    break;
            }
            return;
        }
        if (!empty($assoc_args['default-url'])) {
            update_option('fifu_default_url', $args[0], 'no');
            if (fifu_is_off('fifu_enable_default_url'))
                fifu_db_delete_default_url();
            elseif (!$args[0])
                fifu_db_delete_default_url();
            return;
        }
        if (!empty($assoc_args['default-types'])) {
            update_option('fifu_default_cpt', $args[0], 'no');
            return;
        }
    }

    // metadata

    function metadata($args) {
        switch ($args[0]) {
            case 'on':
                update_option('fifu_fake_stop', false, 'no');
                fifu_enable_fake();
                update_option('fifu_fake', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_fake_stop', true, 'no');
                update_option('fifu_fake', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function clean() {
        fifu_db_enable_clean();
        update_option('fifu_data_clean', 'toggleoff', 'no');
    }

    function sizes() {
        
    }

    // performance

    function cdn($args, $assoc_args) {
        if (!empty($assoc_args['content'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_cdn_content', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_cdn_content', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        switch ($args[0]) {
            case 'on':
                update_option('fifu_photon', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_photon', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // woocommerce

    function woo($args, $assoc_args) {
        if (!empty($assoc_args['lightbox'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_wc_lbox', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_wc_lbox', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['zoom'])) {
            switch ($args[0]) {
                case 'on':
                    update_option('fifu_wc_zoom', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_wc_zoom', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
    }

}

WP_CLI::add_command('fifu', 'fifu_cli');

add_action('wp_insert_post', function ($post_id, $post, $update) {
    fifu_update_fake_attach_id($post->ID);
}, 10, 3);

