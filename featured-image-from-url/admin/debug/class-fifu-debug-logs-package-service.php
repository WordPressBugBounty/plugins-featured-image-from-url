<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Debug_Logs_Package_Service
{
    private const LOG_DIR_RELATIVE = '/fifu/logs';
    private const MAX_LOG_BYTES = 1048576;

    private const LOG_FILES = [
        'fifu-plugin.log',
        'fifu-cloud.log',
    ];

    /**
     * @return array{path:string,filename:string,entries:array<int,string>}|\WP_Error
     */
    public static function create_package()
    {
        $entries = self::collect_entries();
        if (empty($entries)) {
            return new \WP_Error(
                'fifu_debug_logs_not_found',
                self::translate('No debug logs are available yet.')
            );
        }

        $filename = self::build_filename();
        $zip_path = self::create_temp_zip_path($filename);
        if ($zip_path === '') {
            return new \WP_Error(
                'fifu_debug_logs_zip_failed',
                self::translate('Unable to create the temporary debug logs package.')
            );
        }

        if (!self::write_zip($zip_path, $entries)) {
            @unlink($zip_path);

            return new \WP_Error(
                'fifu_debug_logs_zip_failed',
                self::translate('Unable to create the debug logs package.')
            );
        }

        return [
            'path' => $zip_path,
            'filename' => $filename,
            'entries' => array_keys($entries),
        ];
    }

    /**
     * @param array{path?:mixed} $package
     */
    public static function delete_package(array $package): void
    {
        $path = $package['path'] ?? '';
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<string,string>
     */
    private static function collect_entries(): array
    {
        $dir = self::get_log_dir();
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }

        $entries = [];
        foreach (self::LOG_FILES as $filename) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path) || !is_readable($path) || filesize($path) === 0) {
                continue;
            }

            $content = self::read_last_bytes($path, self::MAX_LOG_BYTES);
            if ($content === '') {
                continue;
            }

            $entries[$filename] = self::redact($content);
        }

        ksort($entries);

        return $entries;
    }

    private static function get_log_dir(): string
    {
        $uploads = wp_upload_dir();
        $basedir = isset($uploads['basedir']) && is_string($uploads['basedir'])
            ? rtrim($uploads['basedir'], "/\\")
            : '';

        if ($basedir === '') {
            return '';
        }

        return $basedir . self::LOG_DIR_RELATIVE;
    }

    private static function read_last_bytes(string $path, int $max_bytes): string
    {
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }

        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            return '';
        }

        try {
            if ($size > $max_bytes) {
                @fseek($handle, -$max_bytes, SEEK_END);
            }

            $content = stream_get_contents($handle);
            return is_string($content) ? $content : '';
        } finally {
            fclose($handle);
        }
    }

    private static function redact(string $content): string
    {
        $patterns = [
            '/(Authorization\s*:\s*Bearer\s+)[^\r\n]+/i' => '$1[redacted]',
            '/(Cookie\s*:\s*)[^\r\n]+/i' => '$1[redacted]',
            '/((?:license[_-]?key|password|passwd|pwd|secret|token|access[_-]?token|consumer[_-]?secret)\s*[:=]\s*)[^\r\n]+/i' => '$1[redacted]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = (string) preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    private static function build_filename(): string
    {
        $stamp = '';
        if (function_exists('current_time')) {
            $stamp = preg_replace('/\D+/', '', (string) current_time('mysql'));
        }

        if ($stamp === '') {
            $stamp = gmdate('YmdHis');
        }

        return 'fifu-debug-logs-' . $stamp . '.zip';
    }

    private static function create_temp_zip_path(string $filename): string
    {
        if (function_exists('wp_tempnam')) {
            $path = wp_tempnam($filename);
        } else {
            $path = tempnam(sys_get_temp_dir(), 'fifu-debug-logs-');
        }

        if (!is_string($path) || $path === '') {
            return '';
        }

        return $path;
    }

    private static function translate(string $message): string
    {
        if (!function_exists('__')) {
            return $message;
        }

        $domain = defined('FIFU_SLUG') ? FIFU_SLUG : '';

        return __($message, $domain);
    }

    /**
     * @param array<string,string> $entries
     */
    private static function write_zip(string $path, array $entries): bool
    {
        if (class_exists(\ZipArchive::class)) {
            return self::write_zip_with_ziparchive($path, $entries);
        }

        return self::write_zip_without_extension($path, $entries);
    }

    /**
     * @param array<string,string> $entries
     */
    private static function write_zip_with_ziparchive(string $path, array $entries): bool
    {
        $zip = new \ZipArchive();
        $result = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            return false;
        }

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        return $zip->close();
    }

    /**
     * Minimal ZIP writer using stored entries, used when ZipArchive is unavailable.
     *
     * @param array<string,string> $entries
     */
    private static function write_zip_without_extension(string $path, array $entries): bool
    {
        $handle = @fopen($path, 'wb');
        if (!is_resource($handle)) {
            return false;
        }

        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($entries as $name => $content) {
            $name = basename($name);
            $name_length = strlen($name);
            $size = strlen($content);
            $crc = crc32($content);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            $local_header = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $name_length,
                0
            );

            fwrite($handle, $local_header);
            fwrite($handle, $name);
            fwrite($handle, $content);

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $name_length,
                0,
                0,
                0,
                0,
                0,
                $offset
            );
            $central .= $name;

            $offset += strlen($local_header) + $name_length + $size;
            $count++;
        }

        $central_offset = $offset;
        fwrite($handle, $central);

        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            strlen($central),
            $central_offset,
            0
        );
        fwrite($handle, $end);

        fclose($handle);

        return is_file($path) && filesize($path) !== false && filesize($path) > 0;
    }
}
