<?php

namespace MiniShop3\Utils;

use MiniShop3\Model\msCategory;
use MODX\Revolution\modResource;

/**
 * Shared SQL helpers + class-key sets for the resource category tree.
 *
 * Used by both the product Categories tab ({@see \MiniShop3\Services\Product\ProductCategoryTreeService})
 * and the Options category picker ({@see \MiniShop3\Controllers\Api\Manager\OptionsController}).
 *
 * Node visibility rules (no system settings needed):
 *   - msCategory: visible and selectable;
 *   - MODX folder resources / web links: visible for navigation only;
 *   - msProduct and plain content resources: excluded.
 *
 * Requires the using class to expose a public `modx` property (modX).
 */
trait ResourceCategoryTreeQueryTrait
{
    /**
     * @return string[] FQCN + short name — resources selectable as categories.
     */
    protected function treeCategoryClassKeys(): array
    {
        return [
            msCategory::class,
            'msCategory',
        ];
    }

    /**
     * @return string[] Navigation containers visible in the tree.
     */
    protected function treeContainerClassKeys(): array
    {
        return [
            modResource::class,
            'MODX\\Revolution\\modDocument',
            'MODX\\Revolution\\modWebLink',
            'modResource',
            'modDocument',
            'modWebLink',
        ];
    }

    protected function getTreeNodeSqlFilter(string $alias, string $treeClassKeysSql, string $categoryClassKeysSql): string
    {
        return "(`{$alias}`.`class_key` IN ({$treeClassKeysSql}) "
            . "AND (`{$alias}`.`class_key` IN ({$categoryClassKeysSql}) OR `{$alias}`.`isfolder` = 1))";
    }

    /**
     * @return string[] Category + container class keys, deduplicated.
     */
    protected function getTreeClassKeys(): array
    {
        return array_values(array_unique(array_merge(
            $this->treeCategoryClassKeys(),
            $this->treeContainerClassKeys()
        )));
    }

    protected function isCategoryClass(string $classKey): bool
    {
        return in_array($classKey, $this->treeCategoryClassKeys(), true);
    }

    /**
     * @param string[] $values
     */
    protected function quoteSqlStringList(array $values): string
    {
        return implode(', ', array_map(fn(string $value): string => $this->modx->quote($value), $values));
    }
}
