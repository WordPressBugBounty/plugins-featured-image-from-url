<?php
declare(strict_types=1);

/**
 * Migration statistics helpers for FIFU.
 */
class Fifu_Migration_Stats {

    private const MAIN_FIFU_TABLE_SUFFIXES = [
        'fifu_url',
        'fifu_key',
        'fifu_map',
        'fifu_term_map',
        'fifu_alt',
        'fifu_alt_map',
        'fifu_alt_term_map',
        'fifu_invalid_media_su',
        'fifu_meta_in',
        'fifu_meta_out',
        'fifu_sent',
        'fifu_sent_event',
        'fifu_identifier_type',
        'fifu_identifier',
    ];

    private wpdb $wpdb;

    private string $posts_table;

    private string $postmeta_table;

    private string $fifu_meta_in_table;

    private string $fifu_meta_out_table;

    private string $fifu_map_table;

    private string $fifu_term_map_table;

    private int $author_id;

    /** @var string[] */
    private array $post_types;

    public function __construct(?wpdb $wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->posts_table = $this->wpdb->posts;
        $this->postmeta_table = $this->wpdb->postmeta;
        $this->fifu_meta_in_table = $this->wpdb->prefix . 'fifu_meta_in';
        $this->fifu_meta_out_table = $this->wpdb->prefix . 'fifu_meta_out';
        $this->fifu_map_table = $this->wpdb->prefix . 'fifu_map';
        $this->fifu_term_map_table = $this->wpdb->prefix . 'fifu_term_map';
        $this->author_id = (int) Fifu_Options_Utils::get_author();
        $this->post_types = $this->sanitize_post_types(Fifu_Post_Type_Utils::get_post_types());
    }

    /**
     * Number of published posts tracked by FIFU.
     *
     * Mirrors get_number_of_posts().
     */
    public function get_number_of_posts(): int {
        $types_list = $this->get_post_types_in_clause();
        $sql = "
            SELECT count(1) AS n
            FROM {$this->posts_table}
            WHERE post_type IN ({$types_list})
            AND post_status = 'publish'
        ";
        $result = $this->wpdb->get_row($sql);
        return $result ? (int) $result->n : 0;
    }

    /**
     * Total number of rows in wp_posts.
     *
     * Mirrors get_count_wp_posts().
     */
    public function get_count_wp_posts(): int {
        $sql = "
            SELECT COUNT(1) AS amount
            FROM {$this->posts_table}
        ";
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Total number of rows in wp_postmeta.
     *
     * Mirrors get_count_wp_postmeta().
     */
    public function get_count_wp_postmeta(): int {
        $sql = "
            SELECT COUNT(1) AS amount
            FROM {$this->postmeta_table}
        ";
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Number of posts authored by the FIFU author account.
     *
     * Mirrors get_count_wp_posts_fifu().
     */
    public function get_count_wp_posts_fifu(): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) AS amount FROM {$this->posts_table} WHERE post_author = %d",
            $this->author_id
        );
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Number of postmeta entries linked to FIFU attachments.
     *
     * Mirrors get_count_wp_postmeta_fifu().
     */
    public function get_count_wp_postmeta_fifu(): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) AS amount
             FROM {$this->postmeta_table}
             WHERE meta_key = '_wp_attached_file'
               AND EXISTS (
                   SELECT 1 FROM {$this->posts_table}
                   WHERE id = post_id AND post_author = %d
               )",
            $this->author_id
        );
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Checks whether the DB2 tables exist.
     *
     * Mirrors tables_created().
     */
    public function tables_created(): bool {
        foreach (self::MAIN_FIFU_TABLE_SUFFIXES as $suffix) {
            if (!$this->table_exists($this->wpdb->prefix . $suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Counts posts that have metadata and belong to the FIFU author.
     *
     * Mirrors get_count_urls_with_metadata().
     */
    public function count_urls_with_metadata(): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) AS amount
            FROM {$this->posts_table} p
            WHERE p.post_author = %d",
            $this->author_id
        );
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Counts DB2 URL rows across post and term maps.
     */
    public function count_urls_db2(): int {
        return $this->count_table_rows_if_exists($this->fifu_map_table)
            + $this->count_table_rows_if_exists($this->fifu_term_map_table);
    }

    /**
     * Aggregates metadata operation counts tracked in fifu_meta_in and fifu_meta_out.
     *
     * Mirrors get_count_metadata_operations().
     */
    public function count_meta_in_operations(): int {
        $sql = "
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN post_ids IS NULL OR post_ids = '' THEN 0
                            ELSE CHAR_LENGTH(post_ids)
                                - CHAR_LENGTH(REPLACE(post_ids, ',', ''))
                                + 1
                        END
                    ),
                    0
                ) AS total_amount
            FROM {$this->fifu_meta_in_table}
        ";

        return (int) $this->wpdb->get_var($sql);
    }

    public function count_meta_out_operations(): int {
        $sql = "
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN post_ids IS NULL OR post_ids = '' THEN 0
                            ELSE CHAR_LENGTH(post_ids)
                                - CHAR_LENGTH(REPLACE(post_ids, ',', ''))
                                + 1
                        END
                    ),
                    0
                ) AS total_amount
            FROM {$this->fifu_meta_out_table}
        ";

        return (int) $this->wpdb->get_var($sql);
    }

    public function count_metadata_operations(): int {
        $sql = "
            SELECT 
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_in_table}
                    ), 0
                ) +
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_out_table}
                    ), 0
                ) AS total_amount
        ";
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Counts attachments without metadata dimensions tied to the FIFU author.
     *
     * Mirrors get_count_posts_without_dimensions().
     */
    public function count_posts_without_dimensions(): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) AS amount
            FROM {$this->posts_table} p
            WHERE NOT EXISTS (
                SELECT 1 
                FROM {$this->postmeta_table} b
                WHERE p.id = b.post_id AND meta_key = '_wp_attachment_metadata'
            )
            AND p.post_author = %d",
            $this->author_id
        );
        $result = $this->wpdb->get_var($sql);
        if ($result === null || $result === false) {
            return -1;
        }
        return (int) $result;
    }

    /**
     * Sanitizes and deduplicates registered post types.
     */
    private function sanitize_post_types(array $post_types): array {
        $registered = get_post_types([], 'names');
        $safe = [];
        foreach ($post_types as $type) {
            $sanitized = sanitize_key((string) $type);
            if ($sanitized === '' || !isset($registered[$sanitized])) {
                continue;
            }
            $safe[$sanitized] = true;
        }
        return array_keys($safe);
    }

    /**
     * Builds a quoted IN clause for the stored post types.
     */
    private function get_post_types_in_clause(): string {
        if (empty($this->post_types)) {
            return "''";
        }
        return "'" . implode("','", $this->post_types) . "'";
    }

    /**
     * Returns the number of rows in a table when it exists.
     */
    private function count_table_rows_if_exists(string $table): int {
        if (!$this->table_exists($table)) {
            return 0;
        }

        $sql = "SELECT COUNT(1) AS amount FROM {$table}";
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Returns whether the given table exists in the database.
     */
    private function table_exists(string $table): bool {
        if (property_exists($this->wpdb, 'table_exists_overrides')) {
            $sql = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table);
            return $this->wpdb->get_var($sql) !== null;
        }

        $sql = $this->wpdb->prepare(
            "
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = %s
            LIMIT 1
            ",
            $table
        );
        return $this->wpdb->get_var($sql) !== null;
    }
}
