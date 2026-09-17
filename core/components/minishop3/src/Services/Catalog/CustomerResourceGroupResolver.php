<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\CustomerAccess;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Resolve MODX resource group ids a customer may see via linked modUserGroup ACL (#669).
 *
 * Optional API token is resolved without TokenMiddleware; invalid/expired → anonymous gate.
 *
 * Simplifications vs core modUser ACL (intentional for msCustomer without roles):
 * - Any matching access_resource_groups row is a grant; policy contents (view/load) are not inspected.
 * - authority / role rank is not compared (customers have no modUserRole).
 * - principal_class matches only {@see modUserGroup::class}, same as MODX 3 processors.
 *
 * Blocked customers: permanent manager block (empty/null blocked_until) stays closed.
 * AuthManager does not lift manual blocks (#734); only timed lockout is lifted on
 * login or password reset. Expired blocked_until restores catalog groups without
 * mutating flags here.
 */
final class CustomerResourceGroupResolver
{
    /** @var array<string, list<int>> */
    private array $customerCache = [];

    /** @var array<string, list<int>> */
    private array $userGroupCache = [];

    /** @var array<string, int> token string → customer_id (0 = anonymous / invalid) */
    private array $requestCustomerIdByToken = [];

    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * Resolve allowed document_group ids for the current HTTP visitor (#669).
     *
     * Token → customer_id is memoized per resolver instance so facet batches
     * that call applyForRequest / appliesToCacheKeyForRequest many times do
     * not re-hit msCustomerToken for the same Authorization header.
     *
     * @return list<int> Empty = anonymous catalog gate (#666).
     */
    public function resolveAllowedIdsForRequest(string $contextKey): array
    {
        if ($contextKey === '') {
            return [];
        }

        $tokenString = TokenService::resolveTokenFromRequest();
        if ($tokenString === '') {
            return [];
        }

        $customerId = $this->resolveCustomerIdFromToken($tokenString);
        if ($customerId <= 0) {
            return [];
        }

        return $this->resolveAllowedResourceGroupIdsForCustomer($customerId, $contextKey);
    }

    /**
     * @return int customer_id or 0 when token is missing/invalid/expired
     */
    private function resolveCustomerIdFromToken(string $tokenString): int
    {
        if (array_key_exists($tokenString, $this->requestCustomerIdByToken)) {
            return $this->requestCustomerIdByToken[$tokenString];
        }

        $tokenService = $this->tokenService();
        if ($tokenService === null) {
            return $this->requestCustomerIdByToken[$tokenString] = 0;
        }

        $resolved = $tokenService->resolveApiToken($tokenString);
        if (($resolved['reason'] ?? '') !== 'ok') {
            return $this->requestCustomerIdByToken[$tokenString] = 0;
        }

        $tokenObj = $resolved['token'] ?? null;
        if (!$tokenObj instanceof msCustomerToken) {
            return $this->requestCustomerIdByToken[$tokenString] = 0;
        }

        $customerId = (int) $tokenObj->get('customer_id');

        return $this->requestCustomerIdByToken[$tokenString] = $customerId > 0 ? $customerId : 0;
    }

    /**
     * @return list<int> Allowed MODX document_group ids; empty = anonymous catalog gate.
     */
    public function resolveAllowedResourceGroupIdsForCustomer(int $customerId, string $contextKey): array
    {
        if ($customerId <= 0 || $contextKey === '') {
            return [];
        }

        $cacheKey = $customerId . ':' . $contextKey;
        if (array_key_exists($cacheKey, $this->customerCache)) {
            return $this->customerCache[$cacheKey];
        }

        return $this->customerCache[$cacheKey] = $this->loadAllowedIdsForCustomer($customerId, $contextKey);
    }

    /**
     * @param list<int> $userGroupIds MODX modUserGroup ids (principals)
     *
     * @return list<int> Allowed MODX document_group ids for the context.
     */
    public function resolveAllowedResourceGroupIdsForUserGroups(array $userGroupIds, string $contextKey): array
    {
        if ($contextKey === '') {
            return [];
        }

        $userGroupIds = array_values(array_unique(array_filter(
            array_map('intval', $userGroupIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($userGroupIds === []) {
            return [];
        }

        sort($userGroupIds);
        $cacheKey = $contextKey . ':' . implode(',', $userGroupIds);
        if (array_key_exists($cacheKey, $this->userGroupCache)) {
            return $this->userGroupCache[$cacheKey];
        }

        /** @var xPDOQuery $c */
        $c = $this->modx->newQuery(modAccessResourceGroup::class);
        $c->where([
            'principal:IN' => $userGroupIds,
            'principal_class' => modUserGroup::class,
        ]);
        $c->where([
            'context_key' => $contextKey,
            'OR:context_key:=' => '',
            'OR:context_key:IS' => null,
        ]);
        $c->select('DISTINCT modAccessResourceGroup.target AS target');

        $ids = [];
        if (!$c->prepare()) {
            $this->logAclQueryFailure($c, 'prepare');

            return $this->userGroupCache[$cacheKey] = [];
        }
        if ($c->stmt === null || !$c->stmt->execute()) {
            $this->logAclQueryFailure($c, 'execute');

            return $this->userGroupCache[$cacheKey] = [];
        }
        while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
            $target = (int) ($row['target'] ?? 0);
            if ($target > 0) {
                $ids[] = $target;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $this->userGroupCache[$cacheKey] = $ids;
    }

    private function tokenService(): ?TokenService
    {
        if (!is_object($this->modx->services) || !method_exists($this->modx->services, 'has')) {
            return null;
        }
        if (!$this->modx->services->has('ms3_token_service')) {
            return null;
        }

        $service = $this->modx->services->get('ms3_token_service');

        return $service instanceof TokenService ? $service : null;
    }

    /**
     * @return list<int>
     */
    private function loadAllowedIdsForCustomer(int $customerId, string $contextKey): array
    {
        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);
        if ($customer === null) {
            return [];
        }

        if (CustomerAccess::isAccessDenied($customer)) {
            return [];
        }

        $groupId = (int) ($customer->get('customer_group_id') ?? 0);
        if ($groupId <= 0) {
            return [];
        }

        /** @var msCustomerGroup|null $group */
        $group = $this->modx->getObject(msCustomerGroup::class, $groupId);
        if ($group === null || !(bool) $group->get('active')) {
            return [];
        }

        $userGroupId = (int) ($group->get('user_group_id') ?? 0);
        if ($userGroupId <= 0) {
            return [];
        }

        return $this->resolveAllowedResourceGroupIdsForUserGroups([$userGroupId], $contextKey);
    }

    private function logAclQueryFailure(xPDOQuery $c, string $stage): void
    {
        $info = [];
        if (is_object($c->stmt) && method_exists($c->stmt, 'errorInfo')) {
            $info = $c->stmt->errorInfo();
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[MiniShop3] CustomerResourceGroupResolver ACL query ' . $stage
            . ' failed: ' . json_encode($info, JSON_UNESCAPED_UNICODE),
        );
    }
}
