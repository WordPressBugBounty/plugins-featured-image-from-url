<?php

declare(strict_types=1);

/**
 * Encapsulates the VG Sheet Editor integration previously in
 * admin/sheet-editor.php.
 */
class Fifu_VG_Sheet_Editor_Integration
{
    /**
     * Register the VG Sheet Editor actions and filters.
     */
    public static function register_hooks(): void
    {
        add_action(
            'vg_sheet_editor/editor/register_columns',
            [self::class, 'register_columns'],
            20,
            1
        );

        add_filter(
            'vg_sheet_editor/columns/blacklisted_columns',
            [self::class, 'blacklist_columns'],
            20,
            2
        );

        add_action(
            'plugins_loaded',
            [self::class, 'detach_legacy_wpse_columns'],
            20
        );
    }

    /**
     * Register only the Free single-featured-image fields.
     *
     * These fields must also be available for WooCommerce products.
     *
     * @param mixed $editor
     */
    public static function register_columns($editor): void
    {
        if (!is_object($editor)) {
            return;
        }

        $args = $editor->args ?? null;
        if (!is_array($args)) {
            return;
        }

        $postTypes = $args['enabled_post_types'] ?? null;
        $columns = $args['columns'] ?? null;
        if (!is_array($postTypes) || !is_object($columns) || !method_exists($columns, 'register_item')) {
            return;
        }

        foreach ($postTypes as $postType) {
            if (!post_type_exists($postType)) {
                return;
            }

            $columns->register_item(
                'fifu_image_url',
                $postType,
                [
                    'data_type' => 'meta_data',
                    'column_width' => 170,
                    'title' => 'fifu_image_url',
                    'type' => '',
                    'supports_formulas' => true,
                    'allow_to_hide' => true,
                    'allow_to_rename' => true,
                    'supports_sql_formulas' => true,
                    'save_value_callback' => [
                        self::class,
                        'save_image_url',
                    ],
                ]
            );

            $columns->register_item(
                'fifu_image_alt',
                $postType,
                [
                    'data_type' => 'meta_data',
                    'column_width' => 170,
                    'title' => 'fifu_image_alt',
                    'type' => '',
                    'supports_formulas' => true,
                    'allow_to_hide' => true,
                    'allow_to_rename' => true,
                    'supports_sql_formulas' => true,
                ]
            );
        }
    }

    /**
     * Prevent internal compatibility fields from appearing.
     */
    public static function blacklist_columns($columns, $postType) {
        if (!is_array($columns)) {
            return $columns;
        }

        $columns[] = 'fifu_search_proxy';

        return $columns;
    }

    /**
     * Persist the featured-image URL submitted from the sheet editor.
     *
     * @param mixed $postId
     * @param mixed $cellKey
     * @param mixed $url
     * @param mixed $postType
     * @param mixed $cellArgs
     * @param mixed $spreadsheetColumns
     */
    public static function save_image_url(
        $postId,
        $cellKey,
        $url,
        $postType,
        $cellArgs,
        $spreadsheetColumns
    ): void {
        $postId = is_numeric($postId)
            ? (int) $postId
            : 0;

        if (
            $postId <= 0
            || ($url !== null && !is_scalar($url))
        ) {
            return;
        }

        Fifu_Developer_Media_Service::set_image(
            $postId,
            (string) ($url ?? '')
        );
    }

    /**
     * Detach the legacy WPSE register_columns hook from the Free plugin.
     */
    public static function detach_legacy_wpse_columns(): void
    {
        if (!class_exists('WPSE_Featured_Image_From_Url')) {
            return;
        }

        $originalInstance = WPSE_Featured_Image_From_Url_Obj();

        remove_action(
            'vg_sheet_editor/editor/register_columns',
            [$originalInstance, 'register_columns'],
            10
        );
    }
}
