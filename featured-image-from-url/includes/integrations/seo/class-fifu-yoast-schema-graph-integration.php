<?php

defined('ABSPATH') || exit;

/**
 * Replaces Yoast schema images with FIFU-managed URLs.
 */
class Fifu_Yoast_Schema_Graph_Integration {

    /**
     * Filters the graphql schema graph to include FIFU image URLs.
     *
     * @param mixed $graph
     * @param mixed $context
     */
    public static function filter_schema_graph($graph, $context) {
        if (!is_array($graph)) {
            return $graph;
        }

        if (!is_singular()) {
            return $graph;
        }

        $post_id = get_the_ID();

        $image_urls = Fifu_Post_Image_Urls::get_all((int) $post_id);

        if (empty($image_urls)) {
            $url = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, true);
            $image_urls = $url ? [$url] : [];
        }

        if (empty($image_urls)) {
            return $graph;
        }

        foreach ($graph as &$item) {
            if (isset($item['@type']) && in_array($item['@type'], ['Article', 'WebPage', 'Product'], true)) {
                if (isset($item['primaryImageOfPage'])) {
                    $item['primaryImageOfPage'] = $image_urls[0];
                }

                if (isset($item['image'])) {
                    $item['image'] = $image_urls;
                }
            }

            if (isset($item['@type']) && $item['@type'] === 'ImageObject') {
                if (isset($item['url'])) {
                    $item['url'] = $image_urls[0];
                }
                if (isset($item['contentUrl'])) {
                    $item['contentUrl'] = $image_urls[0];
                }
            }
        }

        return $graph;
    }
}
