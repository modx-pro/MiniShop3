<?php

namespace MiniShop3\Services\Option;

/**
 * Whitelist for msProductOption lookups used by option type handlers.
 *
 * Threat model: getValue()/getRowValue() must not pass arbitrary criteria keys into xPDO.
 * Callers may supply extra fields (e.g. value, SQL operators); only product_id + key are kept.
 */
final class ProductOptionCriteriaPolicy
{
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
        if (!is_string($key) || $key === '') {
            return null;
        }

        return [
            'product_id' => (int) $productId,
            'key' => $key,
        ];
    }
}
