<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Factory responsible for creating virtual attachments for FIFU URLs.
 */
class Fifu_Attachment_Factory {
    public static array $construct_calls = [];
    public static array $create_calls = [];
    public static int $create_return = 0;

    /** @var wpdb */
    private wpdb $wpdb;

    /** @var string */
    private string $posts_table;

    /** @var int */
    private int $author_id;

    public function __construct(?wpdb $wpdb_instance = null) {
        self::$construct_calls[] = true;
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->posts_table = $this->wpdb->posts;
        $this->author_id = $this->resolve_author();
    }

    /**
     * Create an attachment for a given URL.
     *
     * @param string      $url
     * @param string|null $alt
     * @param int|null    $post_parent
     * @return int Attachment ID.
     */
    public function create_attachment_for_url(string $url, ?string $alt = null, ?int $post_parent = null): int {
        self::$create_calls[] = compact('url', 'alt', 'post_parent');

        if (self::$create_return !== 0) {
            return self::$create_return;
        }

        $tuple = $this->build_insert_tuple($url, $alt, $post_parent);
        $this->bulk_insert($tuple);
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Create a category-scoped attachment preserving the "fifu-category-{termId}" post_name.
     *
     * @param string      $url
     * @param string|null $alt
     * @param int         $termId
     * @return int Attachment ID.
     */
    public function create_category_attachment_for_url(string $url, ?string $alt, int $termId): int {
        $tuple = $this->build_category_insert_tuple($url, $alt, $termId);
        $this->bulk_insert($tuple, true);
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Builds an INSERT tuple for a category-linked attachment using the provided term.
     *
     * The post_name should follow the pattern "fifu-category-{termId}".
     *
     * @param string      $url
     * @param string|null $alt
     * @param int         $termId
     * @return string
     */
    public function build_category_insert_tuple(string $url, ?string $alt, int $termId): string {
        $alt = $alt ?? '';
        return $this->wpdb->prepare(
            "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s, %s)",
            $this->author_id,
            '',
            $alt,
            $alt,
            'image/jpeg',
            'attachment',
            'inherit',
            $termId,
            '',
            '',
            '',
            $url,
            'fifu-category-' . $termId
        );
    }

    /**
     * Builds an INSERT tuple for a post attachment using the provided parent.
     *
     * @param string      $url
     * @param string|null $alt
     * @param int|null    $post_parent
     * @return string
     */
    public function build_insert_tuple(string $url, ?string $alt, ?int $post_parent): string {
        $alt = $alt ?? '';
        return $this->wpdb->prepare(
            "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s)",
            $this->author_id, // post_author
            '', // guid
            $alt, // post_title
            $alt, // post_excerpt
            'image/jpeg', // post_mime_type
            'attachment', // post_type
            'inherit', // post_status
            $post_parent ?? 0, // post_parent
            '', // post_content
            '', // to_ping
            '', // pinged
            $url // post_content_filtered
        );
    }

    private function resolve_author(): int {
        if (function_exists('fifu_get_author')) {
            $author = (int) fifu_get_author();
            if ($author > 0) {
                return $author;
            }
        }

        if (class_exists('Fifu_Options_Utils', false) && method_exists('Fifu_Options_Utils', 'get_author')) {
            $author = (int) Fifu_Options_Utils::get_author();
            if ($author > 0) {
                return $author;
            }
        }

        if (defined('FIFU_AUTHOR')) {
            $author = (int) FIFU_AUTHOR;
            if ($author > 0) {
                return $author;
            }
        }

        if (function_exists('get_option')) {
            $author = get_option('fifu_author', null);
            if ($author !== null && $author !== '' && (int) $author > 0) {
                return (int) $author;
            }
        }

        return 7777777777;
    }

    /**
     * Performs bulk INSERT INTO the posts table for the provided tuple list.
     *
     * @param string|array $values
     * @return int
     */
    public function bulk_insert($values, bool $include_post_name = false): int {
        $values_sql = is_array($values) ? implode(', ', $values) : (string) $values;
        $columns = '
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered)';

        if ($include_post_name) {
            $columns = '
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name)';
        }

        $sql = "
            INSERT INTO {$this->posts_table}
                {$columns}
            VALUES {$values_sql}";
        $result = $this->wpdb->query($sql);
        return $result === false ? 0 : (int) $result;
    }

    public static function reset(): void {
        self::$construct_calls = [];
        self::$create_calls = [];
        self::$create_return = 0;
    }
}
