<?php

namespace MiniShop3\Services\Inventory;

/**
 * Stock identity: product_id on msProductData plus an optional variant
 * (SKU / options hash) supplied by an addon via `msOnInventoryResolveKey`.
 *
 * Without addons, variantKey is null and accounting is by product, exactly
 * as before (#1001). Empty string is normalized to null.
 *
 * Variant keys are canonicalized here (case, comma-list order, option order)
 * so that the same SKU resolved by different addons — or re-resolved on a
 * later order edit / status change — always addresses the same ledger row.
 * Strings exceeding 128 characters are hashed (xxh128) *after* canonicalization
 * to fit the VARCHAR(128) ledger column.
 */
final class InventoryKey
{
    public readonly ?string $variantKey;

    public function __construct(
        public readonly int $productId,
        ?string $variantKey = null,
    ) {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('InventoryKey productId must be positive');
        }
        $variantKey = self::canonicalize($variantKey);
        // Limit variantKey length to fit VARCHAR(128) column; hash long keys
        if ($variantKey !== '' && strlen($variantKey) > 128) {
            $variantKey = hash('xxh128', $variantKey);
        }
        $this->variantKey = $variantKey === '' ? null : $variantKey;
    }

    /**
     * Rebuild a key from a ledger/order identity ("123" or "123|sku").
     */
    public static function fromIdentity(string $identity): self
    {
        $parts = explode('|', $identity, 2);

        return new self((int) $parts[0], $parts[1] ?? null);
    }

    /**
     * Stable grouping identity for order lines: "123" or "123|sku-hash".
     */
    public function identity(): string
    {
        return $this->variantKey === null
            ? (string) $this->productId
            : $this->productId . '|' . $this->variantKey;
    }

    /**
     * Ledger value for variant_key column: '' is product-level (see migration).
     */
    public function ledgerVariantKey(): string
    {
        return $this->variantKey ?? '';
    }

    public function isVariant(): bool
    {
        return $this->variantKey !== null;
    }

    /**
     * Case-fold, sort comma lists and sort option pairs so equivalent SKU
     * descriptions produce one identity. JSON option objects are key-sorted
     * recursively; `key=value` / `key:value` pairs separated by `;` are sorted
     * by name. Unknown SKU strings are only case-folded and trimmed.
     */
    private static function canonicalize(?string $variantKey): string
    {
        $value = trim((string) ($variantKey ?? ''));
        if ($value === '') {
            return '';
        }
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        if ($value[0] === '{' || $value[0] === '[') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = json_encode(
                    self::sortAssoc($decoded),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                if (is_string($value)) {
                    return $value;
                }
            }
        }

        if (str_contains($value, ';')) {
            $parts = array_values(array_filter(array_map('trim', explode(';', $value)), 'strlen'));
            if ($parts !== [] && self::isOptionList($parts)) {
                $pairs = [];
                foreach ($parts as $part) {
                    $separator = strpos($part, '=');
                    if ($separator === false) {
                        $separator = strpos($part, ':');
                    }
                    if ($separator === false) {
                        $separator = 0;
                    }
                    $pairs[] = [substr($part, 0, $separator), $part];
                }
                usort($pairs, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
                $value = implode(';', array_column($pairs, 1));
            }
        }

        if (str_contains($value, ',')) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
            sort($parts, SORT_STRING);
            $value = implode(',', $parts);
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function sortAssoc(array $value): array
    {
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortAssoc($item);
            }
        }
        if (!$isList) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    /**
     * @param list<string> $parts
     */
    private static function isOptionList(array $parts): bool
    {
        foreach ($parts as $part) {
            if (!preg_match('/^[^=:]+[=:].+/', $part)) {
                return false;
            }
        }

        return true;
    }
}
