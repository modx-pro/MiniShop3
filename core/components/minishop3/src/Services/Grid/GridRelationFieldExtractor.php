<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Groups relation-type grid columns for JOIN building.
 */
final class GridRelationFieldExtractor
{
    /** @var array<string, string> */
    private const MODEL_MAP = [
        'msOrder' => 'MiniShop3\\Model\\msOrder',
        'ms3_orders' => 'MiniShop3\\Model\\msOrder',
        'msOrderStatus' => 'MiniShop3\\Model\\msOrderStatus',
        'ms3_order_statuses' => 'MiniShop3\\Model\\msOrderStatus',
        'msOrderAddress' => 'MiniShop3\\Model\\msOrderAddress',
        'ms3_order_addresses' => 'MiniShop3\\Model\\msOrderAddress',
        'msOrderProduct' => 'MiniShop3\\Model\\msOrderProduct',
        'ms3_order_products' => 'MiniShop3\\Model\\msOrderProduct',
        'msDelivery' => 'MiniShop3\\Model\\msDelivery',
        'ms3_deliveries' => 'MiniShop3\\Model\\msDelivery',
        'msPayment' => 'MiniShop3\\Model\\msPayment',
        'ms3_payments' => 'MiniShop3\\Model\\msPayment',
        'msProduct' => 'MiniShop3\\Model\\msProduct',
        'ms3_products' => 'MiniShop3\\Model\\msProduct',
        'msProductData' => 'MiniShop3\\Model\\msProductData',
        'ms3_product_data' => 'MiniShop3\\Model\\msProductData',
        'msCategory' => 'MiniShop3\\Model\\msCategory',
        'msCategoryMember' => 'MiniShop3\\Model\\msCategoryMember',
        'ms3_category_members' => 'MiniShop3\\Model\\msCategoryMember',
        'msVendor' => 'MiniShop3\\Model\\msVendor',
        'ms3_vendors' => 'MiniShop3\\Model\\msVendor',
        'msCustomer' => 'MiniShop3\\Model\\msCustomer',
        'ms3_customers' => 'MiniShop3\\Model\\msCustomer',
        'modUser' => 'MODX\\Revolution\\modUser',
        'modUserProfile' => 'MODX\\Revolution\\modUserProfile',
        'modResource' => 'MODX\\Revolution\\modResource',
        'site_content' => 'MODX\\Revolution\\modResource',
    ];

    /**
     * @param list<array<string, mixed>> $gridFields
     * @return array<string, array{
     *   table: string,
     *   modelClass: ?string,
     *   foreignKey: string,
     *   alias: string,
     *   fields: list<array{name: string, displayField: string}>
     * }>
     */
    public function extract(array $gridFields): array
    {
        $relationGroups = [];

        foreach ($gridFields as $field) {
            if (($field['type'] ?? 'model') !== 'relation') {
                continue;
            }

            $relation = $field['relation'] ?? null;
            if (!$this->isCompleteRelation($relation)) {
                continue;
            }

            $table = (string) $relation['table'];
            $foreignKey = (string) $relation['foreignKey'];
            $displayField = (string) $relation['displayField'];
            $fieldName = (string) ($field['name'] ?? '');

            if (!GridColumnRules::isValidSqlIdentifier($foreignKey)
                || !GridColumnRules::isValidSqlIdentifier($displayField)
            ) {
                continue;
            }

            $groupKey = "{$table}_{$foreignKey}";

            if (!isset($relationGroups[$groupKey])) {
                $tableKey = $this->relationTableKey($relation);
                $relationGroups[$groupKey] = [
                    'table' => $table,
                    'modelClass' => $this->resolveModelClassFromRelation($relation),
                    'foreignKey' => $foreignKey,
                    'alias' => "rel_{$tableKey}_{$foreignKey}",
                    'fields' => [],
                ];
            }

            $relationGroups[$groupKey]['fields'][] = [
                'name' => $fieldName,
                'displayField' => $displayField,
            ];
        }

        return $relationGroups;
    }

    /**
     * @param mixed $relation
     * @phpstan-assert-if-true array{table: mixed, foreignKey: mixed, displayField: mixed} $relation
     */
    private function isCompleteRelation(mixed $relation): bool
    {
        return is_array($relation)
            && !empty($relation['table'])
            && !empty($relation['foreignKey'])
            && !empty($relation['displayField']);
    }

    /**
     * @param array<string, mixed> $relation
     */
    private function resolveModelClassFromRelation(array $relation): ?string
    {
        $resolvedModelClass = $relation['resolvedModelClass'] ?? null;
        if (is_string($resolvedModelClass) && $resolvedModelClass !== '' && class_exists($resolvedModelClass)) {
            return $resolvedModelClass;
        }

        $table = (string) ($relation['table'] ?? '');
        if ($table !== '' && str_contains($table, '\\') && class_exists($table)) {
            return $table;
        }

        if (isset(self::MODEL_MAP[$table])) {
            return self::MODEL_MAP[$table];
        }

        $resolvedTableName = (string) ($relation['resolvedTableName'] ?? '');
        if ($resolvedTableName !== '') {
            $normalized = $this->stripKnownPrefixes($resolvedTableName);
            if (isset(self::MODEL_MAP[$normalized])) {
                return self::MODEL_MAP[$normalized];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $relation
     */
    private function relationTableKey(array $relation): string
    {
        $table = (string) ($relation['table'] ?? '');
        if (str_contains($table, '\\')) {
            $parts = explode('\\', $table);

            return (string) end($parts);
        }

        return $this->stripKnownPrefixes($table);
    }

    private function stripKnownPrefixes(string $tableName): string
    {
        $tableName = trim($tableName);
        // Keep ms3_* keys in MODEL_MAP; strip table_prefix-style modx_ for map lookup.
        if (str_starts_with($tableName, 'modx_')) {
            $tableName = substr($tableName, strlen('modx_'));
        }

        return $tableName;
    }
}
