<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Category\CategoryProductActionPermissions;
use MiniShop3\Services\Category\CategoryProductDocumentPolicy;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Services\Category\CategoryProductsListService;
use MiniShop3\Services\FilterConfigManager;
use MODX\Revolution\modX;

/**
 * API controller for category products management
 *
 * Handles product listing, filtering, sorting for category page.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class CategoryProductsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Human-readable lexicon entry, or original key when missing/empty translation.
     *
     * @param array<string, scalar|null> $params
     */
    protected function lexiconMessageOrKey(string $key, array $params = []): string
    {
        $text = $this->modx->lexicon($key, $params);

        return ($text !== $key && $text !== '') ? $text : $key;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    protected function errorResponse(string $messageKey, int $status, array $params = []): array
    {
        return Response::error($this->lexiconMessageOrKey($messageKey, $params), $status)->getData();
    }

    /**
     * Get list of products in category with pagination and filtering
     * GET /api/mgr/categories/{id}/products
     *
     * @param array $params URL and query parameters
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $resolved = $this->requireCategoryWithView($categoryId);
        if (is_array($resolved)) {
            return $resolved;
        }

        $start = (int) ($params['start'] ?? 0);
        $limit = (int) ($params['limit'] ?? 20);
        $sortBy = $params['sort'] ?? 'menuindex';
        $sortDir = strtoupper((string) ($params['dir'] ?? 'ASC'));
        $nested = (bool) ($params['nested'] ?? false);

        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'ASC';
        }

        $gridConfig = $this->modx->services->get('ms3_grid_config');
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('category-products', true) : [];

        /** @var CategoryProductsListService|null $listService */
        $listService = $this->modx->services->get('ms3_category_products_list');
        if (!$listService) {
            return $this->errorResponse('ms3_err_category_products_list_service', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $results = $this->collectVisibleListPage(
            $listService,
            $categoryId,
            $params,
            $nested,
            $gridFields,
            $start,
            $limit,
            (string) $sortBy,
            $sortDir
        );

        $total = $this->countVisibleListResults(
            $listService,
            $categoryId,
            $params,
            $nested,
            $gridFields,
            (string) $sortBy,
            $sortDir
        );

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    /**
     * Get filters configuration
     * GET /api/mgr/categories/{id}/products/filters
     *
     * @param array $params
     * @return array Response
     */
    public function getFilters(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $resolved = $this->requireCategoryWithView($categoryId);
        if (is_array($resolved)) {
            return $resolved;
        }

        /** @var FilterConfigManager $filterConfigManager */
        $filterConfigManager = $this->modx->services->get('ms3_filter_config');

        if (!$filterConfigManager) {
            return Response::success(['filters' => $this->getDefaultFilters()])->getData();
        }

        $filters = $filterConfigManager->getFilters('category-products', true);

        return Response::success(['filters' => $filters])->getData();
    }

    /**
     * Sort products (drag-drop reordering)
     * POST /api/mgr/categories/{id}/products/sort
     *
     * @param array $params
     * @return array Response
     */
    public function sort(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $items = $params['items'] ?? [];
        $nested = $this->isNested($params);

        if (empty($items) || !is_array($items)) {
            return $this->errorResponse('ms3_err_items_required', HttpStatus::BAD_REQUEST);
        }

        $resolved = $this->requireCategoryWithView($categoryId);
        if (is_array($resolved)) {
            return $resolved;
        }

        $updated = 0;
        $policyDenied = 0;

        $scope = $this->scopeService();

        foreach ($items as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $menuindex = (int) ($item['menuindex'] ?? 0);

            if (!$productId) {
                continue;
            }

            $product = $scope->findInCategory($categoryId, $productId, $nested);

            if (!$product) {
                continue;
            }

            if (!CategoryProductDocumentPolicy::isAllowedAll($product, CategoryProductDocumentPolicy::sortPolicies())) {
                $policyDenied++;
                $this->logDocumentPolicyDenied($product, CategoryProductDocumentPolicy::sortPolicies());
                continue;
            }

            $product->set('menuindex', $menuindex);
            if ($product->save()) {
                $updated++;
            }
        }

        if ($updated === 0 && $policyDenied > 0) {
            return Response::error(
                'Save permission denied for this document',
                HttpStatus::FORBIDDEN
            )->getData();
        }

        return Response::success([
            'updated' => $updated,
        ], $this->lexiconMessageOrKey('ms3_category_products_reordered'))->getData();
    }

    /**
     * Multiple product actions (bulk operations)
     * POST /api/mgr/categories/{id}/products/multiple
     *
     * @param array $params
     * @return array Response
     */
    public function multiple(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $method = $params['method'] ?? '';
        $ids = $params['ids'] ?? [];
        $nested = $this->isNested($params);

        if (!$categoryId) {
            return $this->errorResponse('ms3_err_category_id_required', HttpStatus::BAD_REQUEST);
        }

        if (empty($method)) {
            return $this->errorResponse('ms3_err_method_required', HttpStatus::BAD_REQUEST);
        }

        $access = CategoryProductActionPermissions::evaluate(
            $method,
            fn(string $permission): bool => $this->modx->hasPermission($permission)
        );
        if (!$access['allowed']) {
            if ($access['reason'] === 'unknown_method') {
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    "[CategoryProductsController] Unknown method: {$method}"
                );
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    '[CategoryProductsController] Access denied for permission '
                    . ($access['permission'] ?? '')
                    . ' (user id ' . (int)($this->modx->user->get('id') ?? 0) . ')'
                );
            }

            return $this->errorResponse(
                $access['message'],
                $access['status'],
                ['permission' => $access['permission'] ?? '']
            );
        }

        if (empty($ids) || !is_array($ids)) {
            return $this->errorResponse('ms3_err_product_ids_required', HttpStatus::BAD_REQUEST);
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return $this->errorResponse('ms3_err_product_ids_invalid', HttpStatus::BAD_REQUEST);
        }

        $documentPolicies = CategoryProductActionPermissions::documentPoliciesForMethod($method);

        $success = 0;
        $failed = 0;
        $policyDenied = 0;
        $scope = $this->scopeService();

        foreach ($ids as $id) {
            $product = $scope->findInCategory($categoryId, $id, $nested);

            if (!$product) {
                $failed++;
                continue;
            }

            if (
                $documentPolicies !== null
                && !CategoryProductDocumentPolicy::isAllowedAll($product, $documentPolicies)
            ) {
                $this->logDocumentPolicyDenied($product, $documentPolicies);
                $policyDenied++;
                continue;
            }

            // $method is already validated by CategoryProductActionPermissions::evaluate()
            // above (unknown → 400 before this loop); default is defensive/unreachable.
            $result = match ($method) {
                'publish' => $this->applyPublish($product, true),
                'unpublish' => $this->applyPublish($product, false),
                'delete' => $this->applyDelete($product, true),
                'undelete' => $this->applyDelete($product, false),
                'show' => $this->applyHideMenu($product, false),
                'hide' => $this->applyHideMenu($product, true),
                default => false,
            };

            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }

        if ($success === 0) {
            if ($policyDenied > 0) {
                return Response::error(
                    'Save permission denied for this document',
                    HttpStatus::FORBIDDEN
                )->getData();
            }

            return $this->errorResponse('ms3_err_category_products_no_updates', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        return Response::success([
            'success' => $success,
            'failed' => $failed,
        ], $this->lexiconMessageOrKey('ms3_category_products_updated', ['count' => $success]))->getData();
    }

    /**
     * Bulk delete products
     * DELETE /api/mgr/categories/{id}/products/bulk
     *
     * @param array $params
     * @return array Response
     */
    public function bulkDelete(array $params = []): array
    {
        $params['method'] = 'delete';

        return $this->multiple($params);
    }

    /**
     * Update product data from category grid inline-edit
     * PUT /api/mgr/categories/{id}/products/{productId}/data
     *
     * @param array $params
     * @return array Response
     */
    public function updateProductData(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $productId = (int) ($params['productId'] ?? 0);
        $nested = filter_var($params['nested'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$categoryId) {
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $data = $params;
        unset($data['id'], $data['productId'], $data['nested']);

        if ($data === []) {
            return Response::error('Invalid request data', HttpStatus::BAD_REQUEST)->getData();
        }

        // Scope check + product fetch in one round-trip (CategoryProductScopePolicy).
        // Replaces the separate isProductInCategoryScope() bool-only lookup and yields
        // the product instance for the document ACL check below (#473 pattern).
        $product = $this->scopeService()->findInCategory($categoryId, $productId, $nested);

        if (!$product) {
            $this->modx->lexicon->load('minishop3:default');

            return Response::error(
                $this->modx->lexicon('ms3_err_product_not_in_category_scope'),
                HttpStatus::FORBIDDEN
            )->getData();
        }

        $savePolicies = [CategoryProductDocumentPolicy::POLICY_SAVE];
        if (!CategoryProductDocumentPolicy::isAllowedAll($product, $savePolicies)) {
            $this->logDocumentPolicyDenied($product, $savePolicies);

            return Response::error(
                'Save permission denied for this document',
                HttpStatus::FORBIDDEN
            )->getData();
        }

        /** @var \MiniShop3\Services\Product\ProductDataService|null $productDataService */
        $productDataService = $this->modx->services->get('ms3_product_data_service');
        if (!$productDataService) {
            return Response::error('Product data service is not available', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $result = $productDataService->updateProductData($productId, $data);

        if (!empty($result['ok']) && !empty($result['data'])) {
            return Response::success($result['data'])->getData();
        }

        $code = $result['code'] ?? HttpStatus::INTERNAL_SERVER_ERROR;
        $message = $result['message'] ?? 'Failed to save product data';

        return Response::error($message, $code)->getData();
    }

    /**
     * Toggle product publish status
     * POST /api/mgr/categories/{id}/products/{productId}/publish
     *
     * @param array $params
     * @return array Response
     */
    public function publish(array $params = []): array
    {
        $categoryId = (int) ($params['id'] ?? 0);
        $productId = (int) ($params['productId'] ?? 0);
        $published = isset($params['published']) ? (int) $params['published'] : null;
        $nested = $this->isNested($params);

        if (!$categoryId) {
            return $this->errorResponse('ms3_err_category_id_required', HttpStatus::BAD_REQUEST);
        }

        if (!$productId) {
            return $this->errorResponse('ms3_err_product_id_required', HttpStatus::BAD_REQUEST);
        }

        if ($denied = $this->denyWithoutPermission('msproduct_publish')) {
            return $denied;
        }

        $scope = $this->scopeService();
        $product = $scope->findInCategory($categoryId, $productId, $nested);

        if (!$product) {
            return $this->errorResponse('ms3_err_product_nf', HttpStatus::NOT_FOUND);
        }

        // If published param not provided, toggle current state
        if ($published === null) {
            $published = $product->get('published') ? 0 : 1;
        }

        $policies = CategoryProductDocumentPolicy::policiesForPublish((bool) $published);
        if ($denied = CategoryProductDocumentPolicy::denialResponseAll($product, $policies)) {
            $this->logDocumentPolicyDenied($product, $policies);

            return $denied;
        }

        if (!$this->applyPublish($product, (bool) $published)) {
            return $this->errorResponse('ms3_err_product_update_failed', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        return Response::success([
            'id' => $productId,
            'published' => $published,
        ], $this->lexiconMessageOrKey(
            $published ? 'ms3_category_product_published' : 'ms3_category_product_unpublished'
        ))->getData();
    }

    private function isNested(array $params): bool
    {
        $nested = $params['nested'] ?? false;

        return filter_var($nested, FILTER_VALIDATE_BOOLEAN)
            || $nested === 1
            || $nested === '1';
    }

    private function scopeService(): CategoryProductScopeService
    {
        $service = $this->modx->services->get('ms3_category_product_scope');

        return $service instanceof CategoryProductScopeService
            ? $service
            : new CategoryProductScopeService($this->modx);
    }

    /**
     * @return msCategory|array msCategory on success, error response array on failure
     */
    private function requireCategoryWithView(int $categoryId): object|array
    {
        if (!$categoryId) {
            return $this->errorResponse('ms3_err_category_id_required', HttpStatus::BAD_REQUEST);
        }

        $category = $this->modx->getObject(msCategory::class, $categoryId);
        if (!$category) {
            return $this->errorResponse('ms3_err_category_nf', HttpStatus::NOT_FOUND);
        }

        if ($denied = CategoryProductDocumentPolicy::denialResponse(
            $category,
            CategoryProductDocumentPolicy::categoryViewPolicy()
        )) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[CategoryProductsController] Document view denied for category '
                . $categoryId
                . ' (user id ' . (int) ($this->modx->user->get('id') ?? 0) . ')'
            );

            return $denied;
        }

        return $category;
    }

    /**
     * @param list<array<string, mixed>> $results
     * @return list<array<string, mixed>>
     */
    private function filterListResultsByDocumentView(array $results, bool $nested): array
    {
        if ($results === []) {
            return [];
        }

        $productIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $results
        )));

        if ($productIds === []) {
            return [];
        }

        /** @var array<int, msProduct> $productsById */
        $productsById = [];
        $collection = $this->modx->getCollection(msProduct::class, ['id:IN' => $productIds]);
        foreach ($collection as $product) {
            if ($product instanceof msProduct) {
                $productsById[(int) $product->get('id')] = $product;
            }
        }

        /** @var array<int, msCategory> $parentsById */
        $parentsById = [];
        if ($nested) {
            $parentIds = array_values(array_unique(array_filter(array_map(
                static fn (array $row): int => (int) ($row['parent'] ?? 0),
                $results
            ))));
            if ($parentIds !== []) {
                $parentCollection = $this->modx->getCollection(msCategory::class, ['id:IN' => $parentIds]);
                foreach ($parentCollection as $parent) {
                    if ($parent instanceof msCategory) {
                        $parentsById[(int) $parent->get('id')] = $parent;
                    }
                }
            }
        }

        $filtered = [];
        foreach ($results as $row) {
            $productId = (int) ($row['id'] ?? 0);
            $product = $productsById[$productId] ?? null;
            if (!$product instanceof msProduct) {
                continue;
            }

            if (CategoryProductDocumentPolicy::canViewInCategoryGridCached($product, $nested, $parentsById)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $gridFields
     * @return list<array<string, mixed>>
     */
    private function collectVisibleListPage(
        CategoryProductsListService $listService,
        int $categoryId,
        array $params,
        bool $nested,
        array $gridFields,
        int $start,
        int $limit,
        string $sortBy,
        string $sortDir,
    ): array {
        if ($limit <= 0) {
            return [];
        }

        $visible = [];
        $scanOffset = 0;
        $skipped = 0;
        $batchSize = max($limit * 2, 20);

        while (count($visible) < $limit) {
            $page = $listService->getPage(
                $categoryId,
                $params,
                $nested,
                $gridFields,
                $scanOffset,
                $batchSize,
                $sortBy,
                $sortDir
            );

            if ($page['results'] === []) {
                break;
            }

            $filtered = $this->filterListResultsByDocumentView($page['results'], $nested);
            foreach ($filtered as $row) {
                if ($skipped < $start) {
                    $skipped++;
                    continue;
                }

                $visible[] = $row;
                if (count($visible) >= $limit) {
                    break 2;
                }
            }

            $scanOffset += count($page['results']);
            if (count($page['results']) < $batchSize) {
                break;
            }
        }

        return $visible;
    }

    /**
     * @param array<int, array<string, mixed>> $gridFields
     */
    private function countVisibleListResults(
        CategoryProductsListService $listService,
        int $categoryId,
        array $params,
        bool $nested,
        array $gridFields,
        string $sortBy,
        string $sortDir,
    ): int {
        $visible = 0;
        $scanOffset = 0;
        $batchSize = 200;

        while (true) {
            $page = $listService->getPage(
                $categoryId,
                $params,
                $nested,
                $gridFields,
                $scanOffset,
                $batchSize,
                $sortBy,
                $sortDir
            );

            if ($page['results'] === []) {
                break;
            }

            $visible += count($this->filterListResultsByDocumentView($page['results'], $nested));
            $scanOffset += count($page['results']);

            if (count($page['results']) < $batchSize) {
                break;
            }
        }

        return $visible;
    }

    /**
     * @param object $product msProduct or smoke-test stub exposing get('id')
     * @param list<string> $policies
     */
    private function logDocumentPolicyDenied(object $product, array $policies): void
    {
        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            '[CategoryProductsController] Document policy denied ('
            . implode(',', $policies)
            . ') for product '
            . (int) $product->get('id')
            . ' (user id ' . (int) ($this->modx->user->get('id') ?? 0) . ')'
        );
    }

    private function denyWithoutPermission(string $permission): ?array
    {
        if ($this->modx->hasPermission($permission)) {
            return null;
        }

        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            '[CategoryProductsController] Access denied for permission ' . $permission
            . ' (user id ' . (int)($this->modx->user->get('id') ?? 0) . ')'
        );

        return $this->errorResponse(
            'ms3_err_access_denied_permission',
            HttpStatus::FORBIDDEN,
            ['permission' => $permission]
        );
    }

    private function applyPublish(msProduct $product, bool $published): bool
    {
        return $this->setAuditedFlag($product, 'published', 'publishedon', 'publishedby', $published);
    }

    private function applyDelete(msProduct $product, bool $deleted): bool
    {
        return $this->setAuditedFlag($product, 'deleted', 'deletedon', 'deletedby', $deleted);
    }

    private function setAuditedFlag(
        msProduct $product,
        string $flag,
        string $onField,
        string $byField,
        bool $enabled
    ): bool {
        $product->set($flag, $enabled ? 1 : 0);
        if ($enabled) {
            $product->set($onField, time());
            $product->set($byField, $this->modx->user->get('id'));
        } else {
            $product->set($onField, 0);
            $product->set($byField, 0);
        }

        return $product->save();
    }

    private function applyHideMenu(msProduct $product, bool $hidemenu): bool
    {
        $product->set('hidemenu', $hidemenu ? 1 : 0);

        return $product->save();
    }

    /**
     * Get default filters configuration
     *
     * @return array
     */
    protected function getDefaultFilters(): array
    {
        return [
            'query' => [
                'type' => 'text',
                'label' => 'search',
                'placeholder' => 'search_placeholder',
                'width' => '250px',
                'position' => 10,
            ],
            'published' => [
                'type' => 'select',
                'label' => 'published',
                'placeholder' => 'all',
                'options' => [
                    ['label' => 'Да', 'value' => 1],
                    ['label' => 'Нет', 'value' => 0],
                ],
                'width' => '120px',
                'position' => 20,
            ],
        ];
    }
}
