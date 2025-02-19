<?php

add_action('vg_sheet_editor/editor/register_columns', 'fifu_sheet_editor_register_columns', 20, 1);

function fifu_sheet_editor_register_columns($editor) {
    $post_types = $editor->args['enabled_post_types'];
    foreach ($post_types as $post_type) {
        if (!post_type_exists($post_type)) {
            return;
        }

        $editor->args['columns']->register_item(
                'fifu_image_url',
                $post_type,
                array(
                    'data_type' => 'meta_data',
                    'column_width' => 170,
                    'title' => 'fifu_image_url',
                    'type' => '',
                    'supports_formulas' => true,
                    'allow_to_hide' => true,
                    'allow_to_rename' => true,
                    'supports_sql_formulas' => true,
                    'save_value_callback' => 'fifu_sheet_editor_save_image_url',
                )
        );

        $editor->args['columns']->register_item(
                'fifu_image_alt',
                $post_type,
                array(
                    'data_type' => 'meta_data',
                    'column_width' => 170,
                    'title' => 'fifu_image_alt',
                    'type' => '',
                    'supports_formulas' => true,
                    'allow_to_hide' => true,
                    'allow_to_rename' => true,
                    'supports_sql_formulas' => true,
                )
        );
    }
}

function fifu_sheet_editor_save_image_url($post_id, $cell_key, $url, $post_type, $cell_args, $spreadsheet_columns) {
    fifu_dev_set_image($post_id, $url);
}

// Remove original action using the singleton instance
add_action('plugins_loaded', function () {
    if (!class_exists('WPSE_Featured_Image_From_Url'))
        return;

    $original_instance = WPSE_Featured_Image_From_Url_Obj();

    remove_action(
            'vg_sheet_editor/editor/register_columns',
            array($original_instance, 'register_columns'),
            10
    );
}, 20);

