<?php

/**
 * Handles updating and deleting FIFU post meta fields.
 */
class FIFU_Post_Meta_Updater {

    /**
     * @var self
     */
    private static $instance;

    /**
     * Removed feature meta keys. Existing data is preserved, but new writes/deletes are ignored.
     *
     * @var string[]
     */
    private const REMOVED_WRITE_META_KEYS = array(
        'fifu_isbn',
        'fifu_asin',
        'fifu_audio_url',
    );

    private const REMOVED_GALLERY_META_KEYS = [
        'fifu_list_url',
        'fifu_list_alt',
        'fifu_list_iframe_url',
    ];

    private const REMOVED_PHASE_FIVE_META_KEYS = [
        'fifu_list_video_url',
        'fifu_slider_list_url',
        'fifu_slider_list_alt',
    ];

    private const REMOVED_PHASE_FOUR_META_KEYS = [
        'fifu_video_url',
        'fifu_custom_video_url',
        'fifu_redirection_url',
        'fifu_list_video_url',
        'fifu_ctgr_video_url',
    ];

    private const REMOVED_GALLERY_META_KEY_PATTERN = '/^(fifu_image_url_\d+|fifu_image_alt_\d+|fifu_image_ifm_\d+|fifu_slider_.*)$/';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
    }

    /**
     * Gets the singleton instance of this class.
     *
     * @return self
     */
    public static function instance(): self {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    

    /**
     * Updates or deletes a post meta field, mirroring the legacy `fifu_update_or_delete` function.
     * Converts image URLs when necessary and mirrors the meta change in the db2 layer (when available).
     *
     * @param int         $post_id The post ID.
     * @param string      $field   The meta field key.
     * @param string|null $url     The URL to save. If null, the meta is deleted.
     * @return void
     */
    public function update_or_delete( int $post_id, string $field, ?string $url ): void {
        $this->update_or_delete_with_status( $post_id, $field, $url );
    }

    /**
     * Updates or deletes a post meta field and reports whether the DB2 sync succeeded.
     *
     * @param int         $post_id
     * @param string      $field
     * @param string|null $url
     * @return bool
     */
    public function update_or_delete_with_status( int $post_id, string $field, ?string $url ): bool {
        if ( self::is_removed_video_meta_key( $field ) ) {
            return true;
        }
        if ( self::is_removed_phase_five_meta_key( $field ) ) {
            return true;
        }
        if ( self::is_removed_gallery_meta_key( $field ) ) {
            return true;
        }
        if ( self::is_removed_write_field( $field ) ) {
            return true;
        }

        $value = $this->normalize_meta_value( $field, $url );
        $sync_value = $value === '' ? null : $value;
        $is_delete = $value === '';
        if ( $this->maybe_handle_metadata_sent_field( $post_id, $field, $sync_value ) ) {
            return true;
        }

        if ( $this->is_db2_only_field( $field ) ) {
            $ok = $this->sync_meta_change_to_db2( $post_id, $field, $sync_value );
            $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, $is_delete, $sync_value );
            return $ok;
        }

        if ( $value !== '' ) {
            update_post_meta( $post_id, $field, $value );
        } else {
            delete_post_meta( $post_id, $field, $url );
        }

        $ok = $this->sync_meta_change_to_db2( $post_id, $field, $sync_value );
        $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, $is_delete, $sync_value );
        return $ok;
    }

    /**
     * Updates or deletes a post meta field based on an arbitrary value.
     * Mirrors the legacy `fifu_update_or_delete_value` function.
     *
     * @param int    $post_id The post ID.
     * @param string $field   The meta field key.
     * @param mixed  $value   The value to save. If falsy, the meta is deleted.
     * @return void
     */
    public function update_or_delete_value( int $post_id, string $field, $value ): void {
        if ( self::is_removed_video_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_phase_five_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_gallery_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_write_field( $field ) ) {
            return;
        }

        $is_delete = ! $value;
        $sync_value = $is_delete ? null : (string) $value;
        if ( $this->maybe_handle_metadata_sent_field( $post_id, $field, $sync_value ) ) {
            return;
        }

        if ( $this->is_db2_only_field( $field ) ) {
            $ok = $this->sync_meta_change_to_db2( $post_id, $field, $sync_value );
            $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, $is_delete, $sync_value );
            return;
        }

        if ( $is_delete ) {
            delete_post_meta( $post_id, $field, $value );
        } else {
            update_post_meta( $post_id, $field, $value );
        }

        // Sync the meta change to db2, passing null when the value is empty.
        $ok = $this->sync_meta_change_to_db2( $post_id, $field, $sync_value );
        $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, $is_delete, $sync_value );
    }

    /**
     * Deletes a post meta field.
     *
     * @param int    $post_id The post ID.
     * @param string $field   The meta field key.
     * @return void
     */
    public function delete( int $post_id, string $field ): void {
        if ( self::is_removed_video_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_phase_five_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_gallery_meta_key( $field ) ) {
            return;
        }
        if ( self::is_removed_write_field( $field ) ) {
            return;
        }

        if ( $this->maybe_handle_metadata_sent_field( $post_id, $field, null ) ) {
            return;
        }

        if ( $this->is_db2_only_field( $field ) ) {
            $ok = $this->sync_meta_change_to_db2( $post_id, $field, null );
            $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, true, null );
            return;
        }

        delete_post_meta( $post_id, $field );

        $ok = $this->sync_meta_change_to_db2( $post_id, $field, null );
        $this->cleanup_db2_only_legacy_postmeta( $post_id, $field, $ok, true, null );
    }

    /**
     * @param string      $field
     * @param string|null $url
     * @return string
     */
    private function normalize_meta_value( string $field, ?string $url ): string {
        if ( $url === null ) {
            return '';
        }

        if ( $this->is_alt_meta_key( $field ) ) {
            return $this->normalize_alt_meta_value( $url );
        }

        $url = trim( $url );
        if ( $url === '' ) {
            return '';
        }

        if ( strpos( $field, 'fifu_image_url' ) === 0 ) {
            return fifu_convert( $url );
        }

        return $url;
    }

    /**
     * @param string $field
     * @return bool
     */
    private function is_alt_meta_key( string $field ): bool {
        return strpos( $field, 'alt' ) !== false;
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalize_alt_meta_value( string $value ): string {
        if ( $this->is_empty_alt_value( $value ) ) {
            return '';
        }

        if ( function_exists( 'wp_strip_all_tags' ) ) {
            $value = (string) wp_strip_all_tags( $value );
        } else {
            $value = strip_tags( $value );
        }

        if ( $this->is_empty_alt_value( $value ) ) {
            return '';
        }

        return $value;
    }

    private function is_empty_alt_value( string $value ): bool {
        $trimmed = trim( $value );
        if ( $trimmed === '' ) {
            return true;
        }

        return preg_match( '/^(null|undefined)$/i', $trimmed ) === 1;
    }

    /**
     * @param int         $post_id
     * @param string      $field
     * @param string|null $value
     * @return bool
     */
    private function sync_meta_change_to_db2( int $post_id, string $field, ?string $value ): bool {
        if ( ! function_exists( 'fifu_db2_legacy_write_bridge' ) ) {
            return false;
        }

        $bridge = fifu_db2_legacy_write_bridge();
        if ( ! $bridge instanceof Fifu_Db2_Legacy_Write_Bridge ) {
            return false;
        }

        try {
            $result = $bridge->handle_post_meta_change( $post_id, $field, $value );
        } catch ( \Throwable $throwable ) {
            return false;
        }

        return $result === true;
    }

    /**
     * @param string $field
     * @return bool
     */
    /**
     * Delete legacy post meta via direct SQL for cleanup-on-write.
     *
     * @param int    $post_id
     * @param string $meta_key
     * @return void
     */
    private function delete_legacy_postmeta_raw( int $post_id, string $meta_key ): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->postmeta,
            [
                'post_id'  => $post_id,
                'meta_key' => $meta_key,
            ],
            [
                '%d',
                '%s',
            ]
        );

        clean_post_cache( $post_id );
        wp_cache_delete( $post_id, 'post_meta' );
    }

    /**
     * Remove legacy video-list related postmeta after DB2 has already been populated.
     *
     * @param int $post_id
     * @return void
     */
    private function maybe_handle_metadata_sent_field( int $post_id, string $field, ?string $value ): bool {
        if ( ! $this->is_metadata_sent_field( $field ) ) {
            return false;
        }

        $service = $this->get_db2_sent_service();
        if ( ! $service ) {
            return false;
        }

        $sent = (int) $value === 1;
        $ok = $service->set_sent_post_metadata( $post_id, $sent );
        if ( $ok ) {
            $this->delete_legacy_postmeta_raw( $post_id, $field );
        }

        return $ok;
    }

    private function is_metadata_sent_field( string $field ): bool {
        return in_array( $field, self::DB2_ONLY_SENT_META_KEYS, true );
    }

    private function get_db2_sent_service(): ?Fifu_Db2_Sent_Service {
        if ( ! function_exists( 'fifu_db2_sent_service' ) ) {
            return null;
        }

        $service = fifu_db2_sent_service();
        if ( ! $service instanceof Fifu_Db2_Sent_Service ) {
            return null;
        }

        return $service;
    }

    private static function is_removed_gallery_meta_key(string $field): bool {
        return in_array($field, self::REMOVED_GALLERY_META_KEYS, true)
            || preg_match(self::REMOVED_GALLERY_META_KEY_PATTERN, $field) === 1;
    }

    private static function is_removed_phase_five_meta_key(string $field): bool {
        return in_array($field, self::REMOVED_PHASE_FIVE_META_KEYS, true)
            || preg_match('/^(fifu_image_url_\d+|fifu_image_alt_\d+|fifu_image_ifm_\d+|fifu_slider_image_url_\d+|fifu_slider_image_alt_\d+|fifu_slider_.*)$/', $field) === 1;
    }

    private const DB2_ONLY_SENT_META_KEYS = array(
        'fifu_metadatapost_sent',
    );

    private function is_db2_only_field(string $field): bool {
        return in_array($field, [
            'fifu_image_url',
            'fifu_image_alt',
            'fifu_metadatapost_sent',
        ], true);
    }

    private static function is_removed_write_field( string $field ): bool {
        return in_array( $field, self::REMOVED_WRITE_META_KEYS, true );
    }

    private static function is_removed_video_meta_key( string $meta_key ): bool {
        return in_array( $meta_key, self::REMOVED_PHASE_FOUR_META_KEYS, true )
            || preg_match( '/^fifu_video_url_\d+$/', $meta_key ) === 1;
    }

    private function cleanup_db2_only_legacy_postmeta(int $post_id, string $field, bool $ok, bool $is_delete, ?string $value): void {
        if (!$ok || !empty($_REQUEST['fifu_preserve_legacy_meta_until_db2_confirmation'])) {
            return;
        }
        if (($field === 'fifu_image_url' || $field === 'fifu_image_alt')
            && !$this->is_db2_featured_image_post_meta_confirmed($post_id, $field, $value, $is_delete)) {
            return;
        }
        if ($field === 'fifu_image_url' || $field === 'fifu_image_alt') {
            $this->delete_legacy_postmeta_raw($post_id, $field);
        }
    }

    private function is_db2_featured_image_post_meta_confirmed( int $post_id, string $field, ?string $value, bool $is_delete ): bool {
        if ( ! function_exists( 'fifu_db2_manager' ) || ! class_exists( 'Fifu_Db2_Manager', false ) ) {
            return false;
        }

        $manager = fifu_db2_manager();
        if ( ! $manager instanceof Fifu_Db2_Manager ) {
            return false;
        }

        if ( $field === 'fifu_image_url' ) {
            $mapping = $manager->getPostMapping( $post_id, 'image', 0 );
            if ( $is_delete ) {
                return ! is_array( $mapping ) || (string) ( $mapping['url'] ?? '' ) === '';
            }

            if ( $value === null || $value === '' ) {
                return false;
            }

            return is_array( $mapping ) && (string) ( $mapping['url'] ?? '' ) === $value;
        }

        if ( $field === 'fifu_image_alt' ) {
            $mapping = $manager->getPostAltMapping( $post_id, 'image', 0 );
            if ( $is_delete ) {
                return ! is_array( $mapping ) || (string) ( $mapping['alt'] ?? '' ) === '';
            }

            if ( $value === null || $value === '' ) {
                return false;
            }

            $expected_alt = $this->normalize_alt_meta_value( $value );
            return is_array( $mapping ) && (string) ( $mapping['alt'] ?? '' ) === $expected_alt;
        }

        return true;
    }

}
