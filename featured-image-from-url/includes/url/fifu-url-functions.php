<?php
declare(strict_types=1);

// It's recommended to use an autoloader in a real project,
// but for this refactoring, direct requires are explicit.
require_once __DIR__ . '/sanitizer/class-fifu-url-special-char-sanitizer.php';
require_once __DIR__ . '/providers/interface-fifu-url-provider.php';
require_once __DIR__ . '/providers/class-fifu-google-drive-provider.php';
require_once __DIR__ . '/providers/class-fifu-onedrive-provider.php';
require_once __DIR__ . '/class-fifu-url-converter.php';

/**
 * Converts a URL using the new object-oriented structure.
 * This function ensures backward compatibility with legacy code.
 *
 * @param string $url The URL to convert.
 * @return string The converted or sanitized URL.
 */
function fifu_convert(string $url): string
{
    // Instantiate dependencies
    $sanitizer = new Fifu_Url_Special_Char_Sanitizer();
    $providers = [
        new Fifu_Google_Drive_Provider(),
        new Fifu_Onedrive_Provider(),
    ];

    // Instantiate the main converter with its dependencies
    $converter = new Fifu_Url_Converter($providers, $sanitizer);

    // Delegate the conversion task
    return $converter->convert($url);
}

/**
 * Checks if a URL is a Google Drive file link using the object-oriented structure.
 *
 * @param string $url The URL to check.
 * @return bool True if the URL is a Google Drive file link, false otherwise.
 */
function fifu_is_google_drive_file_php(string $url): bool
{
    $google_drive_provider = new Fifu_Google_Drive_Provider();
    return $google_drive_provider->is_google_drive_file($url);
}
