<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prints social metadata for the home page when FIFU is active.
 */
class Fifu_Home_Social_Tags {

    /**
     * Outputs the necessary social tags for the home page.
     *
     * @return void
     */
    public static function render_home_social_tags(): void {
        if ( ! is_front_page() ) {
            return;
        }

        $url = get_option( 'fifu_default_url', '' );
        if ( empty( $url ) ) {
            return;
        }

        $buffer_contents = ob_get_contents();
        if ( $buffer_contents !== false && strpos( $buffer_contents, '<meta property="og:image"' ) === false ) {
            $url = esc_url( $url );
            include FIFU_INCLUDES_DIR . '/html/social-home.html';
        }
    }
}
