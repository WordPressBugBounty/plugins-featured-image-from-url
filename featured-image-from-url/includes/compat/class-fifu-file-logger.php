<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_File_Logger
{
    private const MAX_LOG_SIZE_BYTES = 10485760; // 10 MB
    private const LOG_SUBDIR = 'fifu/logs';
    private const INDEX_CONTENT = "<?php\n// Silence is golden.\n";
    private const HTACCESS_CONTENT = "Order deny,allow\nDeny from all\n<FilesMatch \".*\">\n    Deny from all\n</FilesMatch>\n";

    public static function log(string $channel, $entry, string $mode = 'a'): int
    {
        $paths = self::resolve_paths($channel);
        if ($paths === null) {
            return 0;
        }

        self::ensure_log_directory($paths['dir']);
        self::ensure_protection_files($paths['dir']);
        self::migrate_legacy_log($paths['legacy_file'], $paths['file']);

        if (is_array($entry)) {
            $entry = json_encode(
                [current_time('mysql') => $entry],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }

        if (file_exists($paths['file']) && filesize($paths['file']) > self::MAX_LOG_SIZE_BYTES) {
            @unlink($paths['file']);
        }

        if (!file_exists($paths['file'])) {
            @touch($paths['file']);
        }

        @chmod($paths['file'], 0600);

        $bytes = 0;
        $fh = @fopen($paths['file'], $mode);
        if ($fh) {
            $bytes = fwrite($fh, "{$entry}\n");
            fclose($fh);
        }

        @chmod($paths['file'], 0600);

        return $bytes ?: 0;
    }

    public static function cloud($entry, string $mode = 'a'): int
    {
        return self::log('fifu-cloud', $entry, $mode);
    }

    public static function plugin($entry, string $mode = 'a'): int
    {
        return self::log('fifu-plugin', $entry, $mode);
    }

    public static function register_hooks(): void
    {
        // Intentionally no-op. Log permissions are no longer changed when fifu_debug changes.
    }

    public static function set_log_permissions(bool $debug_on): void
    {
        // Intentionally no-op. Kept only for backward compatibility with old callers/tests.
    }

    public static function maybe_handle_debug_toggle(string $option, $old_value, $value): void
    {
        // Intentionally no-op. Kept only for backward compatibility with old callers/tests.
    }

    /**
     * @return array{dir:string,file:string,legacy_file:string}|null
     */
    private static function resolve_paths(string $channel): ?array
    {
        $upload_dir = wp_upload_dir();
        $basedir = is_array($upload_dir) ? (string) ($upload_dir['basedir'] ?? '') : '';

        if ($basedir === '') {
            return null;
        }

        $base = rtrim($basedir, '/\\');
        $safe_channel = self::sanitize_channel($channel);
        $dir = $base . '/' . self::LOG_SUBDIR;

        return [
            'dir' => $dir,
            'file' => $dir . '/' . $safe_channel . '.log',
            'legacy_file' => $base . '/' . $safe_channel . '.log',
        ];
    }

    private static function sanitize_channel(string $channel): string
    {
        $channel = preg_replace('/[^a-zA-Z0-9_-]/', '', $channel) ?: '';

        return $channel !== '' ? $channel : 'fifu-plugin';
    }

    private static function ensure_log_directory(string $dir): void
    {
        if (!file_exists($dir) && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private static function ensure_protection_files(string $dir): void
    {
        self::write_file_if_missing($dir . '/index.php', self::INDEX_CONTENT);
        self::write_file_if_missing($dir . '/.htaccess', self::HTACCESS_CONTENT);
    }

    private static function write_file_if_missing(string $path, string $content): void
    {
        if (file_exists($path)) {
            return;
        }

        @file_put_contents($path, $content);
        @chmod($path, 0600);
    }

    private static function migrate_legacy_log(string $legacy_file, string $new_file): void
    {
        if ($legacy_file === $new_file || !file_exists($legacy_file)) {
            return;
        }

        if (!file_exists($new_file)) {
            if (@rename($legacy_file, $new_file)) {
                @chmod($new_file, 0600);
                return;
            }

            $legacy_content = @file_get_contents($legacy_file);
            if ($legacy_content !== false) {
                @file_put_contents($new_file, $legacy_content, FILE_APPEND);
                @chmod($new_file, 0600);
                @unlink($legacy_file);
            }

            return;
        }

        $legacy_content = @file_get_contents($legacy_file);
        if ($legacy_content !== false && $legacy_content !== '') {
            @file_put_contents($new_file, $legacy_content, FILE_APPEND);
            @chmod($new_file, 0600);
        }

        @unlink($legacy_file);
    }
}
