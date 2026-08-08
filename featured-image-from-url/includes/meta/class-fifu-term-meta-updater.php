<?php

/**
 * Handles updating and deleting FIFU term meta fields.
 */
class FIFU_Term_Meta_Updater {

    /**
     * @var self
     */
    private static $instance;

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
     * Updates or deletes a term meta field based on a URL, converting image URLs when needed.
     * Mirrors the legacy `fifu_update_or_delete_ctgr` function and keeps db2 in sync for image metadata.
     *
     * @param int         $term_id The term ID.
     * @param string      $field   The meta field key.
     * @param string|null $url     The URL to save. If null, the meta is deleted.
     * @return void
     */
    public function update_or_delete_term( int $term_id, string $field, ?string $url ): void {
        $this->update_or_delete_term_with_status( $term_id, $field, $url );
    }

    public function update_or_delete_term_with_status( int $term_id, string $field, ?string $url ): bool {
        $value = $url;

        if ( $url ) {
            $value = strpos( $field, 'fifu_image_url' ) === 0 ? fifu_convert( $url ) : $url;
        }

        if ( $this->is_alt_meta_key( $field ) && $value !== null ) {
            $value = $this->normalize_alt_meta_value( $value );
        }

        $is_delete = $value === null || $value === '';

        if ( $this->maybe_handle_metadata_sent_field( $term_id, $field, $value ) ) {
            return true;
        }

        if ( $this->is_db2_only_field( $field ) ) {
            $ok = $this->sync_term_meta_change_to_db2( $term_id, $field, $value );
            $this->cleanup_db2_only_legacy_termmeta( $term_id, $field, $ok, $is_delete, $value );
            return $ok;
        }

        if ( ! $is_delete ) {
            update_term_meta( $term_id, $field, $value );
            $ok = $this->sync_term_meta_change_to_db2( $term_id, $field, $value );
            $this->cleanup_db2_only_legacy_termmeta( $term_id, $field, $ok, $is_delete, $value );
            return $ok;
        }

        delete_term_meta( $term_id, $field, $url );
        $ok = $this->sync_term_meta_change_to_db2( $term_id, $field, null );
        $this->cleanup_db2_only_legacy_termmeta( $term_id, $field, $ok, $is_delete, null );
        return $ok;
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
        $value = trim( $value );
        if ( $value === '' || preg_match( '/^(null|undefined)$/i', $value ) === 1 ) {
            return '';
        }

        if ( function_exists( 'wp_strip_all_tags' ) ) {
            $value = (string) wp_strip_all_tags( $value );
        } else {
            $value = strip_tags( $value );
        }

        $value = trim( $value );
        if ( $value === '' || preg_match( '/^(null|undefined)$/i', $value ) === 1 ) {
            return '';
        }

        return $value;
    }
    /**
     * Deletes a term meta field.
     *
     * @param int    $term_id The term ID.
     * @param string $field   The meta field key.
     * @return void
     */
    public function delete( int $term_id, string $field ): void {
        $this->delete_with_status( $term_id, $field );
    }

    public function delete_with_status( int $term_id, string $field ): bool {
        if ( $this->maybe_handle_metadata_sent_field( $term_id, $field, null ) ) {
            return true;
        }

        if ( $this->is_db2_only_field( $field ) ) {
            $ok = $this->sync_term_meta_change_to_db2( $term_id, $field, null );
            $this->cleanup_db2_only_legacy_termmeta( $term_id, $field, $ok, true, null );
            return $ok;
        }

        delete_term_meta( $term_id, $field );
        $ok = $this->sync_term_meta_change_to_db2( $term_id, $field, null );
        $this->cleanup_db2_only_legacy_termmeta( $term_id, $field, $ok, true, null );
        return $ok;
    }

    /**
     * Mirrors term meta changes to the db2 layer when supported.
     *
     * @param int         $term_id
     * @param string      $field
     * @param string|null $value
     * @return bool
     */
    private function sync_term_meta_change_to_db2( int $term_id, string $field, ?string $value ): bool {
        if ( ! function_exists( 'fifu_db2_legacy_write_bridge' ) ) {
            return false;
        }

        $bridge = fifu_db2_legacy_write_bridge();
        if ( ! $bridge instanceof Fifu_Db2_Legacy_Write_Bridge ) {
            return false;
        }
        return $bridge->handle_term_meta_change( $term_id, $field, $value );
    }

    /**
     * @param string $field
     * @return bool
     */
    private function is_db2_only_video_key( string $field ): bool {
        return false;
    }

    /**
     * @param int    $term_id
     * @param string $meta_key
     * @return void
     */
    private function delete_legacy_termmeta_raw( int $term_id, string $meta_key ): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->termmeta,
            [
                'term_id'  => $term_id,
                'meta_key' => $meta_key,
            ],
            [
                '%d',
                '%s',
            ]
        );

        clean_term_cache( $term_id );
        wp_cache_delete( $term_id, 'term_meta' );
    }

    private function should_preserve_legacy_termmeta_until_db2_confirmation(): bool {
        return ! empty( $_REQUEST['fifu_preserve_legacy_meta_until_db2_confirmation'] );
    }

    private function maybe_handle_metadata_sent_field( int $term_id, string $field, ?string $value ): bool {
        if ( ! $this->is_metadata_sent_field( $field ) ) {
            return false;
        }

        $service = $this->get_db2_sent_service();
        if ( ! $service ) {
            return false;
        }

        $sent = (int) $value === 1;
        $ok = $service->set_sent_term_metadata( $term_id, $sent );
        if ( $ok ) {
            $this->delete_legacy_termmeta_raw( $term_id, $field );
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

    public static function set_cached_term_image_url(int $termId, ?string $url): void {
        if ($url === null) {
            unset(self::$cachedTermImageUrls[$termId]);
            return;
        }

        self::$cachedTermImageUrls[$termId] = $url;
    }

    public static function get_cached_term_image_url(int $termId): ?string {
        return self::$cachedTermImageUrls[$termId] ?? null;
    }

    private const DB2_ONLY_SENT_META_KEYS = array(
        'fifu_metadataterm_sent',
    );

    /**
     * @param string $field
     * @return bool
     */
    private function is_db2_only_field( string $field ): bool {
        return in_array( $field, array(
            'fifu_image_alt',
            'fifu_image_url',
        ), true )
            || in_array( $field, self::DB2_ONLY_SENT_META_KEYS, true );
    }

    private function is_db2_only_term_image_url_key( string $field ): bool {
        return $field === 'fifu_image_url';
    }

    private function is_db2_only_term_image_alt_key( string $field ): bool {
        return $field === 'fifu_image_alt';
    }

    private function cleanup_db2_only_legacy_termmeta( int $term_id, string $field, bool $ok, bool $delete_images, ?string $value ): void {
        if ( ! $ok ) {
            return;
        }

        if ( $this->should_preserve_legacy_termmeta_until_db2_confirmation() ) {
            return;
        }

        if ( ! $this->is_db2_term_meta_confirmed( $term_id, $field, $value, $delete_images ) ) {
            return;
        }

        if ( $this->is_db2_only_video_key( $field ) ) {
            $this->delete_legacy_termmeta_raw( $term_id, $field );
            return;
        }

        if ( $this->is_db2_only_term_image_url_key( $field ) ) {
            $this->delete_legacy_termmeta_raw( $term_id, $field );
            $this->delete_legacy_termmeta_raw( $term_id, 'fifu_image_alt' );

            return;
        }

        if ( $this->is_db2_only_term_image_alt_key( $field ) ) {
            $this->delete_legacy_termmeta_raw( $term_id, $field );
        }
    }

    private function is_db2_term_meta_confirmed( int $term_id, string $field, ?string $value, bool $is_delete ): bool {
        if ( ! function_exists( 'fifu_db2_manager' ) ) {
            return false;
        }

        if ( ! class_exists( 'Fifu_Db2_Manager', false ) ) {
            return false;
        }

        $manager = fifu_db2_manager();
        if ( ! $manager instanceof Fifu_Db2_Manager ) {
            return false;
        }

        if ( $field === 'fifu_image_url' ) {
            $mapping = $manager->getTermMapping( $term_id, 'image' );
            if ( $is_delete ) {
                return ! is_array( $mapping ) || empty( $mapping['url'] );
            }

            return is_array( $mapping ) && isset( $mapping['url'] ) && (string) $mapping['url'] === (string) $value;
        }

        if ( $field === 'fifu_image_alt' ) {
            $mapping = $manager->getTermAltMapping( $term_id, 'image' );
            if ( $is_delete ) {
                return ! is_array( $mapping ) || empty( $mapping['alt'] );
            }

            $expectedAlt = $this->normalize_alt_meta_value( (string) ( $value ?? '' ) );
            return is_array( $mapping ) && isset( $mapping['alt'] ) && (string) $mapping['alt'] === $expectedAlt;
        }

        return false;
    }

    /**
     * Detects whether a DB2 key_type exists in fifu_key.
     *
     * @param string $key_type
     * @return bool
     */
    private function db2_key_type_exists( string $key_type ): bool {
        if ( ! function_exists( 'fifu_db2_manager' ) ) {
            return false;
        }
        if ( ! class_exists( 'Fifu_Db2_Manager', false ) ) {
            return false;
        }

        $manager = fifu_db2_manager();
        if ( ! $manager instanceof Fifu_Db2_Manager ) {
            return false;
        }

        $wpdb = $manager->getWpdb();
        if ( ! $wpdb instanceof wpdb ) {
            return false;
        }

        $table = $wpdb->prefix . 'fifu_key';
        $key_id = $wpdb->get_var( $wpdb->prepare( "SELECT key_id FROM {$table} WHERE key_type = %s LIMIT 1", $key_type ) );
        return $key_id !== null;
    }

}
