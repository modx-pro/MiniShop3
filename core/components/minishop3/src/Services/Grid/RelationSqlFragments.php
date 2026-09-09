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
        // Names may arrive already quoted (e.g. xPDO getTableName). Strip then wrap.
        // Charset-validated identifiers never contain backticks, so no `` escape needed.
        return '`' . self::stripIdentQuotes($name) . '`';
    }

    /**
     * Remove MySQL identifier quotes before re-quoting or prefix checks.
     */
    public static function stripIdentQuotes(string $name): string
    {
        return str_replace('`', '', $name);
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

    /**
     * Aggregate SELECT for customer-grid relation columns (JOIN direction: relation.fk = customers.id).
     *
     * Uses charset-only identifier checks (grandfather reserved words) + quoting, same as RelationColumnSpec.
     *
     * @param list<int> $customerIds
     */
    public static function customerRelationAggregateSql(
        string $customersTable,
        string $relationTable,
        string $foreignKey,
        string $displayField,
        ?string $aggregation,
        array $customerIds,
    ): ?string {
        $ids = array_values(array_filter(array_map('intval', $customerIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return null;
        }

        $customersTable = self::stripIdentQuotes($customersTable);
        $relationTable = self::stripIdentQuotes($relationTable);
        $foreignKey = self::stripIdentQuotes($foreignKey);
        $displayField = self::stripIdentQuotes($displayField);

        foreach ([$customersTable, $relationTable, $foreignKey, $displayField] as $ident) {
            if (!GridColumnRules::matchesSqlIdentifierPattern($ident)) {
                return null;
            }
        }

        $agg = ($aggregation !== null && $aggregation !== '') ? strtoupper($aggregation) : null;
        if ($agg !== null && !in_array($agg, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
            return null;
        }

        $column = self::columnRef($relationTable, $displayField);
        $selectExpr = $agg !== null ? "{$agg}({$column})" : $column;
        $customersId = self::columnRef($customersTable, 'id');
        $joinOn = self::columnRef($relationTable, $foreignKey) . ' = ' . $customersId;

        return "
                SELECT
                    {$customersId} as customer_id,
                    {$selectExpr} as field_value
                FROM " . self::quoteIdent($customersTable) . "
                LEFT JOIN " . self::quoteIdent($relationTable) . " ON {$joinOn}
                WHERE {$customersId} IN (" . implode(',', $ids) . ")
                GROUP BY {$customersId}
            ";
    }
}
