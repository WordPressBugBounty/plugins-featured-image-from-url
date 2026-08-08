<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public developer API bridge for the free build.
 *
 * The free build exposes only image and category image developer APIs.
 */
class Fifu_Developer_Media_Service {
	/**
	 * Set a remote image URL for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $image_url Remote image URL.
	 * @param bool   $force_featured_attachment_sync Whether to bypass generic post-save suppression while syncing the featured attachment.
	 * @return bool True on success, false on failure.
	 */
	public static function set_image( int $post_id, $image_url, bool $force_featured_attachment_sync = false ): bool {
		return Fifu_Internal_Media_Write_Service::set_image(
			$post_id,
			$image_url,
			true
		);
	}

	/**
	 * Set a remote image URL for a term.
	 *
	 * @param int    $term_id   Term ID.
	 * @param string $image_url Remote image URL.
	 * @return bool True on success, false on failure.
	 */
	public static function set_category_image( int $term_id, $image_url ): bool {
		return Fifu_Internal_Media_Write_Service::set_category_image(
			$term_id,
			$image_url
		);
	}
}
