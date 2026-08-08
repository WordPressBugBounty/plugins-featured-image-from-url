<?php
declare(strict_types=1);

/**
 * Converts a public URL into a direct link by delegating to registered providers.
 * Also applies sanitization as a fallback.
 */
class Fifu_Url_Converter
{
    /**
     * @var Fifu_Url_Provider[]
     */
    private $providers;

    /**
     * @var Fifu_Url_Special_Char_Sanitizer
     */
    private $sanitizer;

    /**
     * @param Fifu_Url_Provider[] $providers Array of URL providers.
     * @param Fifu_Url_Special_Char_Sanitizer $sanitizer Sanitizer instance.
     */
    public function __construct(array $providers, Fifu_Url_Special_Char_Sanitizer $sanitizer)
    {
        $this->providers = $providers;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Converts a URL by finding a suitable provider or sanitizing it.
     *
     * @param string $url
     * @return string
     */
    public function convert(string $url): string
    {
        // 1. Loop through providers to find one that supports the URL
        foreach ($this->providers as $provider) {
            if ($provider->supports($url)) {
                return $provider->convert($url);
            }
        }

        // 2. If no provider is found, fall back to the sanitizer
        if ($this->sanitizer->has_special_char($url)) {
            return $this->sanitizer->sanitize($url);
        }

        // 3. If no conversion or sanitization is needed, return the original URL
        return $url;
    }
}