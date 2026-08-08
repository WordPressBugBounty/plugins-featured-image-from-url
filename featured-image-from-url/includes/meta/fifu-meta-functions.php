<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// The new classes are loaded here to be available for the wrapper functions.
require_once __DIR__ . '/class-fifu-post-meta-updater.php';
require_once __DIR__ . '/class-fifu-term-meta-updater.php';
require_once __DIR__ . '/class-fifu-meta-debug-utils.php';
require_once __DIR__ . '/class-fifu-sent-bridge.php';



/**
 * Legacy wrapper for updating or deleting a post meta field.
 *
 * @deprecated Use FIFU_Post_Meta_Updater::instance()->update_or_delete() instead.
 * @param int    $post_id The post ID.
 * @param string $field   The meta field key.
 * @param string $url     The URL to save.
 * @return void
 */
function fifu_update_or_delete( $post_id, $field, $url ): void {
    FIFU_Post_Meta_Updater::instance()->update_or_delete( (int) $post_id, (string) $field, $url );
}

/**
 * Legacy wrapper for updating or deleting a post meta field with an arbitrary value.
 *
 * @deprecated Use FIFU_Post_Meta_Updater::instance()->update_or_delete_value() instead.
 * @param int    $post_id The post ID.
 * @param string $field   The meta field key.
 * @param mixed  $value   The value to save.
 * @return void
 */
function fifu_update_or_delete_value( $post_id, $field, $value ): void {
    FIFU_Post_Meta_Updater::instance()->update_or_delete_value( (int) $post_id, (string) $field, $value );
}

/**
 * Legacy wrapper for updating or deleting a term meta field.
 *
 * @deprecated Use FIFU_Term_Meta_Updater::instance()->update_or_delete_term() instead.
 * @param int    $term_id The term ID.
 * @param string $field   The meta field key.
 * @param string $url     The URL to save.
 * @return void
 */
function fifu_update_or_delete_ctgr( $term_id, $field, $url ): void {
    FIFU_Term_Meta_Updater::instance()->update_or_delete_term( (int) $term_id, (string) $field, $url );
}

/**
 * Legacy wrapper for deleting a post meta field.
 *
 * @deprecated Use FIFU_Post_Meta_Updater::instance()->delete() instead.
 * @param int    $post_id The post ID.
 * @param string $field   The meta field key.
 * @param mixed  $value   Legacy value parameter kept for callsite compatibility.
 * @return void
 */
function fifu_delete_post_meta( $post_id, $field, $value = null ): void {
    // $value mantido por compatibilidade; ignorado.
    FIFU_Post_Meta_Updater::instance()->delete( (int) $post_id, (string) $field );
}

/**
 * Legacy wrapper for deleting a term meta field.
 *
 * @deprecated Use FIFU_Term_Meta_Updater::instance()->delete() instead.
 * @param int    $term_id The term ID.
 * @param string $field   The meta field key.
 * @param mixed  $value   Legacy value parameter kept for callsite compatibility.
 * @return void
 */
function fifu_delete_term_meta( $term_id, $field, $value = null ): void {
    // $value mantido por compatibilidade; ignorado.
    FIFU_Term_Meta_Updater::instance()->delete( (int) $term_id, (string) $field );
}

/**
 * Compatibility wrapper for the FIFU author id.
 *
 * @return int
 */
function fifu_get_author(): int {
    return fifu_resolve_author();
}

/**
 * Returns the list of FIFU attachment author candidates in priority order.
 *
 * @return int[]
 */
function fifu_get_fifu_author_candidates(): array {
    $candidates = [];

    $option = get_option( 'fifu_author', null );
    if ( $option !== null && $option !== false && trim( (string) $option ) !== '' ) {
        $candidates[] = (int) $option;
    }

    if ( class_exists( 'Fifu_Options_Utils', false ) && method_exists( 'Fifu_Options_Utils', 'get_author' ) ) {
        $author = (int) Fifu_Options_Utils::get_author();
        if ( $author > 0 ) {
            $candidates[] = $author;
        }
    }

    if ( defined( 'FIFU_AUTHOR' ) ) {
        $author = (int) FIFU_AUTHOR;
        if ( $author > 0 ) {
            $candidates[] = $author;
        }
    }

    $resolved = fifu_resolve_author();
    if ( $resolved > 0 ) {
        $candidates[] = $resolved;
    }

    $candidates[] = 7777777777;
    $candidates[] = 77777;

    $candidates = array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn( $author ): int => (int) $author,
                    $candidates
                ),
                static fn( int $author ): bool => $author > 0
            )
        )
    );

    return $candidates;
}

/**
 * Checks whether an author matches FIFU attachment ownership.
 *
 * @param mixed $author
 * @return bool
 */
function fifu_is_fifu_attachment_author( $author ): bool {
    $author = (int) $author;
    if ( $author <= 0 ) {
        return false;
    }

    return in_array( $author, fifu_get_fifu_author_candidates(), true );
}

/**
 * Returns the list of reserved FIFU authors in priority order.
 *
 * @return int[]
 */
function fifu_get_reserved_authors(): array {
    return array( 77777, 7777777777 );
}

/**
 * Checks whether an author belongs to FIFU's reserved ownership range.
 *
 * @param mixed $author
 * @return bool
 */
function fifu_is_fifu_author( $author ): bool {
    return fifu_is_fifu_attachment_author( $author );
}

/**
 * Resolves the author used by FIFU attachments.
 *
 * Option value wins. Without an option, prefer legacy 77777 rows first, then 7777777777 rows,
 * and default to 7777777777.
 *
 * @return int
 */
function fifu_resolve_author(): int {
    $option = get_option( 'fifu_author', null );
    if ( $option !== null && $option !== false && trim( (string) $option ) !== '' ) {
        return (int) $option;
    }

    global $wpdb;
    if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->posts ) ) {
        $legacy_small = (int) $wpdb->get_var( "SELECT 1 FROM {$wpdb->posts} WHERE post_author = 77777 LIMIT 1" );
        if ( $legacy_small ) {
            return 77777;
        }

        $canonical_large = (int) $wpdb->get_var( "SELECT 1 FROM {$wpdb->posts} WHERE post_author = 7777777777 LIMIT 1" );
        if ( $canonical_large ) {
            return 7777777777;
        }
    }

    return 7777777777;
}

/**
 * Checks whether an attachment is FIFU-owned.
 *
 * @param mixed $attachment_id
 * @return bool
 */
function fifu_is_fifu_attachment( $attachment_id ): bool {
    $attachment_id = (int) $attachment_id;
    if ( $attachment_id <= 0 ) {
        return false;
    }

    $att_post = get_post( $attachment_id );
    if ( ! $att_post ) {
        return false;
    }

    return fifu_is_fifu_attachment_author( $att_post->post_author );
}

/**
 * Returns the raw stored remote URL if the attachment is remote/FIFU-owned.
 *
 * @param mixed $attachment_id
 * @return string
 */
function fifu_get_raw_remote_attached_file( $attachment_id ): string {
    $attachment_id = (int) $attachment_id;
    if ( $attachment_id <= 0 ) {
        return '';
    }

    $raw_url = trim( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
    if ( $raw_url === '' ) {
        return '';
    }

    if ( fifu_is_fifu_attachment( $attachment_id ) ) {
        return $raw_url;
    }

    foreach ( array( 'http://', 'https://', '//', ';http://', ';https://', ';/') as $prefix ) {
        if ( strpos( $raw_url, $prefix ) === 0 ) {
            return $raw_url;
        }
    }

    return '';
}

/**
 * @return Fifu_Attachment_Repository
 */
function fifu_attachment_repository(): Fifu_Attachment_Repository {
    static $instance = null;
    if ( $instance === null ) {
        $instance = new Fifu_Attachment_Repository();
    }
    return $instance;
}

/**
 * Wrapper para debug da tabela fifu_meta_in.
 *
 * @return array
 */
function fifu_db_debug_metain(): array {
    return Fifu_Meta_Debug_Utils::debug_metain();
}

/**
 * Wrapper para debug da tabela fifu_meta_out.
 *
 * @return array
 */
function fifu_db_debug_metaout(): array {
    return Fifu_Meta_Debug_Utils::debug_metaout();
}

/**
 * Wrapper para debug de postmeta.
 *
 * @param int $post_id
 * @return array
 */
function fifu_db_debug_postmeta( $post_id ): array {
    return Fifu_Meta_Debug_Utils::debug_postmeta( (int) $post_id );
}

/**
 * Wrapper para debug de posts por ID.
 *
 * @param int $id
 * @return array
 */
function fifu_db_debug_posts( $id ): array {
    return Fifu_Meta_Debug_Utils::debug_posts( (int) $id );
}

/**
 * Wrapper para debug de posts por slug.
 *
 * @param string $slug
 * @return array
 */
function fifu_db_debug_slug( $slug ): array {
    return Fifu_Meta_Debug_Utils::debug_slug( (string) $slug );
}

/**
 * Wrapper para contagem de URLs legadas.
 *
 * @return int
 */
function fifu_db_count_urls(): int {
    return Fifu_Meta_Debug_Utils::count_legacy_url_meta();
}
