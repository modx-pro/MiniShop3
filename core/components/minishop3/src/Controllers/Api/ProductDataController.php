<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Services\Product\ProductCategoryTreeService;

/**
 * API controller for working with product data (msProductData)
 */
class ProductDataController extends BaseApiController
{
    /**
     * GET /api/mgr/product-data/{id}
     * Get product data
     *
     * @param array $params
     * @return Response
     */
    public function get(array $params): Response
    {
        $productId = (int)($params['id'] ?? 0);

        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ProductDataService */
            $productDataService = $this->modx->services->get('ms3_product_data_service');

            $data = $productDataService->getProductData($productId);

            if (!$data) {
                return Response::error('Product data not found', HttpStatus::NOT_FOUND);
            }

            return Response::success($data);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ProductDataController] ' . $e->getMessage());
            return Response::error('Failed to load product data: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PUT /api/mgr/product-data/{id}
     * Update product data
     *
     * @param array $params
     * @return Response
     */
    public function update(array $params): Response
    {
        $productId = (int)($params['id'] ?? 0);

        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST);
        }

        $requestData = $this->getRequestData();

        if (!$requestData) {
            return Response::error('Invalid request data', HttpStatus::BAD_REQUEST);
        }

        $categoryId = (int) ($requestData['category_id'] ?? 0);
        $nested = filter_var($requestData['nested'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $data = $requestData;
        unset($data['category_id'], $data['nested']);

        if ($categoryId > 0) {
            if (!$this->categoryProductScopeService()->findInCategory($categoryId, $productId, $nested)) {
                $this->modx->lexicon->load('minishop3:default');

                return Response::error(
                    $this->modx->lexicon('ms3_err_product_not_in_category_scope'),
                    HttpStatus::FORBIDDEN
                );
            }
        }

        if ($data === []) {
            return Response::error('Invalid request data', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\Product\ProductDataService $productDataService */
            $productDataService = $this->modx->services->get('ms3_product_data_service');

            $result = $productDataService->updateProductData($productId, $data);

            if (!empty($result['ok']) && !empty($result['data'])) {
                return Response::success($result['data']);
            }

            $code = $result['code'] ?? 500;
            $message = $result['message'] ?? 'Failed to save product data';
            return Response::error($message, $code);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ProductDataController] ' . $e->getMessage());
            return Response::error('Failed to save product data: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/product-data/{id}/categories/tree
     *
     * Lazy msCategory tree for the product Categories tab (Vue).
     *
     * @param array $params id (product), parent (default 0), parent_category, categories (JSON precheck)
     */
    public function getCategoriesTree(array $params): Response
    {
        $productId = (int)($params['id'] ?? 0);
        if (!$productId) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST);
        }

        $parent = (int)($params['parent'] ?? 0);
        $parentCategoryId = (int)($params['parent_category'] ?? 0);
        $clientSentCategories = array_key_exists('categories', $params);
        $preChecked = $this->decodeIntArray($params['categories'] ?? null);

        try {
            $service = new ProductCategoryTreeService($this->modx);
            $nodes = $service->getTreeNodes(
                $parent,
                $productId,
                $parentCategoryId,
                $preChecked,
                $clientSentCategories
            );

            return Response::success(['results' => $nodes, 'total' => count($nodes)]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ProductDataController] ' . $e->getMessage());
            return Response::error('Failed to load category tree: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    private function categoryProductScopeService(): CategoryProductScopeService
    {
        $service = $this->modx->services->get('ms3_category_product_scope');

        return $service instanceof CategoryProductScopeService
            ? $service
            : new CategoryProductScopeService($this->modx);
    }
}
