<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Allows FIFU Premium to replace Free without loading both runtimes. */
final class Fifu_Free_Premium_Handoff
{
    private const FREE_PLUGIN_BASENAME = 'featured-image-from-url/featured-image-from-url.php';
    private const PREMIUM_PLUGIN_BASENAME = 'fifu-premium/fifu-premium.php';
    private static string $pendingFreePlugin = '';

    public static function maybe_yield(string $free_plugin_file): bool
    {
        self::clearDeferredHandoff();
        $free = plugin_basename($free_plugin_file);
        if (self::networkActive(self::PREMIUM_PLUGIN_BASENAME)) {
            self::deactivateFree($free, true); self::deactivateFree($free, false); return true;
        }
        if (self::siteActive(self::PREMIUM_PLUGIN_BASENAME)) {
            self::deactivateFree($free, false); return true;
        }
        if (!self::premiumActivationRequest()) return false;
        self::$pendingFreePlugin = $free;
        add_action('activated_plugin', [self::class, 'completeHandoffAfterPremiumActivation'], 10, 2);
        return true;
    }

    public static function completeHandoffAfterPremiumActivation($plugin, $network_wide): void
    {
        if (!is_string($plugin)) {
            return;
        }

        $network_wide = (bool) $network_wide;

        if (self::normalizePlugin($plugin) !== self::PREMIUM_PLUGIN_BASENAME) return;
        $free = self::$pendingFreePlugin;
        self::clearDeferredHandoff();
        if ($free === '') return;
        self::deactivateFree($free, $network_wide);
        if ($network_wide) self::deactivateFree($free, false);
    }

    private static function clearDeferredHandoff(): void
    {
        remove_action('activated_plugin', [self::class, 'completeHandoffAfterPremiumActivation'], 10);
        self::$pendingFreePlugin = '';
    }

    private static function siteActive(string $plugin): bool { return in_array($plugin, (array) get_option('active_plugins', []), true); }
    private static function networkActive(string $plugin): bool { return is_multisite() && array_key_exists($plugin, (array) get_site_option('active_sitewide_plugins', [])); }

    private static function premiumActivationRequest(): bool
    {
        if (self::explicitCliActivation()) return true;
        $action = self::requestString('action'); $action2 = self::requestString('action2'); $plugin = self::normalizePlugin(self::requestString('plugin'));
        if (self::ajaxActivationContext()) return $action === 'activate-plugin' && $plugin === self::PREMIUM_PLUGIN_BASENAME;
        if (!self::adminActivationContext()) return false;
        if (in_array('activate', [$action, $action2], true) && $plugin === self::PREMIUM_PLUGIN_BASENAME) return true;
        if (!in_array('activate-selected', [$action, $action2], true)) return false;
        return in_array(self::PREMIUM_PLUGIN_BASENAME, array_map([self::class, 'normalizePlugin'], self::requestArray('checked')), true);
    }

    private static function adminActivationContext(): bool
    {
        return defined('WP_ADMIN') && WP_ADMIN && !self::ajaxActivationContext();
    }

    private static function ajaxActivationContext(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    private static function explicitCliActivation(): bool
    {
        if (!defined('WP_CLI') || !WP_CLI) return false;
        $args = array_values(array_map('strval', (array) ($_SERVER['argv'] ?? [])));
        $index = null;
        for ($i = 0; $i < count($args) - 1; $i++) if ($args[$i] === 'plugin' && $args[$i + 1] === 'activate') { $index = $i; break; }
        if ($index === null) return false;
        $targets = array_slice($args, $index + 2);
        if (in_array('--all', $targets, true)) return false;
        $targets = array_values(array_filter($targets, static fn(string $target): bool => $target !== '' && strpos($target, '--') !== 0));
        $targets = array_map([self::class, 'normalizeCliTarget'], $targets);
        return in_array(self::PREMIUM_PLUGIN_BASENAME, $targets, true) && !in_array(self::FREE_PLUGIN_BASENAME, $targets, true);
    }

    private static function normalizeCliTarget(string $target): string
    {
        $target = self::normalizePlugin($target);
        return $target === 'fifu-premium' ? self::PREMIUM_PLUGIN_BASENAME : ($target === 'featured-image-from-url' ? self::FREE_PLUGIN_BASENAME : $target);
    }

    private static function deactivateFree(string $plugin, bool $network): void
    {
        if (!function_exists('deactivate_plugins')) {
            $file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (!is_file($file)) return;
            require_once $file;
        }
        if (function_exists('deactivate_plugins')) deactivate_plugins($plugin, true, $network);
    }

    private static function requestString(string $key): string
    {
        $value = self::requestValue($key);
        if (!is_scalar($value) && $value !== null) return '';
        $value = (string) ($value ?? '');
        return trim(function_exists('wp_unslash') ? wp_unslash($value) : $value);
    }

    private static function requestArray(string $key): array
    {
        $value = self::requestValue($key);
        if ($value === null) return [];
        $values = is_array($value) ? $value : [$value];
        if (function_exists('wp_unslash')) $values = wp_unslash($values);
        return array_values(array_filter(array_map('strval', $values), static fn(string $item): bool => trim($item) !== ''));
    }

    private static function requestValue(string $key)
    {
        if (array_key_exists($key, $_POST)) return $_POST[$key];
        return $_GET[$key] ?? null;
    }

    private static function normalizePlugin(string $plugin): string { return ltrim(str_replace('\\', '/', rawurldecode(trim($plugin))), '/'); }
}
