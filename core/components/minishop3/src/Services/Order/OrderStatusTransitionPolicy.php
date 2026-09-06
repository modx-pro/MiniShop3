<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

/**
 * Additive allow-list for order status edges (issue #592).
 *
 * Empty setting → only default final/fixed rules apply.
 * Valid non-empty config → final/fixed still apply, and the edge must appear in the list.
 * Unparseable config → invalid (caller must reject transitions with a lexicon error).
 *
 * Formats:
 * - CSV pairs: "2:3,3:4,2:5"
 * - JSON array: [[2,3],[3,4]]
 */
final class OrderStatusTransitionPolicy
{
    public const MODE_OFF = 0;
    public const MODE_ON = 1;
    public const MODE_INVALID = 2;

    /**
     * @return array{mode: int, edges: array<int, array<int, true>>}
     */
    public static function resolve(mixed $raw): array
    {
        if ($raw === null) {
            return ['mode' => self::MODE_OFF, 'edges' => []];
        }

        if (is_array($raw)) {
            return ['mode' => self::MODE_ON, 'edges' => self::fromPairList($raw)];
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return ['mode' => self::MODE_OFF, 'edges' => []];
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return ['mode' => self::MODE_INVALID, 'edges' => []];
            }

            return ['mode' => self::MODE_ON, 'edges' => self::fromPairList($decoded)];
        }

        $pairs = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) !== 2) {
                return ['mode' => self::MODE_INVALID, 'edges' => []];
            }
            $pairs[] = $parts;
        }

        $edges = self::fromPairList($pairs);
        if ($edges === []) {
            return ['mode' => self::MODE_INVALID, 'edges' => []];
        }

        return ['mode' => self::MODE_ON, 'edges' => $edges];
    }

    /**
     * @param array<int, mixed> $pairs
     * @return array<int, array<int, true>>
     */
    private static function fromPairList(array $pairs): array
    {
        $edges = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }
            $from = (int) $pair[0];
            $to = (int) $pair[1];
            if ($from < 1 || $to < 1) {
                continue;
            }
            $edges[$from][$to] = true;
        }

        return $edges;
    }
}
