<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msCategory;
use MiniShop3\Router\Response;
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
        $categoryId = (int)($params['id'] ?? 0);

        if (!$categoryId) {
            return Response::error('Category ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $category = $this->modx->getObject(msCategory::class, $categoryId);
        if (!$category) {
            return Response::error('Category not found', Response::HTTP_NOT_FOUND)->getData();
        }

        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $sortBy = $params['sort'] ?? 'menuindex';
        $sortDir = strtoupper($params['dir'] ?? 'ASC');
        $query = trim($params['query'] ?? '');
        $nested = (bool)($params['nested'] ?? false);

        // Validate sort direction
        if (!in_array($sortDir, ['ASC', 'DESC'])) {
            $sortDir = 'ASC';
        }

        // Build query
        $c = $this->modx->newQuery(msProduct::class);
        $c->innerJoin(msProductData::class, 'Data', 'msProduct.id = Data.id');

        // class_key filter (getIterator doesn't call addDerivativeCriteria)
        $c->where(['msProduct.class_key' => msProduct::class]);

        // Parent filter
        if ($nested) {
            // Get all child category IDs
            $categoryIds = $this->getChildCategories($categoryId);
            $categoryIds[] = $categoryId;
            $c->where(['msProduct.parent:IN' => $categoryIds]);
        } else {
            $c->where(['msProduct.parent' => $categoryId]);
        }

        // Search filter
        if (!empty($query)) {
            $c->where([
                'msProduct.pagetitle:LIKE' => "%{$query}%",
                'OR:Data.article:LIKE' => "%{$query}%",
            ]);
        }

        // Boolean filters for msProduct fields
        $productBooleanFields = ['published', 'deleted', 'hidemenu', 'isfolder'];
        foreach ($productBooleanFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["msProduct.{$field}" => (int)$params[$field]]);
            }
        }

        // Boolean filters for msProductData fields
        $dataBooleanFields = ['new', 'popular', 'favorite'];
        foreach ($dataBooleanFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["Data.{$field}" => (int)$params[$field]]);
            }
        }

        // Text filters for msProduct fields (LIKE search)
        $productTextFields = ['pagetitle', 'longtitle', 'alias', 'description', 'introtext', 'content'];
        foreach ($productTextFields as $field) {
            if (!empty($params[$field])) {
                $c->where(["msProduct.{$field}:LIKE" => "%{$params[$field]}%"]);
            }
        }

        // Text filters for msProductData fields (LIKE search)
        $dataTextFields = ['article', 'made_in'];
        foreach ($dataTextFields as $field) {
            if (!empty($params[$field])) {
                $c->where(["Data.{$field}:LIKE" => "%{$params[$field]}%"]);
            }
        }

        // Numeric filters for msProductData fields (exact match)
        $dataNumericFields = ['price', 'old_price', 'weight', 'vendor_id'];
        foreach ($dataNumericFields as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $c->where(["Data.{$field}" => $params[$field]]);
            }
        }

        // Default: hide deleted if not explicitly filtered
        if (!isset($params['deleted']) || $params['deleted'] === '') {
            $c->where(['msProduct.deleted' => 0]);
        }

        // Get total count
        $total = $this->modx->getCount(msProduct::class, $c);

        // Apply sorting and pagination
        $c->sortby($sortBy, $sortDir);
        $c->limit($limit, $start);

        // Select fields
        $c->select([
            'msProduct.*',
            'Data.article',
            'Data.price',
            'Data.old_price',
            'Data.weight',
            'Data.image',
            'Data.thumb',
            'Data.vendor_id',
            'Data.made_in',
            'Data.new',
            'Data.popular',
            'Data.favorite',
        ]);

        $products = $this->modx->getIterator(msProduct::class, $c);

        $results = [];
        foreach ($products as $product) {
            $results[] = $this->formatProduct($product, $nested);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
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
        $categoryId = (int)($params['id'] ?? 0);
        $items = $params['items'] ?? [];

        if (!$categoryId) {
            return Response::error('Category ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        if (empty($items) || !is_array($items)) {
            return Response::error('Items array is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $updated = 0;

        foreach ($items as $item) {
            $productId = (int)($item['id'] ?? 0);
            $menuindex = (int)($item['menuindex'] ?? 0);

            if (!$productId) {
                continue;
            }

            $product = $this->modx->getObject(msProduct::class, [
                'id' => $productId,
                'parent' => $categoryId
            ]);

            if ($product) {
                $product->set('menuindex', $menuindex);
                if ($product->save()) {
                    $updated++;
                }
            }
        }

        return Response::success([
            'updated' => $updated
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
            return Response::error('Method is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Product IDs array is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid product IDs provided', Response::HTTP_BAD_REQUEST)->getData();
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
            return Response::error('No products were updated', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'success' => $success,
            'failed' => $failed
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
        $productId = (int)($params['productId'] ?? 0);
        $published = isset($params['published']) ? (int)$params['published'] : null;

        if (!$productId) {
            return Response::error('Product ID is required', Response::HTTP_BAD_REQUEST)->getData();
        }

        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return Response::error('Product not found', Response::HTTP_NOT_FOUND)->getData();
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
            return Response::error('Failed to update product', Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'id' => $productId,
            'published' => $published
        ], $published ? 'Product published' : 'Product unpublished')->getData();
    }

    /**
     * Format product for API response
     *
     * @param msProduct $product
     * @param bool $nested
     * @return array
     */
    protected function formatProduct(msProduct $product, bool $nested = false): array
    {
        $data = [
            'id' => $product->get('id'),
            'pagetitle' => $product->get('pagetitle'),
            'longtitle' => $product->get('longtitle'),
            'alias' => $product->get('alias'),
            'parent' => $product->get('parent'),
            'menuindex' => $product->get('menuindex'),
            'published' => (bool)$product->get('published'),
            'deleted' => (bool)$product->get('deleted'),
            'hidemenu' => (bool)$product->get('hidemenu'),
            'createdon' => $product->get('createdon'),
            'editedon' => $product->get('editedon'),
            // Product data
            'article' => $product->get('article'),
            'price' => (float)$product->get('price'),
            'old_price' => (float)$product->get('old_price'),
            'weight' => (float)$product->get('weight'),
            'image' => $product->get('image'),
            'thumb' => $product->get('thumb'),
            'vendor_id' => (int)$product->get('vendor_id'),
            'made_in' => $product->get('made_in'),
            'new' => (bool)$product->get('new'),
            'popular' => (bool)$product->get('popular'),
            'favorite' => (bool)$product->get('favorite'),
            // Preview URL
            'preview_url' => $this->modx->makeUrl($product->get('id'), '', '', 'full'),
        ];

        // Add category name for nested products
        if ($nested && $product->get('parent') != 0) {
            $parent = $this->modx->getObject(msCategory::class, $product->get('parent'));
            if ($parent) {
                $data['category_name'] = $parent->get('pagetitle');
            }
        }

        return $data;
    }

    /**
     * Get all child category IDs recursively
     *
     * @param int $parentId
     * @return array
     */
    protected function getChildCategories(int $parentId): array
    {
        $ids = [];

        $children = $this->modx->getIterator(msCategory::class, [
            'parent' => $parentId,
            'deleted' => 0,
            'class_key' => msCategory::class,
        ]);

        foreach ($children as $child) {
            $childId = $child->get('id');
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getChildCategories($childId));
        }

        return $ids;
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
