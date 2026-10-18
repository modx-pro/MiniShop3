<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Services\Grid\GridColumnRules;

/**
 * Allowlisted ORDER BY columns for the category products grid.
 *
 * preview_url and category_name are row fields built in PHP, so they are absent here.
 * Anything this method does not recognize must fall back to menuindex and never reach SQL.
 */
final class CategoryProductSort
{
    /** @var array<string, 'product'|'data'> */
    public const BUILTIN = [
        'id' => 'product',
        'pagetitle' => 'product',
        'longtitle' => 'product',
        'alias' => 'product',
        'parent' => 'product',
        'published' => 'product',
        'deleted' => 'product',
        'hidemenu' => 'product',
        'createdon' => 'product',
        'editedon' => 'product',
        'article' => 'data',
        'price' => 'data',
        'old_price' => 'data',
        'weight' => 'data',
        'image' => 'data',
        'thumb' => 'data',
        'vendor_id' => 'data',
        'made_in' => 'data',
        'new' => 'data',
        'popular' => 'data',
        'favorite' => 'data',
    ];

    public static function builtinExpression(string $sortBy): ?string
    {
        $table = self::BUILTIN[$sortBy] ?? null;
        if ($table === null) {
            return null;
        }

        $alias = $table === 'product' ? 'msProduct' : 'Data';

        return "`{$alias}`.`{$sortBy}`";
    }

    /**
     * @param array<string, string> $optionSqlByField   field name => already validated SQL
     * @param array<string, string> $relationSqlByField field name => already validated SQL
     * @param array<string, string> $dataColumns        extra msProductData columns (name => phptype)
     */
    public static function expression(
        string $sortBy,
        array $optionSqlByField,
        array $relationSqlByField,
        array $dataColumns,
        string $effectiveMenuindexSql,
    ): string {
        if (isset($optionSqlByField[$sortBy])) {
            return $optionSqlByField[$sortBy];
        }
        if (isset($relationSqlByField[$sortBy])) {
            return $relationSqlByField[$sortBy];
        }
        if (isset($dataColumns[$sortBy]) && GridColumnRules::matchesSqlIdentifierPattern($sortBy)) {
            return "Data.`{$sortBy}`";
        }
        if ($sortBy === 'menuindex' || $sortBy === 'effective_menuindex') {
            return $effectiveMenuindexSql;
        }

        return self::builtinExpression($sortBy) ?? $effectiveMenuindexSql;
    }
}
