<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Wordpress_Importer_Integration
{
    public static function register_hooks(): void
    {
        // Comments must be in English.
        add_action('import_end', [self::class, 'handle_import_end'], 10, 0);
    }

    public static function handle_import_end(): void
    {
        if (isset($_POST['action']) && $_POST['action'] === 'woocommerce_csv_import_request' && !isset($_POST['mapping'])) {
            return;
        }

        $cleanup = new Fifu_Local_Media_Cleanup();
        $cleanup->delete_orphaned_thumbnails_and_galleries();
        Fifu_Meta_Maintenance_Controller::enable_fake();
    }
}
