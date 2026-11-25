<?php

namespace MiniShop3\Utils;

/**
 * Distinguishes a missing value from a legitimate zero (#862).
 *
 * PHP's empty() treats 0 and "0" as missing, which silently drops selected
 * options (floor = 0) and address fields from the cart/order draft.
 */
final class ValuePresence
{
    /**
     * Only null and blank strings clear a value. Zero, false, arrays and
     * non-blank strings are kept as-is.
     */
    public static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * Merge new values into current ones; null/blank strings remove the key.
     *
     * Shared by cart option mutation paths so both treat zero identically.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    public static function merge(array $current, array $new): array
    {
        foreach ($new as $key => $value) {
            if (self::isBlank($value)) {
                unset($current[$key]);
            } else {
                $current[$key] = $value;
            }
        }

        return $current;
    }
}
