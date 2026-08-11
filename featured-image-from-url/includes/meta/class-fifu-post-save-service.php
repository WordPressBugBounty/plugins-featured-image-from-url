<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Handles editor-based saving for featured media elements.
 */
final class Fifu_Post_Save_Service
{
    private static function has_non_empty_media_url($value): bool
    {
        if ($value === null || $value === false || is_array($value)) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    private static function has_fifu_request_properties(): bool
    {
        if (class_exists('Fifu_Plugin_Detector') && Fifu_Plugin_Detector::is_ol_scrapes_active()) {
            return true;
        }

        foreach ($_POST as $key => $value) {
            if (strpos((string) $key, 'fifu') !== false) {
                return true;
            }
        }

        return false;
    }
    /**
     * Entry point for editor-based post saving.
     * It will replace the legacy fifu_save() callback.
     *
     * @param int $postId
     * @param array $request
     * @param bool $ignore
     */
    public static function save_from_editor(
        int $postId,
        array $request,
        bool $ignore = false
    ): void {
        $thrownException = null;

        try {
            $fromDeferredPostSave =
                !empty(
                    $request[
                        'fifu_post_save_pending'
                    ]
                );

            $featuredImageChanged =
                false;

            $hasFifuInputUrl =
                array_key_exists(
                    'fifu_input_url',
                    $request
                );

            $fifuInputUrl =
                $hasFifuInputUrl
                    ? trim(
                        (string)
                        $request[
                            'fifu_input_url'
                        ]
                    )
                    : '';

            if (
                $hasFifuInputUrl
                && $fifuInputUrl === ''
                && Fifu_Options_Utils::
                    is_on(
                        'fifu_enable_default_url'
                    )
                && Fifu_Post_Type_Utils::
                    is_valid_default_cpt(
                        $postId
                    )
            ) {
                delete_post_meta(
                    $postId,
                    'fifu_image_url'
                );

                delete_post_meta(
                    $postId,
                    'fifu_image_alt'
                );

                Fifu_Default_Image_Service::
                    add_default_image_to_post(
                        $postId
                    );

                return;
            }

            if (
                $hasFifuInputUrl
                && $fifuInputUrl !== ''
            ) {
                $url =
                    esc_url_raw(
                        rtrim(
                            $fifuInputUrl
                        )
                    );

                if (
                    self::
                        featured_media_needs_write(
                            $postId,
                            $url,
                            'url'
                        )
                ) {
                    fifu_update_or_delete(
                        $postId,
                        'fifu_image_url',
                        $url
                    );

                    $featuredImageChanged =
                        true;
                }
            }

            if (
                $fromDeferredPostSave
                || $featuredImageChanged
            ) {
                self::sync_attachments(
                    $postId
                );
            }
        } catch (\Throwable $throwable) {
            $thrownException =
                $throwable;

            throw $throwable;
        } finally {
        }
    }



    /**
     * @return array{known:bool,value:string}
     */
    private static function get_db2_featured_state(
        int $postId,
        string $field
    ): array {
        if (
            !function_exists(
                'fifu_db2_manager'
            )
            || !class_exists(
                'Fifu_Db2_Manager',
                false
            )
        ) {
            return [
                'known' => false,
                'value' => '',
            ];
        }

        try {
            $manager =
                fifu_db2_manager();

            $method =
                $field === 'alt'
                    ? 'getPostAltMapping'
                    : 'getPostMapping';

            if (
                !$manager
                    instanceof
                    Fifu_Db2_Manager
                || !method_exists(
                    $manager,
                    $method
                )
            ) {
                return [
                    'known' => false,
                    'value' => '',
                ];
            }

            $mapping =
                $manager->{$method}(
                    $postId,
                    'image',
                    0
                );

            if (!is_array($mapping)) {
                return [
                    'known' => true,
                    'value' => '',
                ];
            }

            $value =
                $field === 'alt'
                    ? (
                        $mapping['alt']
                        ?? $mapping['value']
                        ?? ''
                    )
                    : (
                        $mapping['url']
                        ?? ''
                    );

            return [
                'known' => true,
                'value' =>
                    self::
                    normalize_featured_media_value(
                        (string) $value,
                        $field
                    ),
            ];
        } catch (\Throwable $throwable) {
            return [
                'known' => false,
                'value' => '',
            ];
        }
    }

    private static function normalize_featured_media_value(
        string $value,
        string $field
    ): string {
        if ($field === 'alt') {
            return trim(
                wp_strip_all_tags(
                    $value
                )
            );
        }

        return esc_url_raw(
            rtrim(
                $value
            )
        );
    }

    private static function featured_media_needs_write(
        int $postId,
        string $value,
        string $field
    ): bool {
        $legacyKey =
            $field === 'alt'
                ? 'fifu_image_alt'
                : 'fifu_image_url';

        $state =
            self::get_db2_featured_state(
                $postId,
                $field
            );

        return !$state['known']
            || $state['value']
                !== self::
                    normalize_featured_media_value(
                        $value,
                        $field
                    )
            || metadata_exists(
                'post',
                $postId,
                $legacyKey
            );
    }

    private static function normalize_pipe_list(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $entries = array_values(
            array_filter(
                array_map('trim', explode('|', $value)),
                static fn(string $entry): bool => $entry !== ''
            )
        );

        if (!$entries) {
            return null;
        }

        return implode('|', $entries);
    }

    private static function cleanup_legacy_main_image_alt_after_db2_sync(int $postId): void
    {
        $legacyAlt = trim((string) get_post_meta($postId, 'fifu_image_alt', true));
        if ($legacyAlt === '') {
            return;
        }

        $db2MainAlt = self::get_db2_main_image_alt($postId);
        if ($db2MainAlt !== null && trim($db2MainAlt) === $legacyAlt) {
            delete_post_meta($postId, 'fifu_image_alt');
        }
    }

    private static function get_db2_main_image_alt(int $postId): ?string
    {
        if (!function_exists('fifu_db2_manager') || !class_exists('Fifu_Db2_Manager', false)) {
            return null;
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager || !method_exists($manager, 'getPostAltMapping')) {
            return null;
        }

        $mapping = $manager->getPostAltMapping($postId, 'image', 0);
        if (!is_array($mapping)) {
            return null;
        }

        $alt = trim((string) ($mapping['alt'] ?? $mapping['value'] ?? ''));
        return $alt === '' ? null : $alt;
    }

    /**
     * @param array<string,mixed> $request
     * @return array{hasPayload:bool,cleanupLength:int,items:array<int,array{url:?string,alt:?string,ifm:?string}>}
     */
    /**
     * Syncs featured attachment, gallery attachments and theme property images.
     *
     * @param int $postId
     */
    private static function sync_attachments(int $postId): void
    {
        Fifu_Post_Attachment_Sync_Service::sync_attachments($postId);
    }

    /**
     * Handles the hooked save_post event, replacing the legacy fifu_save_properties logic.
     *
     * TODO: move all guard clauses (autosave, post type checks, block detection),
     * integrations (WCFM, Toolset, AliPlugin, external forms), metadata updates,
     * dimension updates, video helpers, and request flags into this method.
     *
     * @param int $postId
     * @param \WP_Post $post
     * @param bool $update
     */
    public static function on_save_post(int $postId, \WP_Post $post, bool $update): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post_type = get_post_type($postId);
        if (!$_POST || $post_type == 'nav_menu_item' || $post_type == 'revision') {
            return;
        }

        $action = $_POST['action'] ?? '';
        if ($action == 'woocommerce_do_ajax_product_import') {
            return;
        }

        if (isset($_POST['dokan_edit_product_nonce'])) {
            return;
        }

        $post_content = get_post_field('post_content', $postId);
        $request_content = '';
        if (isset($_POST['content']) && !is_array($_POST['content'])) {
            $request_content = wp_unslash((string) $_POST['content']);
        } elseif (isset($_POST['post_content']) && !is_array($_POST['post_content'])) {
            $request_content = wp_unslash((string) $_POST['post_content']);
        }

        if (
            has_block('fifu/image', $post_content)
            || ($request_content !== '' && has_block('fifu/image', $request_content))
        ) {
            return;
        }

        if ($action == 'wcfm_ajax_controller') {
            if (Fifu_Plugin_Detector::is_wcfm_active() && isset($_POST['wcfm_products_manage_form'])) {
                $image_url = esc_url_raw(
                    rtrim(
                        Fifu_Plugin_Detector::get_wcfm_image_url_from_content($_POST['wcfm_products_manage_form'])
                    )
                );
                Fifu_Developer_Media_Service::set_image(
                    (int) $postId,
                    (string) $image_url
                );
                return;
            }
        }

        $post_status = $_POST['post_status'] ?? '';
        if ($post_status == 'dp-rewrite-republish') {
            Fifu_Developer_Media_Service::set_image(
                (int) $postId,
                ''
            );
            delete_post_meta($postId, 'fifu_image_url');
            delete_post_meta($postId, 'fifu_image_alt');
            return;
        }

        if (!self::has_fifu_request_properties()) {
            return;
        }
        $requiresDeferredMediaSync = false;

        $has_fifu_input_url = array_key_exists('fifu_input_url', $_POST);
        $raw_fifu_input_url = $has_fifu_input_url ? wp_unslash((string) $_POST['fifu_input_url']) : '';
        $fifu_input_url = trim($raw_fifu_input_url);
        $is_empty_fifu_input_url = $has_fifu_input_url && $fifu_input_url === '';

        if ($is_empty_fifu_input_url) {
            if (
                Fifu_Options_Utils::is_on('fifu_enable_default_url')
                && Fifu_Post_Type_Utils::is_valid_default_cpt($postId)
            ) {
                delete_post_meta($postId, 'fifu_image_url');
                delete_post_meta($postId, 'fifu_image_alt');
                Fifu_Default_Image_Service::add_default_image_to_post($postId);
                return;
            }
        }

        if (Fifu_Post_Meta_Utils::has_local_featured_image($postId)) {
            $auto_set = false;
        } else {
            if ($is_empty_fifu_input_url) {
                $auto_set = true;
            } else {
                if (has_post_thumbnail($postId)) {
                    if (Fifu_Post_Main_Image_Resolver::get_main_image_url($postId, false) != $fifu_input_url) {
                        $auto_set = true;
                    } else {
                        $auto_set = Fifu_Options_Utils::is_on('fifu_ovw_first');
                    }
                } else {
                    $auto_set = false;
                }
            }
        }

        $url = null;
        if ($has_fifu_input_url) {
            $url = esc_url_raw(rtrim($raw_fifu_input_url));
            if ($auto_set) {
                $selectedMedia = Fifu_Content_Url_Scanner::find_first_media(
                    $postId,
                    null,
                    'image'
                );

                if (
                    is_array($selectedMedia)
                    && ($selectedMedia['type'] ?? '') === 'image'
                    && Fifu_Options_Utils::is_on('fifu_get_first')
                    && (!$url || Fifu_Options_Utils::is_on('fifu_ovw_first'))
                    && !Fifu_Post_Meta_Utils::has_local_featured_image($postId)
                    && Fifu_Post_Type_Utils::is_valid_cpt($postId)
                ) {
                    $url = (string) ($selectedMedia['url'] ?? '');
                }
            }

            if (self::featured_media_needs_write($postId, (string) $url, 'url')) {
                fifu_update_or_delete($postId, 'fifu_image_url', $url);
                $requiresDeferredMediaSync = true;
            }
        }

        if (Fifu_Plugin_Detector::is_toolset_forms_active() && isset($_POST['wpcf-fifu_image_url'])) {
            $url = esc_url_raw(rtrim($_POST['wpcf-fifu_image_url']));
            if ($url && self::featured_media_needs_write($postId, $url, 'url')) {
                fifu_update_or_delete($postId, 'fifu_image_url', $url);
                $requiresDeferredMediaSync = true;
            }
        }

        if (Fifu_Plugin_Detector::is_aliplugin_active() && isset($_POST['imageUrl'])) {
            $url = esc_url_raw(rtrim($_POST['imageUrl']));
            if ($url) {
                fifu_update_or_delete($postId, 'fifu_image_url', $url);
            }
        }

        if (!$url && isset($_POST['fifu_image_url']) && !is_array($_POST['fifu_image_url'])) {
            $url = esc_url_raw(rtrim($_POST['fifu_image_url']));
            if ($url && self::featured_media_needs_write($postId, $url, 'url')) {
                fifu_update_or_delete($postId, 'fifu_image_url', $url);
                $requiresDeferredMediaSync = true;
            }
        }

        if (
            isset(
                $_POST[
                    'fifu_input_alt'
                ]
            )
        ) {
            $alt =
                esc_html(
                    wp_strip_all_tags(
                        $_POST[
                            'fifu_input_alt'
                        ]
                    )
                );

            if (
                self::featured_media_needs_write(
                    $postId,
                    $alt,
                    'alt'
                )
            ) {
                fifu_update_or_delete_value(
                    $postId,
                    'fifu_image_alt',
                    $alt
                );

                self::
                    cleanup_legacy_main_image_alt_after_db2_sync(
                        $postId
                    );

                $requiresDeferredMediaSync =
                    true;
            }
        }

        if ($requiresDeferredMediaSync) {
            $_REQUEST[
                'fifu_post_save_pending'
            ] = true;

            $_REQUEST[
                'fifu_post_save_ignore'
            ] = !$auto_set;

            $_REQUEST[
                'fifu_post_save_id'
            ] = $postId;
        } else {
            unset(
                $_REQUEST[
                    'fifu_post_save_pending'
                ],
                $_REQUEST[
                    'fifu_post_save_ignore'
                ],
                $_REQUEST[
                    'fifu_post_save_id'
                ]
            );
        }

        $width = Fifu_Meta_Box_Dimensions_Reader::get_main_image_width($_POST);
        $height = Fifu_Meta_Box_Dimensions_Reader::get_main_image_height($_POST);
        $att_id = get_post_thumbnail_id($postId);
        if ($att_id && $width && $height) {
            Fifu_Attachment_Dimensions_Service::update_dimensions($att_id, (int) $width, (int) $height);
        }

    }
}
