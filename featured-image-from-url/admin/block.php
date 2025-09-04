<?php

function fifu_register_blocks() {
    // Prevent block registration in Customizer
    if (is_admin() && isset($GLOBALS['wp_customize']) && $GLOBALS['wp_customize'] instanceof WP_Customize_Manager) {
        return;
    }

    // Prevent block registration on the widgets screen
    if (is_admin() && basename($_SERVER['PHP_SELF']) === 'widgets.php') {
        return;
    }

    $block_strings = fifu_get_strings_block();
    register_block_type(
            FIFU_PLUGIN_DIR . 'blocks/fifu-image',
            array(
                'title' => $block_strings['title']['image'](),
                'description' => $block_strings['description']['image'](),
            // ...other args can be added here if needed...
            )
    );
    // Localize block strings for JS
    $strings = array();
    foreach ($block_strings as $group => $items) {
        foreach ($items as $key => $fn) {
            $strings[$group][$key] = $fn();
        }
    }
    wp_localize_script(
            'fifu-image-editor-script', // handle used in block.json or when enqueuing
            'fifuBlockStrings',
            $strings
    );
}

add_action('init', 'fifu_register_blocks');

function fifu_block_after_rest_insert($post, $request, $creating) {
    $post_id = $post->ID;
    $post_content = $post->post_content;
    $image_url = esc_url_raw(rtrim(get_post_meta($post_id, 'fifu_image_url', true)));
    $image_alt = esc_html(wp_strip_all_tags(get_post_meta($post_id, 'fifu_image_alt', true)));

    if (has_block('fifu/image', $post_content)) {
        fifu_dev_set_image($post_id, $image_url);
        fifu_update_or_delete_value($post_id, 'fifu_image_alt', $image_alt);
    }
}

add_action('rest_after_insert_post', 'fifu_block_after_rest_insert', 10, 3);

function fifu_register_meta() {
    register_post_meta('post', 'fifu_image_url', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
    register_post_meta('post', 'fifu_image_alt', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
}

add_action('init', 'fifu_register_meta');

