<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Specification for a product option exposed as a category-products grid column (JOIN + GROUP_CONCAT).
 */
final readonly class OptionColumnSpec
{
    /** Same rule as {@see \MiniShop3\Services\GridConfigService::validateOptionConfig()} */
    private const OPTION_KEY_PATTERN = '/^[a-z0-9_]+$/i';

    /** Same rule as OPTION_KEY_PATTERN; fieldName also lands in `SELECT … AS \`{name}\``. */
    private const FIELD_NAME_PATTERN = '/^[a-z0-9_]+$/i';

    /**
     * Builtin product / product-data column names that already appear in SELECT.
     * If user picks one as option fieldName, PDO FETCH_ASSOC overwrites the builtin
     * with GROUP_CONCAT string → cast in formatProductRow returns 0 / garbage.
     * Disallow at spec creation to prevent silent data corruption.
     */
    private const RESERVED_NAMES = [
        // modResource
        'id', 'pagetitle', 'longtitle', 'alias', 'parent', 'menuindex',
        'published', 'deleted', 'hidemenu', 'createdon', 'editedon',
        // msProductData
        'article', 'price', 'old_price', 'weight', 'image', 'thumb',
        'vendor_id', 'made_in', 'new', 'popular', 'favorite',
        // formatProductRow synthetics
        'preview_url', 'category_name',
    ];

    public function __construct(
        public string $fieldName,
        public string $key,
        public string $alias,
    ) {
    }

    /**
     * @param array<string, mixed> $field Grid field row (merged config from GridConfigService)
     */
    public static function tryFromGridField(array $field): ?self
    {
        if (($field['type'] ?? 'model') !== 'option') {
            return null;
        }

        $option = $field['option'] ?? null;
        if (!is_array($option) || empty($option['key'])) {
            return null;
        }

        $key = (string) $option['key'];
        if (!self::isValidOptionKey($key)) {
            return null;
        }

        $name = $field['name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        if (!self::isValidFieldName((string) $name)) {
            return null;
        }

        return new self((string) $name, $key, 'opt_' . $key);
    }

    public static function isValidOptionKey(string $key): bool
    {
        return (bool) preg_match(self::OPTION_KEY_PATTERN, $key);
    }

    /**
     * fieldName must match [a-z0-9_] (lands in SQL AS clause) and must not
     * collide with any builtin product/data column emitted in the same SELECT.
     */
    public static function isValidFieldName(string $name): bool
    {
        if (!preg_match(self::FIELD_NAME_PATTERN, $name)) {
            return false;
        }
        return !in_array(strtolower($name), self::RESERVED_NAMES, true);
    }

}
