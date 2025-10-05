<?php

// Render minimal structured data for posts and products.
// Behavior:
// - When NO SEO plugin is active: emit minimal BlogPosting/Product JSON-LD (enough for Rich Results).
// - When an SEO plugin IS active: emit ONLY ImageObject data to complement SEO output and avoid conflicts.
// Tweaks:
// - Use https://schema.org enumerations (required by Google)
// - Add mainEntityOfPage
// - Avoid emitting null JSON-LD payloads

function fifu_render_structured_data($post_id, $image_urls) {
    if (!$post_id)
        return;

    // Only on singular screens for the current post type
    $type = get_post_type($post_id);
    if (!is_singular($type))
        return;

    $page_url = get_permalink($post_id);
    $name = get_the_title($post_id);

    $image_urls = fifu_sd_maybe_photonize($image_urls, $post_id);

    // Build base payload
    $json_ld = [
        '@context' => 'https://schema.org',
        'post_id' => $post_id,
        'url' => $page_url,
        'image' => is_array($image_urls) ? $image_urls : (empty($image_urls) ? [] : [$image_urls]),
    ];

    // Decide whether to defer to SEO plugins
    $seo_active = function_exists('fifu_is_any_seo_plugin_active') ? fifu_is_any_seo_plugin_active() : false;

    // If an SEO plugin is active, only output image objects to complement their schema
    if ($seo_active) {
        $payload = fifu_sd_image_graph($json_ld['image'], $page_url);
        if ($payload) {
            echo "\n<!-- FIFU:jsonld:begin -->\n";
            echo "<script type=\"application/ld+json\">" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES) . "</script>\n";
            echo "<!-- FIFU:jsonld:end -->\n";
        }
        return;
    }

    if ($type === 'product') {
        $json_ld['@type'] = 'Product';
        $json_ld['name'] = $name;

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
                $has_valid_offer = false;

                if ($product->is_type('variable')) {
                    return;
                } elseif ($product->is_type('grouped')) {
                    return;
                } else {
                    // Simple product minimal Offer
                    $price = $product->get_price();
                    if ($price === '' || $price === null)
                        $price = $product->get_regular_price();
                    if ($price === '' || $price === null)
                        $price = $product->get_sale_price();
                    if ($price === '' || $price === null)
                        $price = get_post_meta($post_id, '_price', true);
                    $availability = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
                    if ($price !== '' && $price !== null) {
                        $offer = [
                            '@type' => 'Offer',
                            'url' => $page_url,
                            'price' => $price,
                            'priceCurrency' => $currency,
                            'availability' => $availability,
                        ];
                        if (method_exists($product, 'get_date_on_sale_to')) {
                            $sale_to = $product->get_date_on_sale_to();
                            if ($sale_to) {
                                $json = $sale_to->date_i18n('c');
                                if ($json)
                                    $offer['priceValidUntil'] = $json;
                            }
                        }
                        $json_ld['offers'] = [$offer];
                        $has_valid_offer = true;
                    }
                }

                // Build minimal Product node (no @graph scaffolding)
                $product_node = [
                    '@type' => 'Product',
                    'name' => $name,
                    'url' => $page_url,
                    'mainEntityOfPage' => $page_url,
                    'image' => fifu_sd_image_plain_list($json_ld['image']),
                ];
                // Optional description from excerpt/content if available
                $p_desc = fifu_sd_description($post_id);
                if ($p_desc) {
                    $product_node['description'] = $p_desc;
                }
                // Optional identifiers
                if (method_exists($product, 'get_sku')) {
                    $sku = $product->get_sku();
                    if (!empty($sku))
                        $product_node['sku'] = $sku;
                }
                // Best-effort brand from common attribute slugs
                if (method_exists($product, 'get_attribute')) {
                    $brand = $product->get_attribute('pa_brand');
                    if (!$brand)
                        $brand = $product->get_attribute('brand');
                    if (!empty($brand))
                        $product_node['brand'] = trim(wp_strip_all_tags($brand));
                }
                // Add category if available
                $category = fifu_sd_product_category($post_id);
                if ($category) {
                    $product_node['category'] = $category;
                }
                if (!empty($json_ld['offers'])) {
                    $product_node['offers'] = $json_ld['offers'];
                }
                // Add aggregateRating when WooCommerce ratings exist (even if offers exist)
                if (function_exists('wc_get_product')) {
                    $p = wc_get_product($post_id);
                    if ($p) {
                        $rating_count = (int) $p->get_rating_count();
                        $average = (float) $p->get_average_rating();
                        if ($rating_count > 0 && $average > 0) {
                            $product_node['aggregateRating'] = [
                                '@type' => 'AggregateRating',
                                'ratingValue' => (string) number_format($average, 1, '.', ''),
                                'ratingCount' => $rating_count,
                                'bestRating' => '5',
                                'worstRating' => '1',
                            ];
                        }
                    }
                }
                // Add a couple of recent approved WooCommerce reviews when available
                if (empty($product_node['review'])) {
                    $comments = get_comments([
                        'post_id' => $post_id,
                        'status' => 'approve',
                        'number' => 2,
                        'meta_key' => 'rating',
                        'orderby' => 'comment_date_gmt',
                        'order' => 'DESC',
                    ]);
                    $reviews = [];
                    foreach ((array) $comments as $c) {
                        $rating = get_comment_meta($c->comment_ID, 'rating', true);
                        if (!$rating)
                            continue;
                        $rev = [
                            '@type' => 'Review',
                            'author' => [
                                '@type' => 'Person',
                                'name' => get_comment_author($c),
                            ],
                            'datePublished' => mysql2date('c', $c->comment_date_gmt),
                            'reviewRating' => [
                                '@type' => 'Rating',
                                'ratingValue' => (string) $rating,
                                'bestRating' => '5',
                                'worstRating' => '1',
                            ],
                        ];
                        $content = trim(wp_strip_all_tags($c->comment_content));
                        if ($content)
                            $rev['reviewBody'] = $content;
                        $reviews[] = $rev;
                    }
                    if (!empty($reviews)) {
                        $product_node['review'] = $reviews;
                    }
                }

                // Final fallback: if still no offers and no ratings, emit a minimal unavailable Offer
                if (empty($product_node['offers']) && empty($product_node['aggregateRating'])) {
                    $product_node['offers'] = [
                        '@type' => 'Offer',
                        'url' => $page_url,
                        'price' => '0',
                        'priceCurrency' => $currency,
                        'availability' => 'https://schema.org/OutOfStock',
                    ];
                }

                // Keep minimal: no shippingDetails or hasMerchantReturnPolicy enrichment

                $payload = array_merge(['@context' => 'https://schema.org'], $product_node);

                echo "\n<!-- FIFU:jsonld:begin -->\n";
                echo "<script type=\"application/ld+json\">" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES) . "</script>\n";
                echo "<!-- FIFU:jsonld:end -->\n";
                return;
            }
        }
        // If we reach here (WooCommerce missing), emit image-only fallback
        $payload = fifu_sd_image_graph($json_ld['image'], $page_url);
        if ($payload) {
            echo "\n<!-- FIFU:jsonld:begin -->\n";
            echo "<script type=\"application/ld+json\">" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES) . "</script>\n";
            echo "<!-- FIFU:jsonld:end -->\n";
        }
        return;
    }

    // Posts/pages
    $json_ld['@type'] = 'BlogPosting';
    $json_ld['headline'] = $name;
    // Build minimal BlogPosting (no @graph scaffolding)
    $datePublished = get_post_time('c', true, $post_id);
    $dateModified = get_post_modified_time('c', true, $post_id);
    // If we don't have a publish date, don't emit structured data for posts
    if (empty($datePublished)) {
        return;
    }
    $author_id = (int) get_post_field('post_author', $post_id);
    $author_name = $author_id ? get_the_author_meta('display_name', $author_id) : '';
    $author_url = $author_id ? get_author_posts_url($author_id) : '';

    $article = [
        '@type' => 'BlogPosting',
        'headline' => $name,
        'url' => $page_url,
        'mainEntityOfPage' => $page_url,
        'image' => fifu_sd_image_plain_list($json_ld['image']),
    ];
    if ($datePublished)
        $article['datePublished'] = $datePublished;
    if ($dateModified)
        $article['dateModified'] = $dateModified;
    if ($author_name) {
        $article['author'] = [
            '@type' => 'Person',
            'name' => $author_name,
        ];
        if ($author_url)
            $article['author']['url'] = $author_url;
    }
    $payload = array_merge(['@context' => 'https://schema.org'], $article);

    echo "\n<!-- FIFU:jsonld:begin -->\n";
    echo "<script type=\"application/ld+json\">" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES) . "</script>\n";
    echo "<!-- FIFU:jsonld:end -->\n";
}

function fifu_sd_maybe_photonize($image_urls, $post_id) {
    if (fifu_is_off('fifu_photon'))
        return $image_urls;

    $thumb_id = $post_id ? get_post_thumbnail_id($post_id) : null;
    if (is_array($image_urls)) {
        foreach ($image_urls as $idx => $url) {
            if (empty($url))
                continue;
            $image_urls[$idx] = fifu_jetpack_photon_url($url, null, $thumb_id);
        }
        return $image_urls;
    }

    if (!empty($image_urls))
        return fifu_jetpack_photon_url($image_urls, null, $thumb_id);

    return $image_urls;
}

function fifu_sd_build_image_objects($images, $page_url) {
    $images = is_array($images) ? $images : (empty($images) ? [] : [$images]);
    $seen = [];
    $out = [];
    foreach ($images as $u) {
        if (empty($u))
            continue;
        if (isset($seen[$u]))
            continue;
        $seen[$u] = true;
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

function fifu_sd_image_graph($images, $page_url) {
    $graph = fifu_sd_build_image_objects($images, $page_url);
    if (empty($graph))
        return null;
    return [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
}

// Derive a concise description for the post/page
function fifu_sd_description($post_id) {
    if (!$post_id)
        return null;
    $desc = get_post_field('post_excerpt', $post_id);
    if (!$desc)
        $desc = wp_strip_all_tags(get_post_field('post_content', $post_id));
    $desc = trim(preg_replace('/\s+/', ' ', (string) $desc));
    if (strlen($desc) > 320)
        $desc = mb_substr($desc, 0, 320);
    return $desc ?: null;
}

// Convert URLs into a minimal ImageObject list for embedding in Product/Article nodes
function fifu_sd_image_plain_list($images) {
    $images = is_array($images) ? $images : (empty($images) ? [] : [$images]);
    $out = [];
    foreach ($images as $u) {
        if (empty($u))
            continue;
        $out[] = [
            '@type' => 'ImageObject',
            'url' => $u,
        ];
    }
    return $out;
}

// Get a simple category label for products (first assigned term)
function fifu_sd_product_category($post_id) {
    $terms = get_the_terms($post_id, 'product_cat');
    if (is_wp_error($terms) || empty($terms))
        return null;
    $term = $terms[0];
    return is_object($term) ? ($term->name ?? null) : null;
}

