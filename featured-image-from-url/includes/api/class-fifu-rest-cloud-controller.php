<?php
declare(strict_types=1);

final class Fifu_Rest_Cloud_Controller {

    /**
     * Register Cloud REST endpoints.
     */
    public static function register_routes(): void {
        $permission = [Fifu_Rest_Permissions::class, 'private_data'];

        register_rest_route(FIFU_REST_NAMESPACE_V2, '/delete/', [
            'methods' => 'POST',
            'callback' => [self::class, 'delete'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/delete/', [
            'methods' => 'POST',
            'callback' => [self::class, 'delete'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/reset-credentials/', [
            'methods' => 'POST',
            'callback' => [self::class, 'reset_credentials'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/list-all/', [
            'methods' => 'POST',
            'callback' => [self::class, 'list_all_su'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/list_all_su/', [
            'methods' => 'POST',
            'callback' => [self::class, 'list_all_su'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/list_all_fifu/', [
            'methods' => 'POST',
            'callback' => [self::class, 'list_all_fifu'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/list-daily-count/', [
            'methods' => 'POST',
            'callback' => [self::class, 'list_daily_count'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/upload-auto/', [
            'methods' => 'POST',
            'callback' => [self::class, 'cloud_upload_auto'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/delete-auto/', [
            'methods' => 'POST',
            'callback' => [self::class, 'cloud_delete_auto'],
            'permission_callback' => $permission,
        ]);
        register_rest_route(FIFU_REST_NAMESPACE_V2, '/cloud/hotlink/', [
            'methods' => 'POST',
            'callback' => [self::class, 'cloud_hotlink'],
            'permission_callback' => $permission,
        ]);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_Media_Service::delete_selected()
     */
    public static function delete(\WP_REST_Request $request) {
        $storageIds = [];
        foreach ((array) ($request['selected'] ?? []) as $image) {
            $storageId = self::selected_storage_id($image);
            if ($storageId === null) {
                continue;
            }

            $storageIds[] = $storageId;
        }

        return Fifu_Cloud_Media_Service::delete_selected($storageIds);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_License_Service::reset_credentials()
     */
    public static function reset_credentials(\WP_REST_Request $request) {
        $email = self::string_param($request, 'email');
        return Fifu_Cloud_License_Service::reset_credentials($email);
    }

    private static function string_param(\WP_REST_Request $request, string $key): string {
        $value = $request[$key] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }

    private static function selected_storage_id($image): ?string {
        $storageId = null;

        if (is_array($image)) {
            $storageId = $image['storage_id'] ?? null;
        } elseif ($image instanceof \ArrayAccess) {
            $storageId = isset($image['storage_id']) ? $image['storage_id'] : null;
        } elseif (is_object($image)) {
            $storageId = $image->storage_id ?? null;
        }

        if (!is_scalar($storageId)) {
            return null;
        }

        $storageId = trim((string) $storageId);
        return $storageId !== '' ? $storageId : null;
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_Media_Service::list_all()
     */
    public static function list_all_su(\WP_REST_Request $request) {
        $page = (int) ($request['page'] ?? 0);
        $type = (string) ($request['type'] ?? '');
        $keyword = (string) ($request['keyword'] ?? '');

        return Fifu_Cloud_Media_Service::list_all($page, $type, $keyword);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_Media_Service::list_all_fifu()
     */
    public static function list_all_fifu(\WP_REST_Request $request) {
        $page = (int) ($request['page'] ?? 0);
        $type = $request['type'] ?? null;
        $keyword = $request['keyword'] ?? null;

        return Fifu_Cloud_Media_Service::list_all_fifu($page, $type, $keyword);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_Media_Service::list_daily_count()
     */
    public static function list_daily_count(\WP_REST_Request $request) {
        return Fifu_Cloud_Media_Service::list_daily_count();
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_License_Service::toggle_upload_auto()
     */
    public static function cloud_upload_auto(\WP_REST_Request $request) {
        $enabled = self::is_toggle_enabled($request);
        return Fifu_Cloud_License_Service::toggle_upload_auto($enabled);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_License_Service::toggle_delete_auto()
     */
    public static function cloud_delete_auto(\WP_REST_Request $request) {
        $enabled = ($request['toggle'] ?? '') === 'toggleon';
        return Fifu_Cloud_License_Service::toggle_delete_auto($enabled);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @see Fifu_Cloud_License_Service::toggle_hotlink()
     */
    public static function cloud_hotlink(\WP_REST_Request $request) {
        $enabled = ($request['toggle'] ?? '') === 'toggleon';
        return Fifu_Cloud_License_Service::toggle_hotlink($enabled);
    }

    private static function is_toggle_enabled(\WP_REST_Request $request): bool {
        $raw = null;

        if (isset($request['enabled'])) {
            $raw = $request['enabled'];
        } elseif (isset($request['toggle'])) {
            $raw = $request['toggle'];
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw)) {
            return $raw === 1;
        }

        if (is_string($raw)) {
            return in_array(strtolower(trim($raw)), ['1', 'true', 'on', 'toggleon', 'yes'], true);
        }

        return false;
    }
}
