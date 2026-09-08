<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modResourceGroupResource;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Anonymous MVP: hide catalog resources that belong to a MODX resource group
 * with Resource Group Access ACL for the request context (#659).
 */
final class CatalogResourceGroupVisibility
{
    public const SETTING_KEY = 'ms3_web_catalog_respect_resource_groups';

    private const MODX_ACCESS_RG_ENABLED = 'access_resource_group_enabled';

    public function __construct(
        private modX $modx,
    ) {
    }

    public function isEnabled(): bool
    {
        return CatalogQuery::toBool($this->modx->getOption(self::MODX_ACCESS_RG_ENABLED, null, true))
            && CatalogQuery::toBool($this->modx->getOption(self::SETTING_KEY, null, true));
    }

    public function appliesToCacheKey(): string
    {
        return $this->isEnabled() ? '1' : '0';
    }

    public function apply(xPDOQuery $query, string $resourceAlias, string $contextKey): void
    {
        if (!$this->isEnabled() || $contextKey === '') {
            return;
        }

        if (!in_array($resourceAlias, ['msProduct', 'msCategory'], true)) {
            return;
        }

        $dgTable = $this->modx->getTableName(modResourceGroupResource::class);
        $argTable = $this->modx->getTableName(modAccessResourceGroup::class);
        $quotedContext = $this->modx->quote($contextKey);

        $query->where(
            self::buildNotExistsSql($dgTable, $argTable, $resourceAlias, $quotedContext)
        );
    }

    public static function buildNotExistsSql(
        string $dgTable,
        string $argTable,
        string $alias,
        string $quotedContext,
    ): string {
        return "NOT EXISTS (
            SELECT 1
            FROM {$dgTable} AS dg
            INNER JOIN {$argTable} AS arg ON arg.`target` = dg.`document_group`
            WHERE dg.`document` = {$alias}.`id`
                AND (arg.`context_key` = {$quotedContext} OR arg.`context_key` = '' OR arg.`context_key` IS NULL)
        )";
    }
}
