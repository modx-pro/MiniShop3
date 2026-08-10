<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Shared validation for grid column names and SQL identifiers.
 */
final class GridColumnRules
{
    public const SQL_IDENTIFIER_PATTERN = '/^[a-z0-9_]+$/i';

    /**
     * Builtin product / product-data column names emitted by CategoryProductsListService.
     * Extra columns (option, relation) must not collide with these.
     */
    public const RESERVED_CATEGORY_PRODUCT_FIELD_NAMES = [
        'id', 'pagetitle', 'longtitle', 'alias', 'parent', 'menuindex',
        'published', 'deleted', 'hidemenu', 'createdon', 'editedon',
        'article', 'price', 'old_price', 'weight', 'image', 'thumb',
        'vendor_id', 'made_in', 'new', 'popular', 'favorite',
        'preview_url', 'category_name',
    ];

    public static function isValidSqlIdentifier(string $name): bool
    {
        return (bool) preg_match(self::SQL_IDENTIFIER_PATTERN, $name);
    }

    /**
     * fieldName for category-products extra columns: safe SQL alias + no builtin collision.
     */
    public static function isValidCategoryProductExtraFieldName(string $name): bool
    {
        if (!self::isValidSqlIdentifier($name)) {
            return false;
        }

        return !in_array(strtolower($name), self::RESERVED_CATEGORY_PRODUCT_FIELD_NAMES, true);
    }
}
