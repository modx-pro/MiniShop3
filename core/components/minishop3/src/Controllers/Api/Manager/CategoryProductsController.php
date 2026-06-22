<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
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

        if (!$categoryId) {
            return Response::error('Category ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (empty($items) || !is_array($items)) {
            return Response::error('Items array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $updated = 0;

        foreach ($items as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $menuindex = (int) ($item['menuindex'] ?? 0);

            if (!$productId) {
                continue;
            }

            $product = $this->modx->getObject(msProduct::class, [
                'id' => $productId,
                'parent' => $categoryId,
            ]);

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
        $method = $params['method'] ?? '';
        $ids = $params['ids'] ?? [];

        if (empty($method)) {
            return Response::error('Method is required', HttpStatus::BAD_REQUEST)->getData();
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

        foreach ($ids as $id) {
            $product = $this->modx->getObject(msProduct::class, $id);

            if (!$product) {
                $failed++;
                continue;
            }

            $result = false;

            switch ($method) {
                case 'publish':
                    $product->set('published', 1);
                    $product->set('publishedon', time());
                    $product->set('publishedby', $this->modx->user->get('id'));
                    $result = $product->save();
                    break;

                case 'unpublish':
                    $product->set('published', 0);
                    $product->set('publishedon', 0);
                    $product->set('publishedby', 0);
                    $result = $product->save();
                    break;

                case 'delete':
                    $product->set('deleted', 1);
                    $product->set('deletedon', time());
                    $product->set('deletedby', $this->modx->user->get('id'));
                    $result = $product->save();
                    break;

                case 'undelete':
                    $product->set('deleted', 0);
                    $product->set('deletedon', 0);
                    $product->set('deletedby', 0);
                    $result = $product->save();
                    break;

                case 'show':
                    $product->set('hidemenu', 0);
                    $result = $product->save();
                    break;

                case 'hide':
                    $product->set('hidemenu', 1);
                    $result = $product->save();
                    break;

                default:
                    $this->modx->log(modX::LOG_LEVEL_WARN, "[CategoryProductsController] Unknown method: {$method}");
            }

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
        $productId = (int) ($params['productId'] ?? 0);
        $published = isset($params['published']) ? (int) $params['published'] : null;

        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return Response::error('Product not found', HttpStatus::NOT_FOUND)->getData();
        }

        // If published param not provided, toggle current state
        if ($published === null) {
            $published = $product->get('published') ? 0 : 1;
        }

        $product->set('published', $published);
        if ($published) {
            $product->set('publishedon', time());
            $product->set('publishedby', $this->modx->user->get('id'));
        } else {
            $product->set('publishedon', 0);
            $product->set('publishedby', 0);
        }

        if (!$product->save()) {
            return Response::error('Failed to update product', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'id' => $productId,
            'published' => $published,
        ], $published ? 'Product published' : 'Product unpublished')->getData();
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
