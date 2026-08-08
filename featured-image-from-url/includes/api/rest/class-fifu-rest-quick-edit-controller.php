<?php

if (!class_exists('Fifu_Quick_Edit_Read_Service', false)) {
    require_once dirname(__DIR__, 3) . '/admin/columns/class-fifu-quick-edit-read-service.php';
}

class Fifu_Rest_Quick_Edit_Controller {
    private const QUICK_EDIT_DEBUG_MAX_FIELD_CHARS = 2048;

    public static function register_routes(): void {
        register_rest_route(self::quick_edit_rest_namespace(), '/quick_edit_save_api/', array(
            'methods'             => 'POST',
            'callback'            => array(self::class, 'handle_quick_edit_save'),
            'permission_callback' => [Fifu_Rest_Permissions::class, 'private_data'],
        ));

        register_rest_route(self::quick_edit_rest_namespace(), '/quick_edit_debug_data_api/', array(
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle_quick_edit_debug_data'],
            'permission_callback' => [Fifu_Rest_Permissions::class, 'private_data'],
        ));
        register_rest_route(self::quick_edit_rest_namespace(), '/quick_edit_variations_api/', array(
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle_quick_edit_variations'],
            'permission_callback' => [Fifu_Rest_Permissions::class, 'quick_edit_read'],
            'args'                => array(
                'parent_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        register_rest_route(self::quick_edit_rest_namespace(), '/quick_edit_item_api/', array(
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle_quick_edit_item'],
            'permission_callback' => [Fifu_Rest_Permissions::class, 'quick_edit_read'],
            'args'                => array(
                'post_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }

    private static function quick_edit_rest_namespace(): string
    {
        return FIFU_REST_NAMESPACE_V2;
    }

    public static function handle_quick_edit_save(WP_REST_Request $request): WP_REST_Response {
        $post_id = (int) $request->get_param('post_id');
        $is_ctgr = in_array($request->get_param('is_ctgr'), [true, 1, '1', 'true'], true);
        $request_params = $request->get_params();
        $has_image_url = array_key_exists('image_url', $request_params);
        $image_url_raw = $request->get_param('image_url');
        $image_url = is_string($image_url_raw) ? trim($image_url_raw) : trim((string) $image_url_raw);
        $gallery_length = (int) $request->get_param('gallery_length');
        $gallery_urls = $request->get_param('gallery_urls');

        if ($post_id > 0 && get_post_type($post_id) === 'product_variation') {
            return new WP_REST_Response([
                'code' => 'fifu_quick_edit_variation_save_blocked',
                'message' => 'Product variation editing is not available in FIFU Free.',
            ], 403);
        }

        if ($gallery_length > 0 && is_array($gallery_urls) && count($gallery_urls) > 0) {
            return new WP_REST_Response([
                'code' => 'fifu_quick_edit_gallery_pro_only',
                'message' => 'Product Gallery is available in FIFU PRO.',
            ], 403);
        }

        if ($has_image_url) {
            $ok = false;
            if ($is_ctgr) {
                $ok = Fifu_Developer_Media_Service::set_category_image($post_id, $image_url);
                if ($ok && $image_url !== '') {
                    self::sync_category_image_dimensions($post_id, $request);
                    self::sync_category_image_alt($post_id, $image_url);
                }
            } else {
                $ok = Fifu_Developer_Media_Service::set_image($post_id, $image_url);
                if ($ok && $image_url !== '') {
                    self::sync_post_image_dimensions($post_id, $request);
                    self::sync_post_image_alt($post_id, $image_url);
                }
            }

            if (!$ok) {
                return new WP_REST_Response([
                    'code' => 'fifu_quick_edit_image_save_failed',
                    'message' => 'Unable to save featured image.',
                ], 500);
            }
        }

        if (class_exists('Distributor\\Connections', false)) {
            wp_update_post(['ID' => $post_id]);
        }

        return new WP_REST_Response(array('thumb_url' => $image_url), 200);
    }

    public static function handle_quick_edit_debug_data(WP_REST_Request $request) {
        if (!Fifu_Options_Utils::is_on('fifu_debug')) return new WP_Error('fifu_debug_disabled', 'FIFU Debug is disabled.', ['status' => 403]);
        if (self::normalize_quick_edit_debug_is_category($request->get_param('is_ctgr'))) {
            $term_id=(int)$request->get_param('post_id');
            if ($term_id<=0) return new WP_Error('fifu_invalid_term_id','Invalid term_id.',['status'=>400]);
            $taxonomy=trim((string)$request->get_param('taxonomy'));
            $term=$taxonomy!==''?get_term($term_id,$taxonomy):get_term($term_id);
            if (!$term||is_wp_error($term)) return new WP_Error('fifu_invalid_term_id','Invalid term_id.',['status'=>400]);
            return new WP_REST_Response(self::build_quick_edit_debug_term_response($term_id,(string)$term->taxonomy),200);
        }
        $post_id=(int)$request->get_param('post_id');
        if ($post_id<=0||!get_post($post_id)) return new WP_Error('fifu_invalid_post_id','Invalid post_id.',['status'=>400]);
        $root_post = get_post($post_id);
        $post_type = $root_post ? (string) $root_post->post_type : '';
        $children=get_posts(['post_type'=>'product_variation','post_parent'=>$post_id,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
        $variations=[]; foreach($children as $child_id) $variations[]=self::build_quick_edit_debug_item((int)$child_id);

        if ($children) {
            $scope = 'product_context';
        } elseif ($post_type === 'product') {
            $scope = 'product';
        } else {
            $scope = 'post';
        }

        return new WP_REST_Response(['type'=>'quick_edit_debug_data','scope'=>$scope,'root_post_id'=>$post_id,'root_item'=>self::build_quick_edit_debug_item($post_id),'product_variations'=>$variations],200);
    }

    private static function sanitize_quick_edit_debug_post($post): ?array {
        if (!$post) return null;
        $post_data = (array) $post;
        unset($post_data['post_password']);
        return $post_data;
    }

    private static function build_quick_edit_debug_item(int $post_id): array {
        $post = get_post($post_id);
        $meta = self::sanitize_quick_edit_debug_meta_array(get_post_meta($post_id));
        $legacy = array_filter($meta, static function ($key) {
            return strpos((string) $key, 'fifu_') === 0;
        }, ARRAY_FILTER_USE_KEY);
        $thumbnail_values = array_map('strval', (array) get_post_meta($post_id, '_thumbnail_id', false));
        $thumbnail_id = self::select_first_valid_attachment_id($thumbnail_values);
        $attachment = $thumbnail_id > 0 ? get_post($thumbnail_id) : null;
        $manager = function_exists('fifu_db2_manager') ? fifu_db2_manager() : null;
        $db2_image_mappings = is_object($manager) && method_exists($manager, 'getPostMappings')
            ? (array) $manager->getPostMappings($post_id, 'image')
            : [];
        $resolved_image_url = Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);
        $resolved_image_source = self::resolve_quick_edit_debug_image_source(
            $resolved_image_url,
            $db2_image_mappings,
            $legacy
        );

        return [
            'post' => self::sanitize_quick_edit_debug_post($post),
            'wordpress_postmeta' => $meta,
            'fifu_legacy_postmeta' => $legacy,
            'wordpress_thumbnail_reference' => [
                'thumbnail_id_meta_values' => array_values($thumbnail_values),
                'wordpress_thumbnail_id' => (int) get_post_thumbnail_id($post_id),
                'inspected_attachment_id' => $thumbnail_id,
            ],
            'wordpress_thumbnail_attachment' => [
                'post' => self::sanitize_quick_edit_debug_post($attachment),
                'postmeta' => $thumbnail_id > 0
                    ? self::sanitize_quick_edit_debug_meta_array(get_post_meta($thumbnail_id))
                    : [],
            ],
            'fifu_db2_storage' => [
                'image' => $db2_image_mappings,
            ],
            'resolved_media' => [
                'featured_image' => [
                    'url' => $resolved_image_url,
                    'source' => $resolved_image_source,
                ],
            ],
        ];
    }

    private static function build_quick_edit_debug_term_response(int $term_id,string $taxonomy): array {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return ['type'=>'quick_edit_debug_data','scope'=>'term','root_term_id'=>$term_id,'taxonomy'=>$taxonomy,'term'=>null,'wordpress_termmeta'=>[],'fifu_legacy_termmeta'=>[],'wordpress_thumbnail_reference'=>['thumbnail_id_meta_values'=>[],'inspected_attachment_id'=>0],'wordpress_thumbnail_attachment'=>['post'=>null,'postmeta'=>[]],'fifu_db2_storage'=>['image'=>[]],'resolved_media'=>['featured_image'=>['url'=>null,'source'=>null]]];
        }

        $meta = self::sanitize_quick_edit_debug_meta_array(get_term_meta($term_id));
        $legacy = array_filter($meta, static function ($key) {
            return strpos((string) $key, 'fifu_') === 0;
        }, ARRAY_FILTER_USE_KEY);
        $thumbnail_values = array_map('strval', (array) get_term_meta($term_id, 'thumbnail_id', false));
        $thumbnail_id = self::select_first_valid_attachment_id($thumbnail_values);
        $attachment = $thumbnail_id > 0 ? get_post($thumbnail_id) : null;
        $manager = function_exists('fifu_db2_manager') ? fifu_db2_manager() : null;
        $db2_image_mappings = is_object($manager) && method_exists($manager, 'getTermMapping')
            ? self::wrap_quick_edit_debug_term_mapping($manager->getTermMapping($term_id, 'image'))
            : [];
        $resolved_image_url = Fifu_Term_Image_Url_Read_Service::get_image_url($term_id);
        $resolved_image_source = self::resolve_quick_edit_debug_image_source(
            $resolved_image_url,
            $db2_image_mappings,
            $legacy
        );

        return ['type'=>'quick_edit_debug_data','scope'=>'term','root_term_id'=>$term_id,'taxonomy'=>$term->taxonomy,'term'=>(array)$term,'wordpress_termmeta'=>$meta,'fifu_legacy_termmeta'=>$legacy,'wordpress_thumbnail_reference'=>['thumbnail_id_meta_values'=>array_values($thumbnail_values),'inspected_attachment_id'=>$thumbnail_id],'wordpress_thumbnail_attachment'=>['post'=>self::sanitize_quick_edit_debug_post($attachment),'postmeta'=>$thumbnail_id>0?self::sanitize_quick_edit_debug_meta_array(get_post_meta($thumbnail_id)):[]],'fifu_db2_storage'=>['image'=>$db2_image_mappings],'resolved_media'=>['featured_image'=>['url'=>$resolved_image_url,'source'=>$resolved_image_source]]];
    }

    private static function resolve_quick_edit_debug_image_source(?string $resolved_url, array $db2_image_mappings, array $legacy_meta): ?string {
        $resolved_url = $resolved_url !== null ? trim($resolved_url) : '';

        if ($resolved_url === '') {
            return null;
        }

        foreach ($db2_image_mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $key_index = array_key_exists('key_index', $mapping) ? (int) $mapping['key_index'] : 0;
            if ($key_index !== 0) {
                continue;
            }

            $db2_url = trim((string) ($mapping['url'] ?? ''));
            if ($db2_url !== '' && $db2_url === $resolved_url) {
                return 'fifu_db2';
            }
        }

        foreach ((array) ($legacy_meta['fifu_image_url'] ?? []) as $legacy_url) {
            if (trim((string) $legacy_url) === $resolved_url) {
                return 'fifu_legacy_postmeta';
            }
        }

        return 'unknown';
    }

    private static function sanitize_quick_edit_debug_meta_array(array $meta): array {
        $allow=['_thumbnail_id','thumbnail_id','_product_image_gallery','_wc_additional_variation_images','_wp_attached_file','_wp_attachment_metadata','_wp_attachment_image_alt','fifu_image_url','fifu_image_alt','fifu_image_url_0','fifu_list_url']; $patterns=['key','secret','token','password','license','consumer_secret','access_token'];
        foreach($meta as $key=>&$values){$k=(string)$key; if(!in_array($k,$allow,true)){foreach($patterns as $pattern){if(strpos(strtolower($k),$pattern)!==false){$values=array_fill(0,is_array($values)?count($values):1,'[redacted]');break;}}} if(!self::should_preserve_long_quick_edit_debug_field($k)) $values=self::crop_quick_edit_debug_value($values);} unset($values); return $meta;
    }
    private static function should_preserve_long_quick_edit_debug_field(string $key): bool { return strpos($key,'fifu')===0||strpos($key,'_')===0; }
    private static function crop_quick_edit_debug_value($value) { if(is_array($value)){foreach($value as $i=>$item)$value[$i]=self::crop_quick_edit_debug_value($item);return $value;} if(!is_string($value))return $value; $len=function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); if($len<=self::QUICK_EDIT_DEBUG_MAX_FIELD_CHARS)return $value; $cut=function_exists('mb_substr')?mb_substr($value,0,self::QUICK_EDIT_DEBUG_MAX_FIELD_CHARS,'UTF-8'):substr($value,0,self::QUICK_EDIT_DEBUG_MAX_FIELD_CHARS); return $cut.'...[cropped from '.$len.' chars]'; }
    private static function select_first_valid_attachment_id(array $values): int { foreach($values as $value){$id=(int)$value; $post=$id>0?get_post($id):null; if($post&&($post->post_type??'')==='attachment')return $id;} return 0; }
    private static function wrap_quick_edit_debug_term_mapping($mapping): array { return is_array($mapping)?[$mapping]:[]; }
    private static function normalize_quick_edit_debug_is_category($value): bool { return $value===true||$value==='1'||$value==='true'||$value===1; }

    public static function handle_quick_edit_variations(WP_REST_Request $request): WP_REST_Response {
        $parent_id = absint($request->get_param('parent_id'));
        $post = $parent_id > 0 ? get_post($parent_id) : null;
        $product = $parent_id > 0 && function_exists('wc_get_product') ? wc_get_product($parent_id) : null;

        if (!$post || !$product) {
            return new WP_REST_Response([
                'code' => 'fifu_quick_edit_parent_not_found',
                'message' => 'The variable product could not be found.',
            ], 404);
        }

        $is_variable = method_exists($product, 'is_type') && $product->is_type('variable');
        if (!$is_variable && method_exists($product, 'get_type')) {
            $is_variable = $product->get_type() === 'variable';
        }
        if (!$is_variable) {
            return new WP_REST_Response([
                'code' => 'fifu_quick_edit_parent_not_variable',
                'message' => 'The post is not a variable product.',
            ], 400);
        }

        $variation_ids = array_values(array_filter(array_map('intval', (array) $product->get_children())));
        $attribute_map = Fifu_Product_Variation_Attributes_Service::get_pretty_variation_attributes_map($parent_id);
        $variations = [];

        foreach ($variation_ids as $variation_id) {
            $variations[] = [
                'post_id' => $variation_id,
                'attributes' => $attribute_map[$variation_id] ?? [],
                'display' => Fifu_Quick_Edit_Read_Service::get_display($variation_id),
            ];
        }

        return new WP_REST_Response([
            'parent_id' => $parent_id,
            'title' => method_exists($product, 'get_title') ? (string) $product->get_title() : '',
            'variations' => $variations,
        ], 200);
    }

    public static function handle_quick_edit_item(WP_REST_Request $request): WP_REST_Response {
        $post_id = absint($request->get_param('post_id'));
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post) {
            return new WP_REST_Response([
                'code' => 'fifu_quick_edit_item_not_found',
                'message' => 'The Quick Edit item could not be found.',
            ], 404);
        }

        return new WP_REST_Response(Fifu_Quick_Edit_Read_Service::get_item_response($post_id), 200);
    }

    private static function sync_category_image_dimensions(int $term_id, WP_REST_Request $request): void {
        $thumbnail_id = (int) get_term_meta($term_id, 'thumbnail_id', true);
        $width = (int) $request->get_param('width');
        $height = (int) $request->get_param('height');

        if ($thumbnail_id > 0 && $width > 0 && $height > 0) {
            Fifu_Attachment_Dimensions_Service::update_dimensions($thumbnail_id, $width, $height);
        }
    }

    private static function sync_post_image_dimensions(int $post_id, WP_REST_Request $request): void {
        $attachment_id = (int) get_post_thumbnail_id($post_id);
        $width = (int) $request->get_param('width');
        $height = (int) $request->get_param('height');

        if ($attachment_id > 0 && $width > 0 && $height > 0) {
            Fifu_Attachment_Dimensions_Service::update_dimensions($attachment_id, $width, $height);
        }
    }

    private static function sync_category_image_alt(int $term_id, string $image_url): void {
        $alt = (string) get_term_meta($term_id, 'fifu_image_alt', true);
        if ($alt !== '') {
            if (class_exists('Fifu_Db2_Manager', false)) {
                $manager = self::db2_manager();
                $manager->saveTermAlt($term_id, 'image', $alt);
            }

            delete_term_meta($term_id, 'fifu_image_alt');
        }

        $mapping = class_exists('Fifu_Db2_Manager', false) ? self::db2_manager()->getTermMapping($term_id, 'image') : null;
        if (is_array($mapping) && (string) ($mapping['url'] ?? '') === $image_url) {
            delete_term_meta($term_id, 'fifu_image_url');
            delete_term_meta($term_id, 'fifu_image_alt');
        }
    }

    private static function sync_post_image_alt(int $post_id, string $image_url): void {
        $alt = (string) get_post_meta($post_id, 'fifu_image_alt', true);
        if ($alt !== '') {
            if (class_exists('Fifu_Db2_Manager', false)) {
                $manager = self::db2_manager();
                $manager->savePostAlt($post_id, 'image', 0, $alt);
            }

            delete_post_meta($post_id, 'fifu_image_alt');
        }
    }

    private static function db2_manager(): Fifu_Db2_Manager
    {
        return fifu_db2_manager();
    }
}
