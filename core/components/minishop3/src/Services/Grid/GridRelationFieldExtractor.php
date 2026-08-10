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
        'msOrderStatus' => 'MiniShop3\\Model\\msOrderStatus',
        'msOrderAddress' => 'MiniShop3\\Model\\msOrderAddress',
        'msOrderProduct' => 'MiniShop3\\Model\\msOrderProduct',
        'msDelivery' => 'MiniShop3\\Model\\msDelivery',
        'msPayment' => 'MiniShop3\\Model\\msPayment',
        'msProduct' => 'MiniShop3\\Model\\msProduct',
        'msProductData' => 'MiniShop3\\Model\\msProductData',
        'msCategory' => 'MiniShop3\\Model\\msCategory',
        'msCategoryMember' => 'MiniShop3\\Model\\msCategoryMember',
        'msVendor' => 'MiniShop3\\Model\\msVendor',
        'msCustomer' => 'MiniShop3\\Model\\msCustomer',
        'modUser' => 'MODX\\Revolution\\modUser',
        'modUserProfile' => 'MODX\\Revolution\\modUserProfile',
        'modResource' => 'MODX\\Revolution\\modResource',
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
            $groupKey = "{$table}_{$foreignKey}";

            if (!isset($relationGroups[$groupKey])) {
                $relationGroups[$groupKey] = [
                    'table' => $table,
                    'modelClass' => self::MODEL_MAP[$table] ?? null,
                    'foreignKey' => $foreignKey,
                    'alias' => "rel_{$table}_{$foreignKey}",
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
}
