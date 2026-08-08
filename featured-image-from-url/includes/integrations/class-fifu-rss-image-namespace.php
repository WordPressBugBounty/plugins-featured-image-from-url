<?php

defined( 'ABSPATH' ) || exit;

/**
 * Handles RSS namespace registration for FIFU images.
 */
class Fifu_Rss_Image_Namespace {

    /**
     * Prepares the RSS feed buffer for FIFU media nodes.
     *
     * @return void
     */
    public static function start_buffer(): void {
        ob_start();
    }

    /**
     * Injects the media namespace declaration into the feed.
     *
     * @return void
     */
    public static function inject_media_namespace(): void {
        $rss_ns = ob_get_clean();
        if ( strpos( $rss_ns, 'xmlns:media="http://search.yahoo.com/mrss/"' ) === false ) {
            $rss_ns = preg_replace(
                '/(<rss version="[^"]+")/',
                '$1' . PHP_EOL . "\t" . 'xmlns:media="http://search.yahoo.com/mrss/"',
                $rss_ns
            );
        }
        echo $rss_ns;
    }
}
