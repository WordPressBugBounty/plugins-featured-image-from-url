<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Fifu_Transient_Manager', false)) {
    final class Fifu_Transient_Manager
    {
        public static array $storage = [];
        public static array $store = [];
        public static array $set_calls = [];
        public static array $get_calls = [];
        public static array $delete_calls = [];
        public static array $calls = [];

        public static function set(string $key, $value, $expiration = 0): bool
        {
            $key = trim((string) $key);
            if ($key === '') {
                return false;
            }

            $expiration = max(0, (int) $expiration);
            self::$set_calls[] = compact('key', 'value', 'expiration');
            self::$calls[] = ['set', $key, $value, $expiration];

            if (!self::write_option_no_autoload($key, $value)) {
                return false;
            }

            self::$storage[$key] = $value;
            self::$store[$key] = $value;

            if ($expiration > 0) {
                $expiration_time = time() + $expiration;
                $expiration_key = self::get_expiration_key($key);
                if (!self::write_option_no_autoload($expiration_key, $expiration_time)) {
                    return false;
                }

                self::$storage[$expiration_key] = $expiration_time;
                self::$store[$expiration_key] = $expiration_time;
                self::delete_option_if_exists(self::get_legacy_timeout_key($key));
                unset(self::$storage[self::get_legacy_timeout_key($key)], self::$store[self::get_legacy_timeout_key($key)]);

                return true;
            }

            self::delete_option_if_exists(self::get_expiration_key($key));
            self::delete_option_if_exists(self::get_legacy_timeout_key($key));
            unset(
                self::$storage[self::get_expiration_key($key)],
                self::$store[self::get_expiration_key($key)],
                self::$storage[self::get_legacy_timeout_key($key)],
                self::$store[self::get_legacy_timeout_key($key)]
            );

            return true;
        }

        public static function get(string $key)
        {
            $key = trim((string) $key);
            if ($key === '') {
                return false;
            }

            self::$get_calls[] = $key;
            self::$calls[] = ['get', $key];

            $expiration_key = self::get_expiration_key($key);
            $legacy_timeout_key = self::get_legacy_timeout_key($key);
            $value = get_option($key, false);

            if ($value === false) {
                self::clear_static_cache_for_key($key);
                return false;
            }

            $expiration = get_option($expiration_key, false);
            $legacy_timeout = false;
            if ($expiration === false) {
                $legacy_timeout = get_option($legacy_timeout_key, false);
                if ($legacy_timeout !== false) {
                    $expiration = $legacy_timeout;
                }
            }

            if ($expiration !== false && (int) $expiration < time()) {
                self::delete($key);
                return false;
            }

            if ($expiration !== false && $legacy_timeout !== false) {
                if (get_option($expiration_key, false) === false) {
                    if (!self::write_option_no_autoload($expiration_key, $expiration)) {
                        return false;
                    }
                }

                self::delete_option_if_exists($legacy_timeout_key);
            } elseif (get_option($expiration_key, false) !== false && get_option($legacy_timeout_key, false) !== false) {
                self::delete_option_if_exists($legacy_timeout_key);
            }

            self::$storage[$key] = $value;
            self::$store[$key] = $value;

            if ($expiration !== false) {
                self::$storage[$expiration_key] = $expiration;
                self::$store[$expiration_key] = $expiration;
            } else {
                unset(self::$storage[$expiration_key], self::$store[$expiration_key]);
            }

            unset(self::$storage[$legacy_timeout_key], self::$store[$legacy_timeout_key]);

            return $value;
        }

        public static function delete(string $key): bool
        {
            $key = trim((string) $key);
            if ($key === '') {
                return false;
            }

            self::$delete_calls[] = $key;
            self::$calls[] = ['delete', $key];
            $expiration_key = self::get_expiration_key($key);
            $legacy_timeout_key = self::get_legacy_timeout_key($key);

            self::delete_option_if_exists($key);
            self::delete_option_if_exists($expiration_key);
            self::delete_option_if_exists($legacy_timeout_key);

            unset(
                self::$storage[$key],
                self::$store[$key],
                self::$storage[$expiration_key],
                self::$store[$expiration_key],
                self::$storage[$legacy_timeout_key],
                self::$store[$legacy_timeout_key]
            );

            return false === get_option($key, false)
                && false === get_option($expiration_key, false)
                && false === get_option($legacy_timeout_key, false);
        }

        public static function reset(): void
        {
            self::$storage = [];
            self::$store = [];
            self::$set_calls = [];
            self::$get_calls = [];
            self::$delete_calls = [];
            self::$calls = [];
        }

        private static function get_expiration_key(string $key): string
        {
            return $key . '_expiration';
        }

        private static function get_legacy_timeout_key(string $key): string
        {
            return $key . '_timeout';
        }

        private static function upsert_option_no_autoload(string $key, $value): bool
        {
            $sentinel = self::get_sentinel();
            $existing = get_option($key, $sentinel);
            if ($existing === $sentinel) {
                return add_option($key, $value, '', 'no');
            }

            if (false === update_option($key, $value, 'no')) {
                $current = get_option($key, $sentinel);
                return $current !== $sentinel;
            }

            return true;
        }

        private static function delete_option_if_exists(string $key): void
        {
            delete_option($key);
        }

        private static function write_option_no_autoload(string $key, $value): bool
        {
            if (get_option($key, false) === false) {
                return add_option($key, $value, '', 'no');
            }

            $updated = update_option($key, $value, 'no');
            if ($updated) {
                return true;
            }

            return self::option_values_match(get_option($key, null), $value);
        }

        private static function option_values_match($stored, $expected): bool
        {
            if (is_int($stored) || is_int($expected)) {
                return (int) $stored === (int) $expected;
            }

            if (is_float($stored) || is_float($expected)) {
                return (string) $stored === (string) $expected;
            }

            return $stored == $expected;
        }

        private static function clear_static_cache_for_key(string $key): void
        {
            unset(
                self::$storage[$key],
                self::$store[$key],
                self::$storage[self::get_expiration_key($key)],
                self::$store[self::get_expiration_key($key)],
                self::$storage[self::get_legacy_timeout_key($key)],
                self::$store[self::get_legacy_timeout_key($key)]
            );
        }
    }
}
