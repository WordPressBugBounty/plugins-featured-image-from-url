<?php

defined( 'ABSPATH' ) || exit;

/**
 * Filters attachment queries to exclude FIFU-managed media when required.
 */
class Fifu_Attachment_Query_Filters {

    private static function get_author_id(): int {
        if ( function_exists( 'fifu_get_author' ) ) {
            $author = (int) fifu_get_author();
            if ( $author > 0 ) {
                return $author;
            }
        }

        if ( class_exists( 'Fifu_Options_Utils', false ) && method_exists( 'Fifu_Options_Utils', 'get_author' ) ) {
            $author = (int) Fifu_Options_Utils::get_author();
            if ( $author > 0 ) {
                return $author;
            }
        }

        if ( defined( 'FIFU_AUTHOR' ) ) {
            $author = (int) FIFU_AUTHOR;
            if ( $author > 0 ) {
                return $author;
            }
        }

        return 7777777777;
    }

    /**
     * Removes FIFU attachments from media library AJAX requests.
     *
     * @param string $where
     * @return string
     */
    public static function filter_media_library_where( string $where ): string {
        global $wpdb;

        $action = $_POST['action'] ?? '';
        if ( Fifu_Web_Stories_Integration::is_web_story() || $action === 'query-attachments' || $action === 'get-attachment' ) {
            $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author <> %d ", self::get_author_id() );
        }

        return $where;
    }

    /**
     * Removes FIFU attachments from the admin attachment list query.
     *
     * @param string   $where
     * @param \WP_Query $query
     * @return string
     */
    public static function filter_admin_attachment_list_where( string $where, \WP_Query $query ): string {
        global $wpdb;

        if ( Fifu_Web_Stories_Integration::is_web_story() || ( is_admin() && $query->is_main_query() && strpos( $where, 'attachment' ) !== false ) ) {
            $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author <> %d ", self::get_author_id() );
        }

        return $where;
    }
}
