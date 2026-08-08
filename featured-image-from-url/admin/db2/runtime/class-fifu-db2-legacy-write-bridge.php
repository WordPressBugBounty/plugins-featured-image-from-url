<?php
declare(strict_types=1);

/**
 * Bridge between legacy meta keys and the new db2 write service.
 */
class Fifu_Db2_Legacy_Write_Bridge {
    private Fifu_Db2_Write_Service $write_service;

    public function __construct(Fifu_Db2_Write_Service $write_service) {
        $this->write_service = $write_service;
    }


    public function handle_post_meta_change(int $post_id, string $meta_key, ?string $url): bool {
        try {
            if ($this->is_removed_phase_five_meta_key($meta_key)) {
                return true;
            }
            if ($this->is_removed_phase_four_meta_key($meta_key)) {
                return true;
            }
            if ($meta_key === 'fifu_image_url') {
                if ($this->is_empty_url($url)) {
                    if (!$this->db2_key_type_exists('image')) {
                        return false;
                    }
                    $manager = fifu_db2_manager();
                    $mapping = $manager->getPostMapping($post_id, 'image', 0);
                    if (!is_array($mapping)) {
                        return true;
                    }
                    return $this->write_service->delete_post_image($post_id, 0) > 0
                        && !is_array($manager->getPostMapping($post_id, 'image', 0));
                }

                $ok = $this->write_service->save_post_image($post_id, $url);
                if (!$ok) {
                    $this->log_db2_write_returned_false('post', $post_id, $meta_key, 0, $url);
                }
                return $ok;
            }

            if ($meta_key === 'fifu_image_alt') {
                if ($this->is_empty_alt($url)) {
                    if (!$this->db2_key_type_exists('image')) {
                        return false;
                    }
                    $manager = fifu_db2_manager();
                    $mapping = $manager->getPostAltMapping($post_id, 'image', 0);
                    if (!is_array($mapping)) {
                        return true;
                    }
                    return $this->write_service->delete_post_image_alt($post_id, 0) > 0
                        && !is_array($manager->getPostAltMapping($post_id, 'image', 0));
                }

                $ok = $this->write_service->save_post_image_alt($post_id, $url);
                if (!$ok) {
                    $this->log_db2_write_returned_false('post', $post_id, $meta_key, 0, $url);
                }
                return $ok;
            }

            return false;
        } catch (\Throwable $exception) {
            error_log(sprintf('FIFU DB2 legacy bridge post write failed: %s', (string) $exception->getMessage()));
            return false;
        }
    }

    public function handle_term_meta_change(int $term_id, string $meta_key, ?string $url): bool {
        try {
            $logicalType = match ($meta_key) {
                'fifu_image_url', 'fifu_ctgr_image_url' => 'term_image',
                'fifu_image_alt', 'fifu_ctgr_image_alt' => 'term_image_alt',
                default => null,
            };

            if ($logicalType === 'term_image') {
                if ($this->is_empty_url($url)) {
                    if (!$this->db2_key_type_exists('image')) {
                        return false;
                    }
                    $manager = fifu_db2_manager();
                    $urlMapping = $manager->getTermMapping($term_id, 'image');
                    $altMapping = $manager->getTermAltMapping($term_id, 'image');
                    $previousAlt = is_array($altMapping) ? ($altMapping['alt'] ?? null) : null;
                    if (!is_array($altMapping) && !is_array($urlMapping)) {
                        return true;
                    }
                    $altDeleted = $this->write_service->delete_term_image_alt($term_id);
                    if (is_array($altMapping) && ($altDeleted <= 0 || is_array($manager->getTermAltMapping($term_id, 'image')))) {
                        return false;
                    }
                    $urlDeleted = $this->write_service->delete_term_image($term_id);
                    if (is_array($urlMapping) && ($urlDeleted <= 0 || is_array($manager->getTermMapping($term_id, 'image')))) {
                        if ($previousAlt !== null) {
                            $this->write_service->save_term_image_alt($term_id, (string) $previousAlt);
                        }
                        return false;
                    }
                    return true;
                }

                $ok = $this->write_service->save_term_image($term_id, $url);
                if (!$ok) {
                    $this->log_db2_write_returned_false('term', $term_id, $meta_key, null, $url);
                }
                return $ok;
            }

            if ($logicalType === 'term_image_alt') {
                if ($this->is_empty_alt($url)) {
                    if (!$this->db2_key_type_exists('image')) {
                        return false;
                    }
                    $manager = fifu_db2_manager();
                    $mapping = $manager->getTermAltMapping($term_id, 'image');
                    if (!is_array($mapping)) {
                        return true;
                    }
                    return $this->write_service->delete_term_image_alt($term_id) > 0
                        && !is_array($manager->getTermAltMapping($term_id, 'image'));
                }

                $ok = $this->write_service->save_term_image_alt($term_id, $url);
                if (!$ok) {
                    $this->log_db2_write_returned_false('term', $term_id, $meta_key, null, $url);
                }
                return $ok;
            }

            return false;
        } catch (\Throwable $exception) {
            error_log(sprintf('FIFU DB2 legacy bridge term write failed: %s', (string) $exception->getMessage()));
            return false;
        }
    }

    private function is_removed_phase_five_meta_key(string $meta_key): bool {
        return preg_match('/^(fifu_image_url_\d+|fifu_image_alt_\d+|fifu_image_ifm_\d+|fifu_slider_image_url_\d+|fifu_slider_image_alt_\d+|fifu_slider_.*|fifu_list_url|fifu_list_alt|fifu_list_video_url|fifu_list_iframe_url)$/', $meta_key) === 1;
    }

    private function get_db2_wpdb(): ?wpdb {
        if (!function_exists('fifu_db2_manager')) {
            return null;
        }

        // Hardening: in some edge load orders the function may exist but the class is not loaded yet.
        // Avoid fatal errors on instanceof checks.
        if (!class_exists('Fifu_Db2_Manager')) {
            return null;
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return null;
        }

        // Optional hardening: ensure the expected method exists.
        if (!method_exists($manager, 'getWpdb')) {
            return null;
        }

        $wpdb = $manager->getWpdb();
        if (!$wpdb instanceof wpdb) {
            return null;
        }

        return $wpdb;
    }

    /**
     * Safety check: if the expected DB2 key_type is missing, do not treat delete-only operations as "ok".
     */
    private function db2_key_type_exists(string $keyType): bool {
        $wpdb = $this->get_db2_wpdb();
        if (!$wpdb) {
            return false;
        }

        $table = $wpdb->prefix . 'fifu_key';
        $query = $wpdb->prepare("SELECT key_id FROM {$table} WHERE key_type = %s LIMIT 1", $keyType);
        $keyId = $wpdb->get_var($query);

        return $keyId !== null && $keyId !== false;
    }

    private function log_db2_write_returned_false(
        string $entity,
        int $entity_id,
        string $meta_key,
        ?int $index,
        ?string $value
    ): void {
        $valueHash = $value === null ? '' : substr(md5($value), 0, 8);

        error_log(
            sprintf(
                'FIFU DB2 legacy bridge write returned false: entity=%s id=%d meta_key=%s index=%s value_md5=%s',
                $entity,
                $entity_id,
                $meta_key,
                $index === null ? 'null' : (string) $index,
                $valueHash
            )
        );
    }

    private function is_empty_url(?string $url): bool {
        return $url === null || trim($url) === '';
    }

    private function is_empty_alt(?string $alt): bool {
        return $alt === null || $alt === '';
    }

    private function is_removed_phase_four_meta_key(string $meta_key): bool {
        return $meta_key === 'fifu_video_url'
            || $meta_key === 'fifu_custom_video_url'
            || $meta_key === 'fifu_redirection_url'
            || $meta_key === 'fifu_list_video_url'
            || $meta_key === 'fifu_ctgr_video_url'
            || preg_match('/^fifu_video_url_\d+$/', $meta_key) === 1;
    }

}
