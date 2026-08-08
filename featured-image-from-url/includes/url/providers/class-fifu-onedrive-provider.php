<?php
declare(strict_types=1);

/**
 * Handles URL conversion for OneDrive links.
 */
class Fifu_Onedrive_Provider implements Fifu_Url_Provider
{
    /**
     * Checks if the URL is from OneDrive (1drv.ms).
     *
     * @param string $url
     * @return bool
     */
    public function supports(string $url): bool
    {
        return strpos($url, '1drv.ms') !== false;
    }

    /**
     * Converts a OneDrive share URL to a direct content API link.
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
        return "https://api.onedrive.com/v1.0/shares/{$id}/root/content";
    }

    /**
     * Extracts the share ID from a OneDrive URL.
     *
     * @param string $url
     * @return string|null
     */
    private function get_id(string $url): ?string
    {
        $url_parts = explode("/", $url);
        if (!isset($url_parts[4])) {
            return null;
        }
        return explode("?", $url_parts[4])[0] ?? null;
    }
}