<?php

namespace MiniShop3\Utils;

/**
 * Normalizes client-supplied id lists into a deduplicated list of positive ints.
 *
 * Shared by API controllers that accept ids as a JSON array, a comma-separated
 * string, or a native array (query params, JSON bodies, bulk payloads).
 */
final class IntArrayDecoder
{
    /**
     * @param mixed $input JSON array, comma-separated string, or array of ids.
     * @return list<int> Deduplicated positive ints, preserving first-seen order.
     */
    public static function decode($input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                $input = explode(',', $input);
            }
        }

        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int)$value;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
