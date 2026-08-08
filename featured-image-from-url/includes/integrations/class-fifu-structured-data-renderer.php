<?php

defined( 'ABSPATH' ) || exit;

/**
 * Responsible for rendering the FIFU structured data payloads.
 */
class Fifu_Structured_Data_Renderer {

    /**
     * Main entrypoint that replaces fifu_render_structured_data().
     *
     * It will detect whether the current post type is a WooCommerce product or a blog post,
     * respect Fifu_Plugin_Detector::is_any_seo_plugin_active(), and decide whether to emit a
     * full Product/BlogPosting JSON-LD node or just ImageObject/@graph.
     *
     * @param int   $post_id
     * @param mixed $image_urls
     * @return void
     */
    public static function render_for_post( int $post_id, $image_urls ): void {
        if ( ! $post_id ) {
            return;
        }

        $type = get_post_type( $post_id );
        if ( ! is_singular( $type ) ) {
            return;
        }

        $page_url = get_permalink( $post_id );
        $image_urls = self::maybe_photonize_images( $image_urls, $post_id );
        $image_list = is_array( $image_urls ) ? $image_urls : ( empty( $image_urls ) ? [] : [ $image_urls ] );

        $json_ld = [
            '@context' => 'https://schema.org',
            'post_id' => $post_id,
            'url' => $page_url,
            'image' => $image_list,
        ];

        $seo_active = class_exists( 'Fifu_Plugin_Detector' ) ? Fifu_Plugin_Detector::is_any_seo_plugin_active() : false;
        if ( $seo_active ) {
            $payload = self::build_image_graph( $json_ld['image'], $page_url );
            if ( $payload ) {
                self::output_payload( $payload );
            }
            return;
        }

        if ( $type === 'product' ) {
            self::render_product( $json_ld, $post_id );
            return;
        }

        self::render_article( $json_ld, $post_id );
    }

    /**
     * Hookable helper that calls render_for_post() for the current page.
     *
     * This will be wired into wp_head in a later step.
     *
     * @return void
     */
    public static function render_current_page(): void {
        if ( is_front_page() || is_home() || is_tax() ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }
        if ( ! $post_id ) {
            return;
        }

        $type = get_post_type( $post_id );
        if ( ! $type || ! is_singular( $type ) ) {
            return;
        }

        $image_urls = self::fifu_sd_collect_image_urls( (int) $post_id );

        if ( empty( $image_urls ) ) {
            return;
        }

        self::render_for_post( $post_id, $image_urls );
    }

    /**
     * Mirrors fifu_sd_maybe_photonize().
     *
     * @param mixed $image_urls
     * @param int   $post_id
     * @return mixed
     */
    private static function maybe_photonize_images( $image_urls, int $post_id ) {
        if ( Fifu_Options_Utils::is_off( 'fifu_photon' ) ) {
            return $image_urls;
        }

        $thumb_id = $post_id ? get_post_thumbnail_id( $post_id ) : null;
        if ( is_array( $image_urls ) ) {
            foreach ( $image_urls as $idx => $url ) {
                if ( empty( $url ) ) {
                    continue;
                }
                $image_urls[ $idx ] = Fifu_Jetpack_Cdn_Service::build_photon_url( $url, null, $thumb_id );
            }
            return $image_urls;
        }

        if ( ! empty( $image_urls ) ) {
            return Fifu_Jetpack_Cdn_Service::build_photon_url( $image_urls, null, $thumb_id );
        }

        return $image_urls;
    }

    /**
     * Collects image URLs preferring DB2 image sources and falling back to legacy meta.
     *
     * @param int $post_id
     * @return string[]
     */
    private static function fifu_sd_collect_image_urls( int $post_id ): array {
        $primary_url = self::fifu_sd_collect_primary_image_urls( $post_id );
        if ( ! empty( $primary_url ) ) {
            return $primary_url;
        }

        return self::fifu_sd_collect_legacy_image_urls( $post_id );
    }

    /**
     * Collect DB2 image URLs in canonical order.
     *
     * @param int $post_id
     * @return string[]
     */
    private static function fifu_sd_collect_db2_image_urls( int $post_id ): array {
        if ( ! function_exists( 'fifu_db2_manager' ) ) {
            return [];
        }

        $manager = fifu_db2_manager();
        if ( ! $manager instanceof Fifu_Db2_Manager || ! method_exists( $manager, 'getPostMapping' ) ) {
            return [];
        }

        $urls = [];

        $row = $manager->getPostMapping( $post_id, 'image', 0 );
        $url = self::fifu_sd_normalize_url( $row['url'] ?? null );
        if ( $url === null ) {
            $hash = trim( (string) ( $row['hash'] ?? '' ) );
            if ( $hash !== '' && method_exists( $manager, 'getUrlDetails' ) ) {
                $details = $manager->getUrlDetails( $hash );
                if ( is_array( $details ) ) {
                    $url = self::fifu_sd_normalize_url( $details['url'] ?? null );
                }
            }
        }
        if ( $url !== null ) {
            $urls[] = $url;
        }

        return self::fifu_sd_unique_urls( $urls );
    }

    /**
     * Collect the current effective primary image URL.
     *
     * @param int $post_id
     * @return string[]
     */
    private static function fifu_sd_collect_primary_image_urls( int $post_id ): array {
        $main_url = Fifu_Post_Main_Image_Resolver::get_main_image_url( $post_id, true );
        $main_url = self::fifu_sd_normalize_url( $main_url );
        if ( $main_url === null ) {
            return [];
        }

        return [ $main_url ];
    }

    /**
     * Collect legacy image URLs via the historical exact image key query.
     *
     * @param int $post_id
     * @return string[]
     */
    private static function fifu_sd_collect_legacy_image_urls( int $post_id ): array {
        global $wpdb;

        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->postmeta ) ) {
            return [];
        }

        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id = %d
               AND meta_key = %s
               AND meta_value IS NOT NULL
               AND TRIM(meta_value) <> ''
             LIMIT 1",
            $post_id,
            'fifu_image_url'
        ) );
        $url = self::fifu_sd_normalize_url( $value );
        return $url === null ? [] : [ $url ];
    }

    /**
     * Normalizes a candidate URL for structured data.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function fifu_sd_normalize_url( $value ): ?string {
        if ( ! is_scalar( $value ) && $value !== null ) {
            return null;
        }

        $url = trim( (string) $value );
        if ( $url === '' ) {
            return null;
        }

        $url = esc_url_raw( $url );
        if ( $url === '' ) {
            return null;
        }

        if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
            return null;
        }

        return $url;
    }

    /**
     * Removes duplicate URLs while preserving order.
     *
     * @param string[] $urls
     * @return string[]
     */
    private static function fifu_sd_unique_urls( array $urls ): array {
        $seen = [];
        $out = [];

        foreach ( $urls as $url ) {
            $url = self::fifu_sd_normalize_url( $url );
            if ( $url === null || isset( $seen[ $url ] ) ) {
                continue;
            }

            $seen[ $url ] = true;
            $out[] = $url;
        }

        return $out;
    }

    /**
     * Mirrors fifu_sd_build_image_objects().
     *
     * @param mixed  $images
     * @param string $page_url
     * @return array
     */
    private static function build_image_objects( $images, string $page_url ): array {
        $images = is_array( $images ) ? $images : ( empty( $images ) ? [] : [ $images ] );
        $seen = [];
        $out = [];
        foreach ( $images as $u ) {
            if ( empty( $u ) ) {
                continue;
            }
            if ( isset( $seen[ $u ] ) ) {
                continue;
            }
            $seen[ $u ] = true;
            $out[] = [
                '@type' => 'ImageObject',
                '@id' => $u,
                'url' => $u,
                'contentUrl' => $u,
                'mainEntityOfPage' => $page_url,
            ];
        }
        return $out;
    }

    /**
     * Mirrors fifu_sd_image_graph().
     *
     * @param mixed  $images
     * @param string $page_url
     * @return array|null
     */
    private static function build_image_graph( $images, string $page_url ): ?array {
        $graph = self::build_image_objects( $images, $page_url );
        if ( empty( $graph ) ) {
            return null;
        }
        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * Mirrors fifu_sd_description().
     *
     * @param int $post_id
     * @return string|null
     */
    private static function get_description( int $post_id ): ?string {
        if ( ! $post_id ) {
            return null;
        }
        $desc = get_post_field( 'post_excerpt', $post_id );
        if ( ! $desc ) {
            $desc = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
        }
        $desc = trim( preg_replace( '/\s+/', ' ', (string) $desc ) );
        if ( strlen( $desc ) > 320 ) {
            $desc = mb_substr( $desc, 0, 320 );
        }
        return $desc ?: null;
    }

    /**
     * Mirrors fifu_sd_image_plain_list().
     *
     * @param mixed $images
     * @return array
     */
    private static function build_image_plain_list( $images ): array {
        $images = is_array( $images ) ? $images : ( empty( $images ) ? [] : [ $images ] );
        $out = [];
        foreach ( $images as $u ) {
            if ( empty( $u ) ) {
                continue;
            }
            $out[] = [
                '@type' => 'ImageObject',
                'url' => $u,
            ];
        }
        return $out;
    }

    /**
     * Mirrors fifu_sd_product_category().
     *
     * @param int $post_id
     * @return string|null
     */
    private static function get_product_category( int $post_id ): ?string {
        $terms = get_the_terms( $post_id, 'product_cat' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }
        $term = $terms[0];
        return is_object( $term ) ? ( $term->name ?? null ) : null;
    }

    /**
     * Mirrors the Product rendering portion of includes/structured-data.php.
     *
     * @param array $base
     * @param int   $post_id
     * @return void
     */
    private static function render_product( array $base, int $post_id ): void {
        $page_url = get_permalink( $post_id );
        $name = get_the_title( $post_id );

        $json_ld = $base;
        $json_ld['@type'] = 'Product';
        $json_ld['name'] = $name;

        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post_id );
            if ( $product ) {
                $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
                $has_valid_offer = false;

                if (
                    $product->is_type( 'variable' )
                    || $product->is_type( 'grouped' )
                ) {
                    return;
                }

                $price = $product->get_price();
                if ( $price === '' || $price === null ) {
                    $price = $product->get_regular_price();
                }
                if ( $price === '' || $price === null ) {
                    $price = $product->get_sale_price();
                }
                if ( $price === '' || $price === null ) {
                    $price = get_post_meta( $post_id, '_price', true );
                }

                $availability = $product->is_in_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock';

                if ( $price !== '' && $price !== null ) {
                    $offer = [
                        '@type' => 'Offer',
                        'url' => $page_url,
                        'price' => $price,
                        'priceCurrency' => $currency,
                        'availability' => $availability,
                    ];

                    if ( method_exists( $product, 'get_date_on_sale_to' ) ) {
                        $sale_to = $product->get_date_on_sale_to();

                        if ( $sale_to ) {
                            $json = $sale_to->date_i18n( 'c' );

                            if ( $json ) {
                                $offer['priceValidUntil'] = $json;
                            }
                        }
                    }

                    $json_ld['offers'] = [ $offer ];
                    $has_valid_offer = true;
                }

                $product_node = [
                    '@type' => 'Product',
                    'name' => $name,
                    'url' => $page_url,
                    'mainEntityOfPage' => $page_url,
                    'image' => self::build_image_plain_list( $json_ld['image'] ),
                ];

                $p_desc = self::get_description( $post_id );
                if ( $p_desc ) {
                    $product_node['description'] = $p_desc;
                }
                if ( method_exists( $product, 'get_sku' ) ) {
                    $sku = $product->get_sku();
                    if ( ! empty( $sku ) ) {
                        $product_node['sku'] = $sku;
                    }
                }
                if ( method_exists( $product, 'get_attribute' ) ) {
                    $brand = $product->get_attribute( 'pa_brand' );
                    if ( ! $brand ) {
                        $brand = $product->get_attribute( 'brand' );
                    }
                    if ( ! empty( $brand ) ) {
                        $product_node['brand'] = trim( wp_strip_all_tags( $brand ) );
                    }
                }
                $category = self::get_product_category( $post_id );
                if ( $category ) {
                    $product_node['category'] = $category;
                }
                if ( ! empty( $json_ld['offers'] ) ) {
                    $product_node['offers'] = $json_ld['offers'];
                }
                $p = $product;
                if ( $p ) {
                    $rating_count = (int) $p->get_rating_count();
                    $average = (float) $p->get_average_rating();
                    if ( $rating_count > 0 && $average > 0 ) {
                        $product_node['aggregateRating'] = [
                            '@type' => 'AggregateRating',
                            'ratingValue' => (string) number_format( $average, 1, '.', '' ),
                            'ratingCount' => $rating_count,
                            'bestRating' => '5',
                            'worstRating' => '1',
                        ];
                    }
                }

                if ( empty( $product_node['review'] ) ) {
                    $comments = get_comments( [
                        'post_id' => $post_id,
                        'status' => 'approve',
                        'number' => 2,
                        'meta_key' => 'rating',
                        'orderby' => 'comment_date_gmt',
                        'order' => 'DESC',
                    ] );
                    $reviews = [];
                    foreach ( (array) $comments as $c ) {
                        $rating = get_comment_meta( $c->comment_ID, 'rating', true );
                        if ( ! $rating ) {
                            continue;
                        }
                        $rev = [
                            '@type' => 'Review',
                            'author' => [
                                '@type' => 'Person',
                                'name' => get_comment_author( $c ),
                            ],
                            'datePublished' => mysql2date( 'c', $c->comment_date_gmt ),
                            'reviewRating' => [
                                '@type' => 'Rating',
                                'ratingValue' => (string) $rating,
                                'bestRating' => '5',
                                'worstRating' => '1',
                            ],
                        ];
                        $content = trim( wp_strip_all_tags( $c->comment_content ) );
                        if ( $content ) {
                            $rev['reviewBody'] = $content;
                        }
                        $reviews[] = $rev;
                    }
                    if ( ! empty( $reviews ) ) {
                        $product_node['review'] = $reviews;
                    }
                }

                if ( empty( $product_node['offers'] ) && empty( $product_node['aggregateRating'] ) ) {
                    $product_node['offers'] = [
                        '@type' => 'Offer',
                        'url' => $page_url,
                        'price' => '0',
                        'priceCurrency' => $currency,
                        'availability' => 'https://schema.org/OutOfStock',
                    ];
                }

                $payload = array_merge( [ '@context' => 'https://schema.org' ], $product_node );
                self::output_payload( $payload );
                return;
            }
        }

        $payload = self::build_image_graph( $json_ld['image'], $page_url );
        if ( $payload ) {
            self::output_payload( $payload );
        }
    }

    /**
     * Mirrors the BlogPosting rendering portion of includes/structured-data.php.
     *
     * @param array $base
     * @param int   $post_id
     * @return void
     */
    private static function render_article( array $base, int $post_id ): void {
        $page_url = get_permalink( $post_id );
        $name = get_the_title( $post_id );
        $date_published = get_post_time( 'c', true, $post_id );
        $date_modified = get_post_modified_time( 'c', true, $post_id );
        if ( empty( $date_published ) ) {
            return;
        }
        $author_id = (int) get_post_field( 'post_author', $post_id );
        $author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
        $author_url = $author_id ? get_author_posts_url( $author_id ) : '';

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $name,
            'url' => $page_url,
            'mainEntityOfPage' => $page_url,
            'image' => self::build_image_plain_list( $base['image'] ?? [] ),
        ];
        if ( $date_published ) {
            $article['datePublished'] = $date_published;
        }
        if ( $date_modified ) {
            $article['dateModified'] = $date_modified;
        }
        if ( $author_name ) {
            $article['author'] = [
                '@type' => 'Person',
                'name' => $author_name,
            ];
            if ( $author_url ) {
                $article['author']['url'] = $author_url;
            }
        }

        self::output_payload( $article );
    }

    /**
     * Outputs the JSON-LD payload with FIFU markers.
     *
     * @param array $payload
     * @return void
     */
    private static function output_payload( array $payload ): void {
        echo "\n<!-- FIFU:jsonld:begin -->\n";
        echo "<script type=\"application/ld+json\">" . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) . "</script>\n";
        echo "<!-- FIFU:jsonld:end -->\n";
    }
}
