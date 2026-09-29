<?php

declare(strict_types=1);

if (!class_exists('WPAuto_Image_Import', false)) {
    return;
}

final class Fifu_Wpauto_Pro_Image_Import_Proxy extends WPAuto_Image_Import {
    /** @var object */
    private $original_importer;

    /** @var callable */
    private $suppression_callback;

    public function __construct($original_importer, callable $suppression_callback) {
        $this->original_importer = $original_importer;
        $this->suppression_callback = $suppression_callback;
    }

    public function fetch_remote_file($url, $post_date, $post_title = '') {
        try {
            if (call_user_func($this->suppression_callback, $url)) {
                return new WP_Error('fifu_wpauto_remote_image', '');
            }
        } catch (Throwable $e) {
            // An interception failure must preserve WPAuto's normal importer path.
        }

        if (is_object($this->original_importer) && method_exists($this->original_importer, 'fetch_remote_file')) {
            return $this->original_importer->fetch_remote_file($url, $post_date, $post_title);
        }

        return new WP_Error('fifu_wpauto_importer_unavailable', '');
    }
}
