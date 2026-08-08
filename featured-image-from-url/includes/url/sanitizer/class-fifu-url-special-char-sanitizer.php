<?php
declare(strict_types=1);

/**
 * Sanitizes URLs by removing or encoding problematic special characters.
 */
class Fifu_Url_Special_Char_Sanitizer
{
    /**
     * Checks if the URL contains a single quote character.
     *
     * @param string $url
     * @return bool
     */
    public function has_special_char(string $url): bool
    {
        return strpos($url, "'") !== false;
    }

    /**
     * Sanitizes the URL by replacing single quotes with '%27'.
     *
     * @param string $url
     * @return string
     */
    public function sanitize(string $url): string
    {
        return str_replace("'", "%27", $url);
    }
}