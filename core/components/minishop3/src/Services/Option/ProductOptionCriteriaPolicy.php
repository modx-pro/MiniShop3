<?php

namespace MiniShop3\Services\Option;

/**
 * Whitelist for msProductOption lookups used by option type handlers.
 *
 * Threat model:
 * - getValue()/getRowValue() must not pass arbitrary criteria keys into xPDO.
 * - Callers may supply extra fields (e.g. value, SQL operators); only product_id + key are kept.
 * - key must match [a-z0-9_]+ (same rule as OptionColumnSpec::isValidOptionKey()).
 * - Authorization for product_id (IDOR) is the caller's responsibility; this policy only validates shape.
 */
final class ProductOptionCriteriaPolicy
{
    /** Same rule as {@see \MiniShop3\Services\Grid\OptionColumnSpec::isValidOptionKey()} */
    private const KEY_PATTERN = '/^[a-z0-9_]+$/i';

    /**
     * @return array{product_id: int, key: string}|null Safe criteria or null when invalid
     */
    public static function normalize(mixed $criteria): ?array
    {
        if (!is_array($criteria)) {
            return null;
        }

        if (!array_key_exists('product_id', $criteria) || !array_key_exists('key', $criteria)) {
            return null;
        }

        $productId = filter_var($criteria['product_id'], FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            return null;
        }

        $key = $criteria['key'];
        if (!is_string($key) || $key === '' || !preg_match(self::KEY_PATTERN, $key)) {
            return null;
        }

        return [
            'product_id' => (int) $productId,
            'key' => $key,
        ];
    }

    /**
     * Build normalized criteria from primitive inputs (defense in depth at msOption entry).
     *
     * @return array{product_id: int, key: string}|null
     */
    public static function fromProductAndKey(mixed $productId, mixed $key): ?array
    {
        return self::normalize([
            'product_id' => $productId,
            'key' => $key,
        ]);
    }
}
