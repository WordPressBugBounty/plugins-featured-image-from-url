<?php

declare(strict_types=1);

class Fifu_Unsplash_Image_Service {

    public function search($keywords, $page = 1): array {
        $keywords = trim((string) $keywords);
        if ($keywords === '') {
            return [];
        }

        $page = max(1, (int) $page);
        $query = http_build_query([
            'site' => home_url(),
            'keywords' => $keywords,
            'page' => $page,
        ]);

        $response = wp_safe_remote_get(
            'https://unsplash-free.fifu.workers.dev?' . $query,
            ['timeout' => 30]
        );
        if (is_wp_error($response)) {
            return [];
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            return [];
        }

        $results = [];
        foreach ($data['results'] as $item) {
            if (!is_array($item) || !isset($item['urls']) || !is_array($item['urls'])) {
                continue;
            }

            $urls = $item['urls'];
            $thumbnail = $this->firstAvailableUrl($urls, ['small', 'thumb', 'regular', 'full', 'raw']);
            $url = $this->firstAvailableUrl($urls, ['regular', 'full', 'raw', 'small', 'thumb']);

            if ($thumbnail === null && $url === null) {
                continue;
            }

            if ($thumbnail === null) {
                $thumbnail = $url;
            }

            if ($url === null) {
                $url = $thumbnail;
            }

            if ($this->hasOnlySizeVariant($urls, 'small')) {
                $url = $this->replaceWidth($thumbnail);
            }

            $results[] = [
                'thumbnail' => $thumbnail,
                'url' => $url,
            ];
        }

        return $results;
    }

    private function firstAvailableUrl(array $urls, array $priority): ?string {
        foreach ($priority as $key) {
            if (!empty($urls[$key]) && is_string($urls[$key])) {
                return $urls[$key];
            }
        }

        return null;
    }

    private function hasOnlySizeVariant(array $urls, string $key): bool {
        return isset($urls[$key]) && is_string($urls[$key]) && !isset($urls['regular']) && !isset($urls['full']) && !isset($urls['raw']);
    }

    private function replaceWidth(string $url): string {
        if (preg_match('/([?&])w=\d+/', $url)) {
            return preg_replace('/([?&])w=\d+/', '$1w=1200', $url) ?? $url;
        }

        return $url;
    }
}
