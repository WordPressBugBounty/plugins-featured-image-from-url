<?php

function fifu_cloud_log($entry, $mode = 'a', $file = 'fifu-cloud') {
    return fifu_log($entry, $file, $mode);
}

function fifu_plugin_log($entry, $mode = 'a', $file = 'fifu-plugin') {
    return fifu_log($entry, $file, $mode);
}

function fifu_log($entry, $file, $mode = 'a') {
    $upload_dir = wp_upload_dir()['basedir'] ?? '';
    $filepath = "{$upload_dir}/{$file}.log";

    // Rotate the .log file if it exceeds 10MB
    if (file_exists($filepath) && filesize($filepath) > 10 * 1024 * 1024) {
        @unlink($filepath);
    }

    if (is_array($entry)) {
        $entry = json_encode([current_time('mysql') => $entry], JSON_UNESCAPED_SLASHES);
    }

    // Ensure file exists before adjusting permissions
    if (!file_exists($filepath)) {
        @touch($filepath);
    }

    // Set permissions based on current debug toggle
    $debug_on = function_exists('fifu_is_on') ? fifu_is_on('fifu_debug') : false;
    @chmod($filepath, $debug_on ? 0600 : 0200);

    $fh = fopen($filepath, $mode);
    $bytes = fwrite($fh, "{$entry}\n");
    fclose($fh);

    return $bytes;
}

// Immediately adjust log file permissions when fifu_debug changes
function fifu_set_log_permissions($debug_on) {
    $upload_dir = wp_upload_dir()['basedir'] ?? '';
    if (!$upload_dir)
        return;
    $files = [
        $upload_dir . '/fifu-plugin.log',
        $upload_dir . '/fifu-cloud.log',
    ];
    $perm = $debug_on ? 0600 : 0200;
    foreach ($files as $file) {
        if (file_exists($file)) {
            @chmod($file, $perm);
        }
    }
}

add_action('updated_option', function ($option, $old_value, $value) {
    if ($option === 'fifu_debug') {
        fifu_set_log_permissions($value === 'toggleon');
    }
}, 10, 3);


