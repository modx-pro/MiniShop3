<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
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
 *
 * principal_class is compared to the single FQCN MODX 3 writes
 * ({@see modUserGroup::class}), quoted via the DB connection — manual string
 * literals break MySQL backslash escaping (#666 / #681 review).
 *
 * Entry points:
 * - {@see apply()} — xPDO WHERE for Web API / cart add
 * - {@see buildWhereFragment()} — SQL for Fenom/pdoTools INNER JOIN ON (#670)
 * - {@see isVisible()} — single-id check for Fenom &product= (#670)
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
        $fragment = $this->buildWhereFragment($resourceAlias, $contextKey);
        if ($fragment !== null) {
            $query->where($fragment);
        }
    }

    /**
     * Visibility SQL fragment for pdoTools INNER JOIN ON / WHERE (anonymous-safe).
     *
     * Same SQL as {@see apply()}. Fenom listings stay anonymous-safe; member-aware
     * catalog/cart for logged-in customers is #669.
     *
     * @return non-empty-string|null SQL when filtering applies; null when disabled or invalid alias
     */
    public function buildWhereFragment(string $resourceAlias, string $contextKey): ?string
    {
        if (!$this->isEnabled() || $contextKey === '') {
            return null;
        }

        if (!in_array($resourceAlias, ['msProduct', 'msCategory'], true)) {
            return null;
        }

        $dgTable = $this->modx->getTableName(modResourceGroupResource::class);
        $argTable = $this->modx->getTableName(modAccessResourceGroup::class);
        $quotedContext = $this->modx->quote($contextKey);
        $quotedPrincipalClass = $this->modx->quote(modUserGroup::class);

        return self::buildNotExistsSql(
            $dgTable,
            $argTable,
            $resourceAlias,
            $quotedContext,
            $quotedPrincipalClass,
        );
    }

    /**
     * Anonymous-safe visibility for a single catalog resource (same ACL as {@see apply()}).
     */
    public function isVisible(int $resourceId, string $resourceAlias = 'msProduct', ?string $contextKey = null): bool
    {
        if ($resourceId <= 0) {
            return false;
        }

        $contextKey ??= (string) ($this->modx->context->key ?? '');
        if ($contextKey === '') {
            $contextKey = 'web';
        }

        $fragment = $this->buildWhereFragment($resourceAlias, $contextKey);
        if ($fragment === null) {
            return true;
        }

        $class = $resourceAlias === 'msCategory' ? msCategory::class : msProduct::class;
        $query = $this->modx->newQuery($class);
        // getCount() does not add derivative class_key (unlike getObject()); pin it
        // so a raw id from a request cannot match a non-product/non-category resource.
        $query->where([
            'id' => $resourceId,
            'class_key' => $class,
        ]);
        $query->where($fragment);

        return $this->modx->getCount($class, $query) > 0;
    }

    /**
     * @param string $quotedContext Already connection-quoted context_key literal
     * @param string $quotedPrincipalClass Already connection-quoted principal_class
     *                                     (use $modx->quote(modUserGroup::class))
     */
    public static function buildNotExistsSql(
        string $dgTable,
        string $argTable,
        string $alias,
        string $quotedContext,
        string $quotedPrincipalClass,
    ): string {
        $contextPred = function (string $aclAlias) use ($quotedContext): string {
            return "({$aclAlias}.`context_key` = {$quotedContext}"
                . " OR {$aclAlias}.`context_key` = ''"
                . " OR {$aclAlias}.`context_key` IS NULL)";
        };

        // Hide only when the document has a restricted membership and none of
        // its groups carry an explicit principal=0 grant (OR across groups).
        // principal_class equality matches modResource::findPolicy() / loadAttributes().
        return "(
            NOT EXISTS (
                SELECT 1
                FROM {$dgTable} AS dg
                INNER JOIN {$argTable} AS arg
                    ON arg.`target` = dg.`document_group`
                    AND arg.`principal_class` = {$quotedPrincipalClass}
                    AND arg.`principal` <> 0
                    AND {$contextPred('arg')}
                WHERE dg.`document` = {$alias}.`id`
            )
            OR EXISTS (
                SELECT 1
                FROM {$dgTable} AS dg_anon
                INNER JOIN {$argTable} AS arg_anon
                    ON arg_anon.`target` = dg_anon.`document_group`
                    AND arg_anon.`principal_class` = {$quotedPrincipalClass}
                    AND arg_anon.`principal` = 0
                    AND {$contextPred('arg_anon')}
                WHERE dg_anon.`document` = {$alias}.`id`
            )
        )";
    }
}
