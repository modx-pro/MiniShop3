<?php

namespace MiniShop3\Utils;

/**
 * Distinguishes a missing value from a legitimate zero (#859).
 *
 * PHP's empty() treats 0 and "0" as missing, which silently drops option
 * values in the storefront snippets. false/[] keep the old "clear" semantics.
 */
final class ValuePresence
{
    public static function isBlank(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }

        return is_array($value) && $value === [];
    }

    /**
     * Merge new values into current ones; blank values remove the key.
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
