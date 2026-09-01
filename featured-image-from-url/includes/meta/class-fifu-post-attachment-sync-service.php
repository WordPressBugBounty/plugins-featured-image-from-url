<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('Fifu_Attachment_Update_Service', false)) {
    require_once __DIR__ . '/class-fifu-attachment-update-service.php';
}
if (!class_exists('Fifu_Attachment_Factory', false)) {
    require_once __DIR__ . '/class-fifu-attachment-factory.php';
}
/**
 * Service to keep FIFU fake attachments in sync with WordPress posts.
 */
class Fifu_Post_Attachment_Sync_Service
{
    private static ?Fifu_Local_Media_Cleanup $cleanup = null;

    private static function is_attachment_owned_by_post(
        int $attachment_id,
        int $post_id
    ): bool {
        if (
            $attachment_id <= 0
            || $post_id <= 0
        ) {
            return false;
        }

        if (
            (int) get_post_field(
                'post_parent',
                $attachment_id
            ) !== $post_id
        ) {
            return false;
        }

        $post_name =
            (string) get_post_field(
                'post_name',
                $attachment_id
            );

        return strpos(
            $post_name,
            'fifu-category-'
        ) !== 0;
    }

    private static function is_category_attachment_owned_by_term(
        int $attachment_id,
        int $term_id
    ): bool {
        if (
            $attachment_id <= 0
            || $term_id <= 0
        ) {
            return false;
        }

        if (
            (int) get_post_field(
                'post_parent',
                $attachment_id
            ) !== $term_id
        ) {
            return false;
        }

        return (string) get_post_field(
            'post_name',
            $attachment_id
        ) === 'fifu-category-' . $term_id;
    }

    private static function persist_attachment_meta(int $attachment_id, string $url, ?string $alt = null): void
    {
        if ($attachment_id <= 0) {
            return;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            return;
        }

        update_post_meta($attachment_id, '_wp_attached_file', $url);

        $alt = $alt === null ? '' : trim((string) $alt);
        if ($alt !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        } else {
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
        }
    }

    /**
     * Sync the FIFU "fake" featured attachment for a given post ID.
     *
     * Responsibility: mirror the behavior of admin/db.php::update_fake_attach_id().
     *
     * @param int $post_id
     * @param bool $force Whether to bypass generic post-save suppression for explicit FIFU operations.
     * @return void
     */
    public static function sync_featured_attachment(int $post_id, bool $force = false): void
    {
        global $wpdb;

        $cleanup = self::get_cleanup();
        $attachment_repo = self::get_attachment_repository();
        $default_attach_id = (int) get_option('fifu_default_attach_id');
        $att_id = (int) get_post_thumbnail_id($post_id);

        /*
         * WordPress/Gutenberg may leave _thumbnail_id pointing to an
         * attachment that no longer exists.
         *
         * get_post_thumbnail_id() still exposes that numeric ID, but
         * Gutenberg cannot retrieve its media entity and displays:
         *
         * "Could not retrieve the featured image data."
         *
         * Normalize that broken state before deciding whether FIFU,
         * Default Featured Image, or a local WordPress attachment should
         * own the featured-media slot.
         */
        if (
            $att_id > 0
            && !get_post($att_id)
        ) {
            delete_post_meta(
                $post_id,
                '_thumbnail_id'
            );

            $att_id = 0;
        }

        $url = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, false);

        $has_fifu_attachment = $att_id
            ? ($attachment_repo->is_fifu_attachment($att_id) && $default_attach_id !== $att_id)
            : false;

        if (
            $has_fifu_attachment
            && !self::is_attachment_owned_by_post(
                $att_id,
                $post_id
            )
        ) {
            /*
             * Another post owns this FIFU attachment.
             *
             * The current post may reference it because a plugin,
             * duplication workflow, importer, migration or custom code
             * copied _thumbnail_id.
             *
             * Never mutate or delete the foreign attachment.
             * Remove only this post's reference and continue as though
             * no featured attachment were assigned.
             */
            delete_post_thumbnail($post_id);

            $att_id = 0;
            $has_fifu_attachment = false;
        }

        $default_url = (string) get_option('fifu_default_url');
        $should_use_default_fallback = !$url || (
            !$force
            && $url === $default_url
        );

        if ($should_use_default_fallback) {
            if ($has_fifu_attachment && $att_id) {
                /*
                 * Remove the obsolete FIFU featured attachment before applying
                 * the default. The default-image service intentionally refuses
                 * to replace another active thumbnail.
                 */
                wp_delete_attachment($att_id);
                delete_post_thumbnail($post_id);
            } else {
                $attachment_ids = self::normalize_attachment_ids(
                    $attachment_repo->get_orphan_attachments_for_post($post_id)
                );

                if ($attachment_ids) {
                    $cleanup->delete_attachment_file_and_alt(
                        $attachment_ids
                    );

                    $cleanup->delete_fifu_attachments(
                        $attachment_ids
                    );
                }
            }

            /*
             * Delegate Default Featured Image assignment to its canonical
             * service.
             *
             * Besides respecting the feature toggle and configured CPT, this
             * ensures a FIFU-owned default attachment is initialized before use
             * and preserves a genuine local WordPress thumbnail.
             */
            Fifu_Default_Image_Service::add_default_image_to_post(
                $post_id
            );
        } else {
            $alt = self::resolve_featured_attachment_alt((int) $post_id, $url);
            $current_url = $att_id ? Fifu_Attachment_Update_Service::get_attachment_remote_url((int) $att_id) : '';
            $is_default_attachment = $att_id === $default_attach_id;
            $same_logical_media = self::is_same_logical_featured_media($current_url, $url);

            if ($has_fifu_attachment && $att_id && !$is_default_attachment) {
                if ($same_logical_media) {
                    if (
                        !self::
                            featured_attachment_state_matches(
                                (int) $att_id,
                                (string) $url,
                                $alt
                            )
                    ) {
                        Fifu_Attachment_Update_Service::
                            initialize_remote_attachment(
                                (int) $att_id,
                                $url,
                                $alt,
                                null,
                                null,
                                true
                            );

                        self::persist_attachment_meta(
                            (int) $att_id,
                            $url,
                            $alt
                        );
                    }
                } else {
                    $new_att_id = $attachment_repo->find_attachment_id($post_id, $url, false);
                    if ($new_att_id) {
                        Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, null, null, true);
                        self::persist_attachment_meta((int) $new_att_id, $url, $alt);
                    } else {
                        $factory = new Fifu_Attachment_Factory();
                        $new_att_id = $factory->create_attachment_for_url($url, $alt, $post_id);
                        if ($new_att_id) {
                            Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, null, null, true);
                            self::persist_attachment_meta((int) $new_att_id, $url, $alt);
                        }
                    }

                    if ($new_att_id) {
                        update_post_meta($post_id, '_thumbnail_id', (int) $new_att_id);
                        if ($att_id !== (int) $new_att_id && $att_id !== $default_attach_id && Fifu_Attachment_Update_Service::is_fifu_owned((int) $att_id)) {
                            $cleanup->delete_attachments_and_meta([$att_id]);
                        }
                    }
                }
            } else {
                $new_att_id = 0;
                $new_att_id = $attachment_repo->find_attachment_id($post_id, $url, false);
                if ($new_att_id) {
                    update_post_meta($post_id, '_thumbnail_id', $new_att_id);
                    Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, null, null, true);
                    self::persist_attachment_meta((int) $new_att_id, $url, $alt);
                } else {
                    $factory = new Fifu_Attachment_Factory();
                    $new_att_id = $factory->create_attachment_for_url($url, $alt, $post_id);
                    if ($new_att_id) {
                        update_post_meta($post_id, '_thumbnail_id', $new_att_id);
                        Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, null, null, true);
                        self::persist_attachment_meta((int) $new_att_id, $url, $alt);
                    }
                }

                $protected_attachment_ids = [];
                if (!empty($new_att_id)) {
                    $protected_attachment_ids[] = (int) $new_att_id;
                }

                $current_thumbnail_id = (int) get_post_meta($post_id, '_thumbnail_id', true);
                if ($current_thumbnail_id > 0) {
                    $protected_attachment_ids[] = $current_thumbnail_id;
                }

                $protected_attachment_ids = array_values(array_unique(array_filter(
                    array_map('intval', $protected_attachment_ids),
                    static fn(int $attachment_id): bool => $attachment_id > 0
                )));

                $attachment_ids = self::normalize_attachment_ids(
                    $attachment_repo->get_orphan_attachments_for_post($post_id)
                );

                if ($protected_attachment_ids) {
                    $attachment_ids = array_values(array_filter(
                        $attachment_ids,
                        static fn(int $attachment_id): bool => !in_array($attachment_id, $protected_attachment_ids, true)
                    ));
                }

                if ($attachment_ids) {
                    $cleanup->delete_attachments_and_meta($attachment_ids);
                }
            }
        }

        if (!Fifu_Speedup_Url_Service::is_speedup_url($url) && get_post_meta($post_id, 'bkp_thumbnail_id', true)) {
            delete_post_meta($post_id, 'bkp_thumbnail_id');
        }
    }

    /**
     * Sync the FIFU featured attachment for a post.
     *
     * @param mixed $post_id Post ID.
     * @return void
     */
    public static function sync_attachments(int $post_id): void
    {
        self::sync_featured_attachment($post_id);
    }

    private static function resolve_featured_attachment_alt(int $post_id, ?string $resolved_url): ?string
    {
        $resolvedUrl = self::normalize_attachment_url($resolved_url);
        if ($resolvedUrl === null) {
            return Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id);
        }

        return Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id);
    }

    private static function normalize_attachment_url(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(htmlspecialchars_decode($value));
        return $normalized === '' ? null : $normalized;
    }

    private static function is_same_logical_featured_media(?string $current_url, ?string $new_url): bool
    {
        $current_url = self::normalize_attachment_url($current_url);
        $new_url = self::normalize_attachment_url($new_url);

        if ($current_url === null || $new_url === null) {
            return false;
        }

        if ($current_url === $new_url) {
            return true;
        }

        return Fifu_Attachment_Update_Service::is_youtube_thumbnail_quality_fallback($current_url, $new_url);
    }

    private static function featured_attachment_state_matches(
        int $attachmentId,
        string $url,
        ?string $alt
    ): bool {
        $currentUrl =
            self::normalize_attachment_url(
                Fifu_Attachment_Update_Service::
                    get_attachment_remote_url(
                        $attachmentId
                    )
            );

        $desiredUrl =
            self::normalize_attachment_url(
                $url
            );

        $currentAlt =
            trim(
                (string)
                get_post_meta(
                    $attachmentId,
                    '_wp_attachment_image_alt',
                    true
                )
            );

        return $currentUrl !== null
            && $currentUrl === $desiredUrl
            && $currentAlt
                === trim(
                    (string) $alt
                );
    }

    /**
     * Delete the FIFU-owned featured attachment before its post is deleted.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public static function handle_post_deleted($post_id): void
    {
        $post_id = is_numeric($post_id) ? (int) $post_id : 0;

        if ($post_id <= 0) {
            return;
        }

        $attachmentId = (int) get_post_thumbnail_id($post_id);

        if ($attachmentId <= 0) {
            return;
        }

        $defaultAttachmentId = 0;

        if (Fifu_Options_Utils::is_on('fifu_enable_default_url')) {
            $defaultAttachmentId = (int) get_option(
                'fifu_default_attach_id'
            );
        }

        if (
            $defaultAttachmentId > 0
            && $attachmentId === $defaultAttachmentId
        ) {
            return;
        }

        $attachmentRepository =
            self::get_attachment_repository();

        if (
            !$attachmentRepository->is_fifu_attachment(
                $attachmentId
            )
            || !self::is_attachment_owned_by_post(
                $attachmentId,
                $post_id
            )
        ) {
            return;
        }

        $cleanup = self::get_cleanup();

        $cleanup->delete_attachment_file_and_alt(
            [$attachmentId]
        );

        $cleanup->delete_fifu_attachments(
            [$attachmentId]
        );
    }

    /**
     * Sync the FIFU attachment used by a category term.
     *
     * Mirrors the behavior of admin/db.php::ctgr_update_fake_attach_id().
     *
     * @param int $term_id
     * @return void
     */
    public static function sync_category_attachment(int $term_id): void
    {
$cleanup = self::get_cleanup();
        $attachment_repo = self::get_attachment_repository();
        global $wpdb;

        $term_meta = get_term_meta($term_id, 'thumbnail_id');
        $att_id = $term_meta ? (int) $term_meta[0] : null;
        $has_fifu_attachment = $att_id ? $attachment_repo->is_fifu_attachment($att_id) : false;

        $url = null;
        $dimension_key_type = 'image';
        $url = Fifu_Term_Image_Url_Read_Service::get_image_url((int) $term_id);
$is_wvs = Fifu_Woocommerce_Context::is_woo_variation_swatches_taxonomy($term_id);

        if (
            $has_fifu_attachment
            && $att_id
            && !self::is_category_attachment_owned_by_term(
                (int) $att_id,
                (int) $term_id
            )
        ) {
            /*
             * The current term references a FIFU category attachment
             * owned by another term.
             *
             * Never delete or mutate the foreign attachment. Remove only
             * this term's references and continue synchronization as
             * though no category attachment were assigned.
             */
            update_term_meta(
                $term_id,
                'thumbnail_id',
                0
            );

            if ($is_wvs) {
                delete_term_meta(
                    $term_id,
                    'product_attribute_image'
                );
            }

            $att_id = null;
            $has_fifu_attachment = false;
        }

        if (!$url) {
if ($has_fifu_attachment && $att_id && Fifu_Attachment_Update_Service::is_fifu_owned((int) $att_id)) {
                wp_delete_attachment($att_id);
                update_term_meta($term_id, 'thumbnail_id', 0);
                if ($is_wvs) {
                    delete_term_meta($term_id, 'product_attribute_image');
                }
            }
            return;
        }

        $alt = Fifu_Term_Image_Alt_Read_Service::get_image_alt((int) $term_id);
        $current_url = $att_id ? Fifu_Attachment_Update_Service::get_attachment_remote_url((int) $att_id) : '';
        $has_attachment_metadata = $att_id ? metadata_exists('post', (int) $att_id, '_wp_attachment_metadata') : false;
        $dimensions = self::get_category_attachment_dimensions((int) $term_id, (string) $url, $dimension_key_type);
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        if ($has_fifu_attachment && $att_id && Fifu_Attachment_Update_Service::is_fifu_owned((int) $att_id)) {
            if ($current_url === '' || $current_url === $url) {
                Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $att_id, $url, $alt, $width, $height, true);
            } elseif (!$has_attachment_metadata) {
                Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $att_id, $url, $alt, $width, $height, true);
            } else {
                $new_att_id = $attachment_repo->find_attachment_id($term_id, $url, true);
                if ($new_att_id) {
                    Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, $width, $height, true);
                } else {
                    $factory = new Fifu_Attachment_Factory();
                    $new_att_id = $factory->create_category_attachment_for_url($url, $alt, $term_id);
                    if ($new_att_id) {
                        Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, $width, $height, true);
                    }
                }

                if ($new_att_id) {
                    update_term_meta($term_id, 'thumbnail_id', $new_att_id);
                    if ($is_wvs) {
                        update_term_meta($term_id, 'product_attribute_image', $new_att_id);
                    }
                    if ($att_id !== (int) $new_att_id) {
                        $cleanup->delete_attachment_file_and_alt([(int) $att_id]);
                        $cleanup->delete_attachments_and_meta([$att_id]);
                    }
                }
            }
        } else {
            $new_att_id = $attachment_repo->find_attachment_id($term_id, $url, true);
            if ($new_att_id) {
                update_term_meta($term_id, 'thumbnail_id', $new_att_id);
                if ($is_wvs) {
                    update_term_meta($term_id, 'product_attribute_image', $new_att_id);
                }
                Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, $width, $height, true);
            } else {
                $factory = new Fifu_Attachment_Factory();
                $new_att_id = $factory->create_category_attachment_for_url($url, $alt, $term_id);
                if ($new_att_id) {
                    Fifu_Attachment_Update_Service::initialize_remote_attachment((int) $new_att_id, $url, $alt, $width, $height, true);
                    update_term_meta($term_id, 'thumbnail_id', $new_att_id);
                    if ($is_wvs) {
                        update_term_meta($term_id, 'product_attribute_image', $new_att_id);
                    }
                }
            }

            $orphan_ids = self::normalize_attachment_ids(
                $attachment_repo->get_orphan_attachments_for_term($term_id)
            );
            if ($orphan_ids) {
                $orphan_ids = array_values(array_filter(
                    $orphan_ids,
                    static fn(int $attachment_id): bool => Fifu_Attachment_Update_Service::is_fifu_owned($attachment_id)
                ));
                if ($orphan_ids) {
                    $cleanup->delete_attachment_file_and_alt($orphan_ids);
                    $cleanup->delete_attachments_and_meta($orphan_ids);
                }
            }
        }

if (!Fifu_Speedup_Url_Service::is_speedup_url($url) && get_term_meta($term_id, 'bkp_thumbnail_id', true)) {
            delete_term_meta($term_id, 'bkp_thumbnail_id');
        }
    }

    /**
     * Resolve DB2 dimensions for the category attachment URL when available.
     *
     * @param int $term_id
     * @param string $url
     * @return array{width:int|null,height:int|null}
     */
    private static function get_category_attachment_dimensions(int $term_id, string $url, string $preferred_key_type = 'image'): array
    {
        $empty = [
            'width' => null,
            'height' => null,
        ];

        if ($term_id <= 0 || trim($url) === '' || !function_exists('fifu_db2_manager')) {
            return $empty;
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager || !method_exists($manager, 'getTermMapping')) {
            return $empty;
        }

        $target_url = self::normalize_category_dimension_url($url);

        $key_types = ['image'];

        foreach ($key_types as $key_type) {
            $mapping = $manager->getTermMapping($term_id, $key_type);
            if (!is_array($mapping)) {
                continue;
            }

            $mapping_url = self::normalize_category_dimension_url((string) ($mapping['url'] ?? ''));
            if ($mapping_url !== '' && $target_url !== '' && $mapping_url !== $target_url) {
                continue;
            }

            $width = isset($mapping['width']) ? (int) $mapping['width'] : 0;
            $height = isset($mapping['height']) ? (int) $mapping['height'] : 0;

            if ($width > 0 && $height > 0) {
                return [
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        return $empty;
    }

    /**
     * Normalize remote URLs for DB2 dimension matching.
     *
     * @param string $url
     * @return string
     */
    private static function normalize_category_dimension_url(string $url): string
    {
        return trim(htmlspecialchars_decode($url));
    }

    /**
     * Build the VALUES tuple for a category attachment insert.
     *
     * @param string      $url
     * @param string|null $alt
     * @param int    $term_id
     * @return string
     */
    private static function build_category_attachment_tuple(string $url, ?string $alt, int $term_id): string
    {
        global $wpdb;
        $alt_value = trim($alt ?? '');
        return $wpdb->prepare(
            "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s, %s)",
            self::get_author_id(),
            '',
            $alt_value,
            $alt_value,
            'image/jpeg',
            'attachment',
            'inherit',
            $term_id,
            '',
            '',
            '',
            $url,
            'fifu-category-' . $term_id
        );
    }

    private static function get_cleanup(): Fifu_Local_Media_Cleanup
    {
        if (self::$cleanup === null) {
            self::$cleanup = new Fifu_Local_Media_Cleanup();
        }

        return self::$cleanup;
    }

    private static function get_attachment_repository(): Fifu_Attachment_Repository
    {
        return new Fifu_Attachment_Repository();
    }

    private static function normalize_attachment_ids($value): array
    {
        if (!$value) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function get_author_id(): int
    {
        return (int) Fifu_Options_Utils::get_author();
    }

    private static function insert_category_attachment_rows(string $values_sql): void
    {
        if (!$values_sql) {
            return;
        }
        global $wpdb;
        $wpdb->query("
            INSERT INTO {$wpdb->posts}
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name)
            VALUES {$values_sql}
        ");
    }

}
