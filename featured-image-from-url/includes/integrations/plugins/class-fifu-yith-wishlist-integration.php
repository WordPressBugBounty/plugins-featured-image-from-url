<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * YITH WooCommerce Wishlist integration helpers.
 */
final class Fifu_Yith_Wishlist_Integration {

	/**
	 * @return bool
	 */
	public static function should_wait_for_ajax(): bool {
		return self::is_wishlist_active() && self::is_ajax_enabled();
	}

	/**
	 * @return bool
	 */
	public static function is_wishlist_active(): bool {
		return class_exists( 'Fifu_Plugin_Detector' ) && Fifu_Plugin_Detector::is_yith_woocommerce_wishlist_active();
	}

	/**
	 * @return bool
	 */
	public static function is_ajax_enabled(): bool {
		return class_exists( 'Fifu_Plugin_Detector' ) && Fifu_Plugin_Detector::is_yith_woocommerce_wishlist_ajax_enabled();
	}
}
