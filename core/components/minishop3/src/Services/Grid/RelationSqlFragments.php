<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Quoted SQL fragments for relation JOINs/SELECTs (safe with MySQL reserved identifiers).
 */
final class RelationSqlFragments
{
    private function __construct()
    {
    }

    public static function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public static function joinEqualsId(string $alias, string $localAlias, string $foreignKey): string
    {
        return self::quoteIdent($alias) . '.' . self::quoteIdent('id')
            . ' = ' . self::quoteIdent($localAlias) . '.' . self::quoteIdent($foreignKey);
    }

    public static function selectAs(string $alias, string $displayField, string $fieldName): string
    {
        return self::quoteIdent($alias) . '.' . self::quoteIdent($displayField)
            . ' AS ' . self::quoteIdent($fieldName);
    }

    public static function columnRef(string $alias, string $column): string
    {
        return self::quoteIdent($alias) . '.' . self::quoteIdent($column);
    }
}
