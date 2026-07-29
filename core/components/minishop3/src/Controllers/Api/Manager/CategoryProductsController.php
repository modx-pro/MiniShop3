<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Category\CategoryProductActionPermissions;
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

        if (!$categoryId) {
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $category = $this->modx->getObject(msCategory::class, $categoryId);
        if (!$category) {
            return Response::error('Category not found', HttpStatus::NOT_FOUND)->getData();
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
            return Response::error('Category products list service is not available', 500)->getData();
        }

        $page = $listService->getPage(
            $categoryId,
            $params,
            $nested,
            $gridFields,
            $start,
            $limit,
            (string) $sortBy,
            $sortDir
        );

        return Response::success([
            'results' => $page['results'],
            'total' => $page['total'],
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

        if (!$categoryId) {
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (empty($items) || !is_array($items)) {
            return Response::error('Items array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $updated = 0;
        $scopeService = $this->scopeService();

        foreach ($items as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $menuindex = (int) ($item['menuindex'] ?? 0);

            if (!$productId || !$scopeService->canReorderInCategory($productId, $categoryId)) {
                continue;
            }

            $product = $this->modx->getObject(msProduct::class, $productId);

            if ($product) {
                $product->set('menuindex', $menuindex);
                if ($product->save()) {
                    $updated++;
                }
            }
        }

        return Response::success([
            'updated' => $updated,
        ], 'Products reordered successfully')->getData();
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
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (empty($method)) {
            return Response::error('Method is required', HttpStatus::BAD_REQUEST)->getData();
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

            return Response::error($access['message'], $access['status'])->getData();
        }

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Product IDs array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid product IDs provided', HttpStatus::BAD_REQUEST)->getData();
        }

        $success = 0;
        $failed = 0;
        $scope = $this->scopeService();

        foreach ($ids as $id) {
            $product = $scope->findInCategory($categoryId, $id, $nested);

            if (!$product) {
                $failed++;
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
            return Response::error('No products were updated', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'success' => $success,
            'failed' => $failed,
        ], "{$success} products updated")->getData();
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
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if ($denied = $this->denyWithoutPermission('msproduct_publish')) {
            return $denied;
        }

        $scope = $this->scopeService();
        $product = $scope->findInCategory($categoryId, $productId, $nested);

        if (!$product) {
            return Response::error('Product not found', HttpStatus::NOT_FOUND)->getData();
        }

        // If published param not provided, toggle current state
        if ($published === null) {
            $published = $product->get('published') ? 0 : 1;
        }

        if (!$this->applyPublish($product, (bool) $published)) {
            return Response::error('Failed to update product', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'id' => $productId,
            'published' => $published,
        ], $published ? 'Product published' : 'Product unpublished')->getData();
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

        return Response::error(
            "Access denied. Required permission: {$permission}",
            HttpStatus::FORBIDDEN
        )->getData();
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
