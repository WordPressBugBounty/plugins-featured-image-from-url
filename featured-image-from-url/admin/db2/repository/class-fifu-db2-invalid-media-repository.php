<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Provides access to the fifu_invalid_media_su table for invalid media attempts.
 */
class Fifu_Db2_Invalid_Media_Repository {
    private wpdb $wpdb;
    private string $table_invalid_media_su;

    /**
     * @param wpdb $wpdb
     */
    public function __construct(wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table_invalid_media_su = $wpdb->prefix . 'fifu_invalid_media_su';
    }

    /**
     * Retrieves the number of attempts stored for the given invalid URL.
     *
     * @param string $url
     * @return int
     */
    public function get_attempts(string $url): int {
        if ($url === '') {
            return 0;
        }

        $md5 = md5($url);
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT attempts FROM {$this->table_invalid_media_su} WHERE md5 = %s",
                $md5
            )
        );

        return $row ? (int) $row->attempts : 0;
    }

    /**
     * Increments the attempt count for the provided URL, inserting if needed.
     *
     * @param string $url
     * @return void
     */
    public function increment_attempts(string $url): void {
        if ($url === '') {
            return;
        }

        $md5 = md5($url);
        if ($this->get_attempts($url) > 0) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table_invalid_media_su} SET attempts = attempts + 1 WHERE md5 = %s",
                    $md5
                )
            );
            return;
        }

        $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table_invalid_media_su} (md5, attempts) VALUES (%s, 1)",
                $md5
            )
        );
    }

    /**
     * Deletes the record for the provided URL from the invalid media table.
     *
     * @param string $url
     * @return void
     */
    public function delete_by_url(string $url): void {
        if ($url === '') {
            return;
        }

        $md5 = md5($url);
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_invalid_media_su} WHERE md5 = %s",
                $md5
            )
        );
    }
}
