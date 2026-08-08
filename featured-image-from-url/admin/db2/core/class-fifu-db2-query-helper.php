<?php

declare(strict_types=1);

/**
 * Assists with merging DB2 and legacy SQL results.
 */
class Fifu_Db2_Query_Helper {
    /**
     * Merge two result sets while deduplicating by an integer field.
     *
     * Primary rows always take precedence over secondary rows.
     *
     * @param array<array-key, array|object> $primary
     * @param array<array-key, array|object> $secondary
     * @param string $idField
     * @return array<array|object>
     */
    public static function merge_unique_rows_by_int_field(
        array $primary,
        array $secondary,
        string $idField = 'post_id'
    ): array {
        $merged = [];
        $seen = [];

        foreach ($primary as $row) {
            $id = self::extract_int_id($row, $idField);
            if ($id === null || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $row;
        }

        foreach ($secondary as $row) {
            $id = self::extract_int_id($row, $idField);
            if ($id === null || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $row;
        }

        return array_values($merged);
    }

    /**
     * Extracts an integer identifier from a row array or object.
     *
     * @param array|object $row
     * @param string $field
     * @return int|null
     */
    private static function extract_int_id($row, string $field): ?int {
        if (is_array($row)) {
            if (!array_key_exists($field, $row)) {
                return null;
            }
            $value = $row[$field];
        } elseif (is_object($row)) {
            if (!property_exists($row, $field)) {
                return null;
            }
            $value = $row->{$field};
        } else {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer === null) {
            return null;
        }

        return $integer;
    }
}
