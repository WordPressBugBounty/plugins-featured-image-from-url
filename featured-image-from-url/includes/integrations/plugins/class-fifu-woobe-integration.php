<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Woobe_Integration
{
    public static function register_hooks(): void
    {
        add_filter(
            'woobe_before_update_product_field',
            [self::class, 'on_before_update_product_field'],
            10,
            3
        );
    }

    /**
     * Persist the Free featured-image field handled by WOOBE.
     *
     * Premium gallery/list fields are intentionally ignored in Free.
     *
     * @param mixed $value
     * @param mixed $productId
     * @param mixed $fieldKey
     * @return mixed
     */
    public static function on_before_update_product_field(
        $value,
        $productId,
        $fieldKey
    ) {
        if (!is_string($fieldKey) || $fieldKey !== 'fifu_image_url') {
            return $value;
        }

        $productId = is_numeric($productId) ? (int) $productId : 0;
        if ($productId <= 0) {
            return $value;
        }

        if ($value !== null && !is_scalar($value)) {
            return $value;
        }

        Fifu_Developer_Media_Service::set_image($productId, (string) $value);

        return $value;
    }
}
