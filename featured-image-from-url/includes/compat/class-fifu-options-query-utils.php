<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Helper for querying wp_options in the FIFU context.
 */
class Fifu_Options_Query_Utils {

    private wpdb $wpdb;

    private string $options_table;

    public function __construct(?wpdb $wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->options_table = $this->wpdb->options;
    }

    /**
     * Inserir ou atualizar uma option.
     *
     * Mapeia a função antiga insert_option() de FifuDb.
     */
    public function insert(string $name, string $value): void {
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->options_table} (option_name, option_value) VALUES (%s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            $name,
            $value
        );
        $this->wpdb->query($sql);
    }

    /**
     * Buscar uma option por nome.
     *
     * Mapeia select_option().
     */
    public function get(string $name): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT option_value FROM {$this->options_table} WHERE option_name = %s",
            $name
        );
        $value = $this->wpdb->get_var($sql);
        return $value === null ? null : (string) $value;
    }

    /**
     * Deletar uma option por nome.
     *
     * Mapeia delete_option().
     */
    public function delete(string $name): void {
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->options_table} WHERE option_name = %s",
            $name
        );
        $this->wpdb->query($sql);
        if ($name !== '') {
            wp_cache_delete($name, 'options');
        }
    }

    /**
     * Listar options por prefixo.
     *
     * Mapeia select_option_prefix().
     *
     * @return array<object> Lista de resultados brutos do wpdb.
     */
    public function select_by_prefix(string $prefix): array {
        if ($prefix === '') {
            return [];
        }

        $like = $this->wpdb->esc_like($prefix) . '%';
        $sql = $this->wpdb->prepare(
            "SELECT option_name, option_value
            FROM {$this->options_table}
            WHERE option_name LIKE %s
            ORDER BY option_name",
            $like
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Deletar options por prefixo.
     *
     * Mapeia delete_option_prefix().
     *
     * @return int Número de registros deletados.
     */
    public function delete_by_prefix(string $prefix): int {
        if ($prefix === '') {
            return 0;
        }

        $like = $this->wpdb->esc_like($prefix) . '%';
        $sql_select = $this->wpdb->prepare(
            "SELECT option_name FROM {$this->options_table} WHERE option_name LIKE %s",
            $like
        );
        $options_to_delete = $this->wpdb->get_col($sql_select);
        $sql_delete = $this->wpdb->prepare(
            "DELETE FROM {$this->options_table} WHERE option_name LIKE %s",
            $like
        );
        $deleted_count = (int) $this->wpdb->query($sql_delete);

        foreach ($options_to_delete as $option_name) {
            wp_cache_delete($option_name, 'options');
        }

        return $deleted_count;
    }
}
