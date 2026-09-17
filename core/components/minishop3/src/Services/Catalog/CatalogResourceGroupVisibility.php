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
 * Catalog visibility via MODX resource groups (#659 anonymous, #669 authenticated).
 *
 * Membership is OR across a resource's groups (checkPolicy): a document stays
 * visible when any of its groups has an anonymous grant (principal = 0), even if
 * another membership is restricted (#666 review). Authenticated customers add an
 * OR branch for allowed document_group ids resolved from msCustomerGroup → modUserGroup ACL.
 *
 * principal_class is compared to the single FQCN MODX 3 writes
 * ({@see modUserGroup::class}), quoted via the DB connection — manual string
 * literals break MySQL backslash escaping (#666 / #677 / #681 review).
 *
 * Entry points:
 * - {@see apply()} / {@see buildWhereFragment()} — SQL gate (optional allowed ids)
 * - {@see applyForRequest()} — resolve token principals then apply (#669)
 * - {@see isVisible()} — single-id check for Fenom &product= (#670)
 */
final class CatalogResourceGroupVisibility
{
    public const SETTING_KEY = 'ms3_web_catalog_respect_resource_groups';

    private const MODX_ACCESS_RG_ENABLED = 'access_resource_group_enabled';

    /** @var list<string> */
    private const RESOURCE_ALIASES = ['msProduct', 'msCategory'];

    public function __construct(
        private modX $modx,
    ) {
    }

    public function isEnabled(): bool
    {
        return CatalogQuery::toBool($this->modx->getOption(self::MODX_ACCESS_RG_ENABLED, null, true))
            && CatalogQuery::toBool($this->modx->getOption(self::SETTING_KEY, null, true));
    }

    /**
     * @param list<int> $allowedResourceGroupIds Empty → anonymous cache segment (#666).
     */
    public function appliesToCacheKey(array $allowedResourceGroupIds = []): string
    {
        if (!$this->isEnabled()) {
            return '0';
        }

        $ids = self::positiveIntIds($allowedResourceGroupIds);
        if ($ids === []) {
            return '1';
        }

        return '1:' . hash('xxh128', implode(',', $ids));
    }

    /**
     * Apply RG gate using optional API-token principals for this request (#669).
     */
    public function applyForRequest(xPDOQuery $query, string $resourceAlias, string $contextKey): void
    {
        $this->apply(
            $query,
            $resourceAlias,
            $contextKey,
            $this->customerResourceGroupResolver()->resolveAllowedIdsForRequest($contextKey),
        );
    }

    /**
     * Facet/cache segment for the current visitor (#669).
     */
    public function appliesToCacheKeyForRequest(string $contextKey): string
    {
        return $this->appliesToCacheKey(
            $this->customerResourceGroupResolver()->resolveAllowedIdsForRequest($contextKey),
        );
    }

    /**
     * @param list<int> $allowedResourceGroupIds Empty → anonymous gate (#666).
     */
    public function apply(
        xPDOQuery $query,
        string $resourceAlias,
        string $contextKey,
        array $allowedResourceGroupIds = [],
    ): void {
        $fragment = $this->buildWhereFragment($resourceAlias, $contextKey, $allowedResourceGroupIds);
        if ($fragment !== null) {
            $query->where($fragment);
        }
    }

    /**
     * Visibility SQL for pdoTools INNER JOIN ON / xPDO where.
     *
     * Fenom listings pass no allowed ids (anonymous-safe). Member-aware callers
     * pass ids from {@see CustomerResourceGroupResolver} or use {@see applyForRequest()}.
     *
     * @param list<int> $allowedResourceGroupIds
     *
     * @return non-empty-string|null SQL when filtering applies; null when disabled or invalid alias
     */
    public function buildWhereFragment(
        string $resourceAlias,
        string $contextKey,
        array $allowedResourceGroupIds = [],
    ): ?string {
        if (!$this->isEnabled() || $contextKey === '') {
            return null;
        }

        if (!in_array($resourceAlias, self::RESOURCE_ALIASES, true)) {
            return null;
        }

        $dgTable = $this->modx->getTableName(modResourceGroupResource::class);
        $argTable = $this->modx->getTableName(modAccessResourceGroup::class);
        $quotedContext = $this->modx->quote($contextKey);
        $quotedPrincipalClass = $this->modx->quote(modUserGroup::class);

        return self::buildVisibilitySql(
            $dgTable,
            $argTable,
            $resourceAlias,
            $quotedContext,
            $quotedPrincipalClass,
            $allowedResourceGroupIds,
        );
    }

    /**
     * Single-resource visibility (Fenom &product= and similar).
     *
     * @param list<int> $allowedResourceGroupIds Empty → anonymous gate.
     */
    public function isVisible(
        int $resourceId,
        string $resourceAlias = 'msProduct',
        ?string $contextKey = null,
        array $allowedResourceGroupIds = [],
    ): bool {
        if ($resourceId <= 0) {
            return false;
        }

        $contextKey ??= (string) ($this->modx->context->key ?? '');
        if ($contextKey === '') {
            $contextKey = 'web';
        }

        $fragment = $this->buildWhereFragment($resourceAlias, $contextKey, $allowedResourceGroupIds);
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

    private function customerResourceGroupResolver(): CustomerResourceGroupResolver
    {
        if (is_object($this->modx->services)
            && method_exists($this->modx->services, 'has')
            && $this->modx->services->has('ms3_customer_resource_group_resolver')
        ) {
            /** @var CustomerResourceGroupResolver $resolver */
            $resolver = $this->modx->services->get('ms3_customer_resource_group_resolver');

            return $resolver;
        }

        return new CustomerResourceGroupResolver($this->modx);
    }

    /**
     * @param list<int> $allowedResourceGroupIds
     * @param string $quotedPrincipalClass Already connection-quoted principal_class
     *                                     (use $modx->quote(modUserGroup::class))
     */
    public static function buildVisibilitySql(
        string $dgTable,
        string $argTable,
        string $alias,
        string $quotedContext,
        string $quotedPrincipalClass,
        array $allowedResourceGroupIds = [],
    ): string {
        if (!in_array($alias, self::RESOURCE_ALIASES, true)) {
            throw new \InvalidArgumentException('Unsupported catalog resource alias for RG visibility');
        }

        $protectedExists = self::buildProtectedMembershipExistsSql(
            $dgTable,
            $argTable,
            $alias,
            $quotedContext,
            $quotedPrincipalClass,
        );
        $ids = self::positiveIntIds($allowedResourceGroupIds);
        if ($ids === []) {
            return 'NOT EXISTS (' . $protectedExists . ')';
        }

        $allowedList = implode(',', $ids);
        // Visible if not protected, OR member of at least one allowed RG (#669 multi-RG OR).
        $allowedExists = "EXISTS (
            SELECT 1
            FROM {$dgTable} AS dg_allow
            WHERE dg_allow.`document` = {$alias}.`id`
                AND dg_allow.`document_group` IN ({$allowedList})
        )";

        return "(NOT EXISTS ({$protectedExists}) OR ({$allowedExists}))";
    }

    /**
     * @param string $quotedPrincipalClass Already connection-quoted principal_class
     */
    public static function buildNotExistsSql(
        string $dgTable,
        string $argTable,
        string $alias,
        string $quotedContext,
        string $quotedPrincipalClass,
    ): string {
        return self::buildVisibilitySql(
            $dgTable,
            $argTable,
            $alias,
            $quotedContext,
            $quotedPrincipalClass,
            [],
        );
    }

    /**
     * SELECT body for a document that should be hidden from anonymous visitors.
     *
     * Restricted ACL on any membership, and no principal=0 grant on any of the
     * document's groups (OR across groups — #666 multi-membership).
     *
     * @param string $quotedPrincipalClass Already connection-quoted principal_class
     */
    public static function buildProtectedMembershipExistsSql(
        string $dgTable,
        string $argTable,
        string $alias,
        string $quotedContext,
        string $quotedPrincipalClass,
    ): string {
        if (!in_array($alias, self::RESOURCE_ALIASES, true)) {
            throw new \InvalidArgumentException('Unsupported catalog resource alias for RG visibility');
        }

        $contextPred = static function (string $aclAlias) use ($quotedContext): string {
            return "({$aclAlias}.`context_key` = {$quotedContext}"
                . " OR {$aclAlias}.`context_key` = ''"
                . " OR {$aclAlias}.`context_key` IS NULL)";
        };

        // principal_class equality matches modResource::findPolicy() / loadAttributes().
        return "
            SELECT 1
            FROM {$dgTable} AS dg
            INNER JOIN {$argTable} AS arg
                ON arg.`target` = dg.`document_group`
                AND arg.`principal_class` = {$quotedPrincipalClass}
                AND arg.`principal` <> 0
                AND {$contextPred('arg')}
            WHERE dg.`document` = {$alias}.`id`
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$dgTable} AS dg_anon
                    INNER JOIN {$argTable} AS arg_anon
                        ON arg_anon.`target` = dg_anon.`document_group`
                        AND arg_anon.`principal_class` = {$quotedPrincipalClass}
                        AND arg_anon.`principal` = 0
                        AND {$contextPred('arg_anon')}
                    WHERE dg_anon.`document` = {$alias}.`id`
                )
        ";
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private static function positiveIntIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids);

        return $ids;
    }
}
