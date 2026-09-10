<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modResourceGroupResource;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Anonymous MVP: hide catalog resources that belong to a MODX resource group
 * with Resource Group Access ACL for the request context (#659).
 *
 * Matches core anonymous policy loading: principal = 0 («(anonymous)») is an
 * explicit grant. Membership is OR across a resource's groups (checkPolicy):
 * a document stays visible when any of its groups has an anonymous grant,
 * even if another membership is restricted (#666 review).
 */
final class CatalogResourceGroupVisibility
{
    public const SETTING_KEY = 'ms3_web_catalog_respect_resource_groups';

    private const MODX_ACCESS_RG_ENABLED = 'access_resource_group_enabled';

    /**
     * Same principal_class forms MODX writes / accepts for user-group ACL.
     *
     * @var list<string>
     */
    public const PRINCIPAL_CLASSES = [
        modUserGroup::class,
        'modUserGroup',
        'MODX\\Revolution\\modUserGroup',
    ];

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
        $principalIn = self::principalClassInList();
        $contextPred = function (string $aclAlias) use ($quotedContext): string {
            return "({$aclAlias}.`context_key` = {$quotedContext}"
                . " OR {$aclAlias}.`context_key` = ''"
                . " OR {$aclAlias}.`context_key` IS NULL)";
        };

        // Hide only when the document has a restricted membership and none of
        // its groups carry an explicit principal=0 grant (OR across groups).
        return "(
            NOT EXISTS (
                SELECT 1
                FROM {$dgTable} AS dg
                INNER JOIN {$argTable} AS arg
                    ON arg.`target` = dg.`document_group`
                    AND arg.`principal_class` IN ({$principalIn})
                    AND arg.`principal` <> 0
                    AND {$contextPred('arg')}
                WHERE dg.`document` = {$alias}.`id`
            )
            OR EXISTS (
                SELECT 1
                FROM {$dgTable} AS dg_anon
                INNER JOIN {$argTable} AS arg_anon
                    ON arg_anon.`target` = dg_anon.`document_group`
                    AND arg_anon.`principal_class` IN ({$principalIn})
                    AND arg_anon.`principal` = 0
                    AND {$contextPred('arg_anon')}
                WHERE dg_anon.`document` = {$alias}.`id`
            )
        )";
    }

    /**
     * Quoted IN-list for principal_class (SQL string literals).
     */
    public static function principalClassInList(): string
    {
        $parts = [];
        foreach (self::PRINCIPAL_CLASSES as $class) {
            $parts[] = "'" . str_replace("'", "''", $class) . "'";
        }

        return implode(',', $parts);
    }
}
