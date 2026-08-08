<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prepares future social tag rendering for term archives.
 */
class Fifu_Category_Social_Tags {

    /**
     * Future: renders social image tags for a category archive.
     */
    public static function render_category_social_tags(): void {
        $url   = self::get_image_url();
        $title = single_cat_title( '', false );

        $term_id = self::get_term_id();
        if ( $term_id ) {
            $description = wp_strip_all_tags( category_description( $term_id ) );
        }
    }

    /**
     * Future: obtains the current category term ID.
     */
    public static function get_term_id(): ?int {
        global $wp_query;
        if ( ! isset( $wp_query ) ) {
            return null;
        }
        return $wp_query->get_queried_object_id() ?? null;
    }

    /**
     * Future: returns the category image URL.
     */
    public static function get_image_url( ?int $term_id = null ): ?string {
        $term_id = $term_id ?: self::get_term_id();
        if ( ! $term_id ) {
            return null;
        }
        return Fifu_Term_Image_Url_Read_Service::get_image_url((int) $term_id);
    }

    /**
     * Future: returns the category image alt text.
     */
    public static function get_alt( ?int $term_id = null ): ?string {
        $term_id = $term_id ?: self::get_term_id();
        if ( ! $term_id ) {
            return null;
        }
        return Fifu_Term_Image_Alt_Read_Service::get_image_alt((int) $term_id);
    }
}
