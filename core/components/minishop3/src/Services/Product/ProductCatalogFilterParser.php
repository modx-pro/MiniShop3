<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

/**
 * Parse / validate public product/list filter query params (no MODX / SQL).
 */
final class ProductCatalogFilterParser
{
    public const MAX_PARENT_IDS = 50;
    public const MAX_VENDOR_IDS = 50;
    public const MAX_OPTION_KEYS = 10;
    public const MAX_OPTION_VALUES = 20;

    /**
     * @param array<string, mixed> $params
     */
    public static function parse(array $params): ProductCatalogFilterSpec
    {
        [$priceMin, $priceMax] = self::parsePriceRange($params);

        return new ProductCatalogFilterSpec(
            parentIds: self::parseParents($params),
            nested: ProductCatalogService::toBool($params['nested'] ?? false),
            priceMin: $priceMin,
            priceMax: $priceMax,
            inStock: ProductCatalogService::toBool($params['in_stock'] ?? false),
            stockMin: self::parseStockMin($params),
            vendorIds: self::parseVendorIds($params),
            flagNew: ProductCatalogService::toBool($params['new'] ?? false),
            flagPopular: ProductCatalogService::toBool($params['popular'] ?? false),
            flagFavorite: ProductCatalogService::toBool($params['favorite'] ?? false),
            options: self::parseOptions($params),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return list<int>
     */
    public static function parseParents(array $params): array
    {
        return self::parsePositiveIdList(
            $params,
            'parents',
            '/^-?\d+$/',
            'ms3_err_catalog_parents_invalid',
            'ms3_err_catalog_parents_limit',
            self::MAX_PARENT_IDS,
            true,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: ?float, 1: ?float}
     */
    public static function parsePriceRange(array $params): array
    {
        $priceMin = self::parseOptionalFloat($params, 'price_min', 'ms3_err_catalog_price_invalid');
        $priceMax = self::parseOptionalFloat($params, 'price_max', 'ms3_err_catalog_price_invalid');

        if (($priceMin !== null && $priceMin < 0) || ($priceMax !== null && $priceMax < 0)) {
            throw new ProductCatalogFilterException('ms3_err_catalog_price_invalid');
        }
        if ($priceMin !== null && $priceMax !== null && $priceMax < $priceMin) {
            throw new ProductCatalogFilterException('ms3_err_catalog_price_range');
        }

        return [$priceMin, $priceMax];
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function parseStockMin(array $params): ?int
    {
        if (!array_key_exists('stock_min', $params) || $params['stock_min'] === '' || $params['stock_min'] === null) {
            return null;
        }

        if (!is_numeric($params['stock_min'])) {
            throw new ProductCatalogFilterException('ms3_err_catalog_stock_invalid');
        }

        $value = (int) $params['stock_min'];
        if ($value < 0) {
            throw new ProductCatalogFilterException('ms3_err_catalog_stock_invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<int>
     */
    public static function parseVendorIds(array $params): array
    {
        return self::parsePositiveIdList(
            $params,
            'vendor_id',
            '/^\d+$/',
            'ms3_err_catalog_vendor_invalid',
            'ms3_err_catalog_vendor_limit',
            self::MAX_VENDOR_IDS,
            false,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, list<string>>
     */
    public static function parseOptions(array $params): array
    {
        if (!array_key_exists('options', $params)) {
            return [];
        }

        $raw = $params['options'];
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || $trimmed === '{}') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                throw new ProductCatalogFilterException('ms3_err_catalog_options_json');
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            throw new ProductCatalogFilterException('ms3_err_catalog_options_json');
        }

        if ($raw === []) {
            return [];
        }

        if (count($raw) > self::MAX_OPTION_KEYS) {
            throw new ProductCatalogFilterException('ms3_err_catalog_options_limit');
        }

        $result = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key) || $key === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new ProductCatalogFilterException('ms3_err_catalog_option_key_invalid');
            }

            $values = self::normalizeOptionValues($value);
            if ($values === []) {
                continue;
            }
            if (count($values) > self::MAX_OPTION_VALUES) {
                throw new ProductCatalogFilterException('ms3_err_catalog_options_limit');
            }
            $result[$key] = $values;
        }

        return $result;
    }

    /**
     * CSV or array of ints; keeps only id > 0.
     * When $rejectEmptyAfterTokens is true (parents), tokens that all discard → 400
     * so we never silently drop an explicit category filter.
     *
     * @param array<string, mixed> $params
     * @return list<int>
     */
    private static function parsePositiveIdList(
        array $params,
        string $key,
        string $pattern,
        string $invalidKey,
        string $limitKey,
        int $max,
        bool $rejectEmptyAfterTokens,
    ): array {
        if (!array_key_exists($key, $params)) {
            return [];
        }

        $ids = [];
        $sawToken = false;
        foreach (self::asList($params[$key]) as $part) {
            if (is_array($part)) {
                throw new ProductCatalogFilterException($invalidKey);
            }
            $trimmed = trim((string) $part);
            if ($trimmed === '' || $trimmed === '0') {
                continue;
            }
            $sawToken = true;
            if (!preg_match($pattern, $trimmed)) {
                throw new ProductCatalogFilterException($invalidKey);
            }
            $id = (int) $trimmed;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($rejectEmptyAfterTokens && $sawToken && $ids === []) {
            throw new ProductCatalogFilterException($invalidKey);
        }
        if (count($ids) > $max) {
            throw new ProductCatalogFilterException($limitKey);
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private static function normalizeOptionValues(mixed $value): array
    {
        $out = [];
        foreach (self::asList($value) as $part) {
            if (is_array($part)) {
                throw new ProductCatalogFilterException('ms3_err_catalog_option_value_invalid');
            }
            $trimmed = trim((string) $part);
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<mixed>
     */
    private static function asList(mixed $value): array
    {
        return is_array($value) ? $value : explode(',', (string) $value);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function parseOptionalFloat(array $params, string $key, string $errorKey): ?float
    {
        if (!array_key_exists($key, $params) || $params[$key] === '' || $params[$key] === null) {
            return null;
        }

        if (!is_numeric($params[$key])) {
            throw new ProductCatalogFilterException($errorKey);
        }

        return (float) $params[$key];
    }
}
