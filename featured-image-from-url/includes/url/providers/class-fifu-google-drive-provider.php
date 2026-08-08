<?php
declare(strict_types=1);

/**
 * Handles URL conversion for Google Drive links.
 */
class Fifu_Google_Drive_Provider implements Fifu_Url_Provider
{
    /**
     * Checks if the URL is from Google Drive.
     *
     * @param string $url
     * @return bool
     */
    public function supports(string $url): bool
    {
        return strpos($url, 'drive.google.com') !== false;
    }

    /**
     * Converts a Google Drive URL to a direct download link.
     *
     * @param string $url
     * @return string
     */
    public function convert(string $url): string
    {
        $id = $this->get_id($url);
        if (!$id) {
            return $url;
        }
        return 'https://drive.google.com/uc?id=' . $id;
    }

    /**
     * Extracts the file ID from a Google Drive URL.
     *
     * @param string $url
     * @return string|null
     */
    public function get_id(string $url): ?string
    {
        preg_match("/[-\w]{25,}/", $url, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Checks if the URL is a direct file link from Google Drive.
     * Note: This method encapsulates the original fifu_is_google_drive_file logic.
     *
     * @param string $url
     * @return bool
     */
    public function is_google_drive_file(string $url): bool
    {
        return strpos($url, 'drive.google.com/file') !== false;
    }
}