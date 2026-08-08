<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public developer API: set FIFU image for a post.
 *
 * @param int    $post_id   Post ID.
 * @param string $image_url Remote image URL.
 * @return bool Result from delegated service call.
 */
function fifu_dev_set_image( $post_id, $image_url ) {
	return Fifu_Developer_Media_Service::set_image(
		(int) $post_id,
		$image_url
	);
}

/**
 * Public developer API: set FIFU image for a term.
 *
 * @param int    $term_id   Term ID.
 * @param string $image_url Remote image URL.
 * @return bool Result from delegated service call.
 */
function fifu_dev_set_category_image( $term_id, $image_url ) {
	return Fifu_Developer_Media_Service::set_category_image(
		(int) $term_id,
		$image_url
	);
}
