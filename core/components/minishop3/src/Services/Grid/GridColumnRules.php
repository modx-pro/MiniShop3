<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Shared validation for grid column names and SQL identifiers.
 *
 * MySQL 8 reserved words are rejected for new user input (extra fields, relation
 * create/update via typed validators). Phinx backticks would still accept them at
 * DDL time, but unquoted runtime SQL breaks later.
 *
 * Grandfathering (#655):
 * - Extra fields: key validated only on create (update does not rename).
 * - Existing relation FK/displayField: runtime extractors use charset-only checks
 *   plus quoted SQL in RelationColumnSpec; bulk grid save does not re-validate keys.
 *
 * @see Mysql8ReservedKeywords
 * @see https://dev.mysql.com/doc/refman/8.0/en/keywords.html
 */
final class GridColumnRules
{
    public const SQL_IDENTIFIER_PATTERN = '/^[a-z0-9_]+$/i';

    public const CLASSIFY_OK = 'ok';
    public const CLASSIFY_INVALID = 'invalid';
    public const CLASSIFY_RESERVED = 'reserved';

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

    /** @var array<string, true>|null */
    private static ?array $mysql8ReservedLookup = null;

    /**
     * Prefer project alternatives from CLAUDE.md where we have a clear mapping.
     *
     * @var array<string, string>
     */
    private const SUGGESTED_ALTERNATIVES = [
        'order' => 'sort_order',
        'rank' => 'sort_index',
        'groups' => 'user_groups',
        'group' => 'group_key',
        'function' => 'function_name',
        'system' => 'system_flag',
        'key' => 'field_key',
        'index' => 'field_index',
        'table' => 'table_name',
        'column' => 'column_name',
        'select' => 'select_value',
        'where' => 'where_clause',
        'from' => 'from_value',
        'to' => 'to_value',
        'check' => 'check_flag',
        'match' => 'match_value',
        'range' => 'range_value',
        'rows' => 'row_count',
        'row' => 'row_value',
        'window' => 'window_name',
        'over' => 'over_value',
    ];

    /**
     * Charset-only check (letters, digits, underscore). Does not reject reserved words.
     * Use for reading grandfathered stored identifiers when SQL quotes them.
     */
    public static function matchesSqlIdentifierPattern(string $name): bool
    {
        return preg_match(self::SQL_IDENTIFIER_PATTERN, $name) === 1;
    }

    /**
     * @return self::CLASSIFY_OK|self::CLASSIFY_INVALID|self::CLASSIFY_RESERVED
     */
    public static function classifySqlIdentifier(string $name): string
    {
        if (!self::matchesSqlIdentifierPattern($name)) {
            return self::CLASSIFY_INVALID;
        }

        if (self::isMysqlReservedIdentifier($name)) {
            return self::CLASSIFY_RESERVED;
        }

        return self::CLASSIFY_OK;
    }

    public static function isValidSqlIdentifier(string $name): bool
    {
        return self::classifySqlIdentifier($name) === self::CLASSIFY_OK;
    }

    public static function isMysqlReservedIdentifier(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return isset(self::mysql8ReservedLookup()[strtolower($name)]);
    }

    /**
     * Suggest a non-reserved alternative for UI error messages.
     */
    public static function suggestSqlIdentifierAlternative(string $name): string
    {
        $lower = strtolower($name);
        if (isset(self::SUGGESTED_ALTERNATIVES[$lower])) {
            return self::SUGGESTED_ALTERNATIVES[$lower];
        }

        $candidate = $lower . '_field';
        if (self::isValidSqlIdentifier($candidate)) {
            return $candidate;
        }

        return $lower . '_col';
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

    /**
     * @return array<string, true>
     */
    private static function mysql8ReservedLookup(): array
    {
        return self::$mysql8ReservedLookup ??= array_fill_keys(Mysql8ReservedKeywords::LIST, true);
    }
}
