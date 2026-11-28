<?php

namespace MiniShop3\Utils;

/**
 * Parses comma-separated snippet parameters into a clean, reindexed list.
 *
 * array_diff() drops empty entries but keeps their original keys, so a value
 * like ",material,length" leaves no element at index 0. Callers that checked
 * $list[0] silently skipped filtering (#842).
 */
final class CommaList
{
    /**
     * @param mixed $value Comma-separated string, array, or null.
     * @return list<string> Trimmed non-empty values with consecutive keys.
     */
    public static function parse($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);
        $parsed = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $parsed[] = $item;
            }
        }

        return $parsed;
    }
}
