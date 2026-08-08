<?php
declare(strict_types=1);

class Fifu_Db2_Orphan_Gc_Repository {
    private wpdb $wpdb;
    private string $tableUrl;
    private string $tableMap;
    private string $tableTermMap;
    private string $tableAlt;
    private string $tableAltMap;
    private string $tableAltTermMap;
    private array $tableExistsCache = [];

    public function __construct(?wpdb $wpdbInstance = null) {
        if ($wpdbInstance === null) {
            global $wpdb;
            $wpdbInstance = $wpdb;
        }

        $this->wpdb = $wpdbInstance;
        $this->tableUrl = $wpdbInstance->prefix . 'fifu_url';
        $this->tableMap = $wpdbInstance->prefix . 'fifu_map';
        $this->tableTermMap = $wpdbInstance->prefix . 'fifu_term_map';
        $this->tableAlt = $wpdbInstance->prefix . 'fifu_alt';
        $this->tableAltMap = $wpdbInstance->prefix . 'fifu_alt_map';
        $this->tableAltTermMap = $wpdbInstance->prefix . 'fifu_alt_term_map';
    }

    public function urlTablesExist(): bool {
        return $this->tableExists($this->tableUrl)
            && $this->tableExists($this->tableMap)
            && $this->tableExists($this->tableTermMap);
    }

    public function altTablesExist(): bool {
        return $this->tableExists($this->tableAlt)
            && $this->tableExists($this->tableAltMap)
            && $this->tableExists($this->tableAltTermMap);
    }

    public function getNextUrlHashWindow(string $cursor, int $limit): array {
        $query = $this->wpdb->prepare(
            "SELECT hash FROM {$this->tableUrl} WHERE hash > %s ORDER BY hash ASC LIMIT %d",
            $cursor,
            $limit
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $row): string => (string) ($row['hash'] ?? ''),
            $rows
        ));
    }

    public function getNextAltHashWindow(string $cursor, int $limit): array {
        $query = $this->wpdb->prepare(
            "SELECT hash FROM {$this->tableAlt} WHERE hash > %s ORDER BY hash ASC LIMIT %d",
            $cursor,
            $limit
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $row): string => (string) ($row['hash'] ?? ''),
            $rows
        ));
    }

    public function deleteOrphanUrlsInRange(string $cursor, string $endHash): int {
        $query = $this->wpdb->prepare(
            "
            DELETE u
            FROM {$this->tableUrl} u
            WHERE u.hash > %s
              AND u.hash <= %s
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->tableMap} m
                  WHERE m.hash = u.hash
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->tableTermMap} tm
                  WHERE tm.hash = u.hash
              )
            ",
            $cursor,
            $endHash
        );

        $result = $this->wpdb->query($query);
        return $result === false ? 0 : $result;
    }

    public function deleteOrphanAltsInRange(string $cursor, string $endHash): int {
        $query = $this->wpdb->prepare(
            "
            DELETE a
            FROM {$this->tableAlt} a
            WHERE a.hash > %s
              AND a.hash <= %s
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->tableAltMap} am
                  WHERE am.hash = a.hash
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->tableAltTermMap} atm
                  WHERE atm.hash = a.hash
              )
            ",
            $cursor,
            $endHash
        );

        $result = $this->wpdb->query($query);
        return $result === false ? 0 : $result;
    }

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $query = $this->wpdb->prepare('SHOW TABLES LIKE %s', $table);
        $result = $this->wpdb->get_var($query);
        $this->tableExistsCache[$table] = ((string) $result === $table);

        return $this->tableExistsCache[$table];
    }
}
