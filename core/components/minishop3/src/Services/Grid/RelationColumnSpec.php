<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

/**
 * Specification for a relation field exposed as a category-products grid column (JOIN + SELECT).
 */
final readonly class RelationColumnSpec
{
    public function __construct(
        public string $fieldName,
        public string $displayField,
        public string $modelClass,
        public string $alias,
        public string $foreignKey,
        public string $localAlias,
    ) {
    }

    /**
     * @param array<string, mixed> $group Relation group from GridConfigService::extractRelationFields()
     * @param array<string, mixed> $fieldDef Single field definition within the group
     */
    public static function fromGroupField(array $group, array $fieldDef): ?self
    {
        $modelClass = $group['modelClass'] ?? null;
        if (empty($modelClass)) {
            return null;
        }

        $foreignKey = (string) ($group['foreignKey'] ?? '');
        $alias = (string) ($group['alias'] ?? '');
        $fieldName = (string) ($fieldDef['name'] ?? '');
        $displayField = (string) ($fieldDef['displayField'] ?? '');

        if ($foreignKey === '' || $alias === '' || $fieldName === '' || $displayField === '') {
            return null;
        }

        if (!self::isSafeSqlIdentifier($foreignKey)
            || !self::isSafeSqlIdentifier($displayField)
            || !self::isSafeSqlIdentifier($fieldName)
            || !self::isSafeSqlIdentifier($alias)
        ) {
            return null;
        }

        if (!GridColumnRules::isValidCategoryProductExtraFieldName($fieldName)) {
            return null;
        }

        return new self(
            $fieldName,
            $displayField,
            (string) $modelClass,
            $alias,
            $foreignKey,
            self::resolveLocalAlias($foreignKey),
        );
    }

    public static function resolveLocalAlias(string $foreignKey): string
    {
        return ProductDataForeignKeys::contains($foreignKey) ? 'Data' : 'msProduct';
    }

    public function joinCondition(): string
    {
        return "`{$this->alias}`.id = {$this->localAlias}.{$this->foreignKey}";
    }

    public function selectExpression(): string
    {
        return "`{$this->alias}`.{$this->displayField} AS `{$this->fieldName}`";
    }

    public function sortExpression(): string
    {
        return "`{$this->alias}`.{$this->displayField}";
    }

    private static function isSafeSqlIdentifier(string $name): bool
    {
        return GridColumnRules::isValidSqlIdentifier($name);
    }
}
