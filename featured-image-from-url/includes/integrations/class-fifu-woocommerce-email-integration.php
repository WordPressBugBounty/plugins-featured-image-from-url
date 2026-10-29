<?php

defined( 'ABSPATH' ) || exit;

/**
 * Handles FIFU integration with WooCommerce order email templates.
 */
class Fifu_Woocommerce_Email_Integration {

    /**
     * Filters the order items table within WooCommerce emails.
     *
     * @param string $output Original table HTML.
     * @param mixed  $order  Order object.
     * @return string
     */
    public static function filter_email_order_items_table($output, $order) {
        if (!is_string($output)) {
            return $output;
        }

        if ( Fifu_Options_Utils::is_off( 'fifu_order_email' ) ) {
            return $output;
        }

        static $is_rendering = false;
        if ( $is_rendering ) {
            return $output;
        }

        $is_rendering = true;

        try {
            return wc_get_email_order_items(
                $order,
                [
                    'show_image' => true,
                    'image_size' => [ 100, 100 ],
                ]
            );
        } finally {
            $is_rendering = false;
        }
    }
}
