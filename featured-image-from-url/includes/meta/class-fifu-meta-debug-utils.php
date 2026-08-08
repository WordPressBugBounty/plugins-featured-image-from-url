<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fifu_Meta_Debug_Utils {

	/**
	 * @return wpdb
	 */
	private static function get_wpdb(): wpdb {
		global $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return $wpdb;
	}

	/**
	 * @param string $suffix
	 * @return string
	 */
	private static function get_table_name( string $suffix ): string {
		return self::get_wpdb()->prefix . $suffix;
	}

	/**
	 * Debug da tabela fifu_meta_in.
	 *
	 * @return array
	 */
	public static function debug_metain(): array {
		return self::get_wpdb()->get_results( "SELECT * FROM " . self::get_table_name( 'fifu_meta_in' ) );
	}

	/**
	 * Debug da tabela fifu_meta_out.
	 *
	 * @return array
	 */
	public static function debug_metaout(): array {
		return self::get_wpdb()->get_results( "SELECT * FROM " . self::get_table_name( 'fifu_meta_out' ) );
	}

	/**
	 * Debug em postmeta para um post específico.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function debug_postmeta( int $post_id ): array {
		$wpdb = self::get_wpdb();
		$postmeta = self::get_table_name( 'postmeta' );
		$posts = self::get_table_name( 'posts' );

		$sql = $wpdb->prepare(
			"
            SELECT pm.meta_key, pm.meta_value
            FROM {$postmeta} pm
            INNER JOIN {$posts} p ON p.ID = pm.post_id
            WHERE pm.post_id = %d 
              AND p.post_status <> 'private'
              AND (p.post_password = '' OR p.post_password IS NULL)
              AND (
                  pm.meta_key LIKE 'fifu%'
                  OR pm.meta_key IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_image_alt', '_product_image_gallery', '_wc_additional_variation_images')
              )
            ",
			$post_id
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Debug em posts por ID.
	 *
	 * @param int $id
	 * @return array
	 */
	public static function debug_posts( int $id ): array {
		$wpdb = self::get_wpdb();
		$posts = self::get_table_name( 'posts' );

		$sql = $wpdb->prepare(
			"
            SELECT post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type
            FROM {$posts} 
            WHERE id = %d
            AND post_status <> 'private'
            AND (post_password = '' OR post_password IS NULL)
            ",
			$id
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Debug de posts por slug.
	 *
	 * @param string $slug
	 * @return array
	 */
	public static function debug_slug( string $slug ): array {
		$wpdb = self::get_wpdb();
		$posts = self::get_table_name( 'posts' );

		$sql = $wpdb->prepare(
			"
            SELECT ID, post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type 
            FROM {$posts} 
            WHERE post_name = %s
              AND post_status <> 'private'
              AND (post_password = '' OR post_password IS NULL)
            ",
			$slug
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Conta quantos registros de URL legada existem (postmeta/meta antiga).
	 *
	 * @return int
	 */
	public static function count_legacy_url_meta(): int {
		$wpdb = self::get_wpdb();
		$postmeta = self::get_table_name( 'postmeta' );
		$termmeta = self::get_table_name( 'termmeta' );

		$sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM {$postmeta} AS pm
                    WHERE pm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND pm.meta_key LIKE '%url%'
                    AND pm.meta_key NOT LIKE '%list%'
                ) +
                (
                    SELECT COUNT(*)
                    FROM {$termmeta} AS tm
                    WHERE tm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND tm.meta_key LIKE '%url%'
                ) AS amount
        ";

		$result = $wpdb->get_var( $sql );

		return $result ? (int) $result : 0;
	}
}
