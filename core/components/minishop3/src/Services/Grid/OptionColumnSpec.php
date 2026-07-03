<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Specification for a product option exposed as a category-products grid column (JOIN + GROUP_CONCAT).
 */
final readonly class OptionColumnSpec
{
    /** Same rule as {@see GridColumnRules::SQL_IDENTIFIER_PATTERN} */
    private const OPTION_KEY_PATTERN = GridColumnRules::SQL_IDENTIFIER_PATTERN;

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
        return GridColumnRules::isValidCategoryProductExtraFieldName($name);
    }
}
