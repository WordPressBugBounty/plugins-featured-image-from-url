<?php
declare(strict_types=1);

/**
 * Interface for URL providers that can convert a public URL into a direct link.
 */
interface Fifu_Url_Provider
{
    /**
     * Checks if the provider supports the given URL.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL is supported, false otherwise.
     */
    public function supports(string $url): bool;

    /**
     * Converts the provider's URL into a direct, usable link.
     *
     * @param string $url The supported URL to convert.
     * @return string The converted direct link.
     */
    public function convert(string $url): string;
}