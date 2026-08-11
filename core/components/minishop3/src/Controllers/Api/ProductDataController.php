<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Services\Product\ProductLinkService;

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
            /** @var \MiniShop3\Services\Product\ProductCategoryTreeService $service */
            $service = $this->modx->services->get('ms3_product_category_tree');
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

    /**
     * GET /api/mgr/product-data/{id}/links
     */
    public function getLinks(array $params): Response
    {
        $productId = $this->requireProductId($params);
        if ($productId instanceof Response) {
            return $productId;
        }

        $payload = $this->productLinkService()->listForProduct(
            $productId,
            trim((string) ($params['query'] ?? '')),
            (int) ($params['start'] ?? 0),
            (int) ($params['limit'] ?? 20)
        );

        return Response::success($payload);
    }

    /**
     * POST /api/mgr/product-data/{id}/links
     */
    public function createLink(array $params): Response
    {
        $productId = $this->requireProductId($params);
        if ($productId instanceof Response) {
            return $productId;
        }

        $data = $this->getRequestData();
        $result = $this->productLinkService()->create(
            $productId,
            (int) ($data['slave'] ?? 0),
            (int) ($data['link'] ?? 0)
        );

        return $this->linkActionResponse($result);
    }

    /**
     * DELETE /api/mgr/product-data/{id}/links
     *
     * Body: { link, master, slave }. Link must involve path product id.
     */
    public function removeLinks(array $params): Response
    {
        $productId = $this->requireProductId($params);
        if ($productId instanceof Response) {
            return $productId;
        }

        $data = $this->getRequestData();
        if (isset($data['ids'])) {
            $this->modx->lexicon->load('minishop3:default');

            return Response::error(
                $this->modx->lexicon('ms3_err_link_batch_not_supported'),
                HttpStatus::BAD_REQUEST
            );
        }

        $master = (int) ($data['master'] ?? $productId);
        $slave = (int) ($data['slave'] ?? 0);

        if (!ProductLinkService::belongsToProduct($productId, $master, $slave)) {
            $this->modx->lexicon->load('minishop3:default');

            return Response::error(
                $this->modx->lexicon('ms3_err_link_not_in_product_scope'),
                HttpStatus::FORBIDDEN
            );
        }

        return $this->linkActionResponse($this->productLinkService()->remove(
            (int) ($data['link'] ?? 0),
            $master,
            $slave
        ));
    }

    private function requireProductId(array $params): int|Response
    {
        $productId = (int) ($params['id'] ?? 0);
        if ($productId <= 0) {
            return Response::error('Product ID is required', HttpStatus::BAD_REQUEST);
        }

        return $productId;
    }

    /**
     * @param array{ok?: bool, message?: string} $result
     */
    private function linkActionResponse(array $result): Response
    {
        if (empty($result['ok'])) {
            return Response::error((string) ($result['message'] ?? 'Failed'), HttpStatus::BAD_REQUEST);
        }

        return Response::success([]);
    }

    private function categoryProductScopeService(): CategoryProductScopeService
    {
        $service = $this->modx->services->get('ms3_category_product_scope');

        return $service instanceof CategoryProductScopeService
            ? $service
            : new CategoryProductScopeService($this->modx);
    }

    private function productLinkService(): ProductLinkService
    {
        $service = $this->modx->services->get('ms3_product_link_service');

        return $service instanceof ProductLinkService
            ? $service
            : new ProductLinkService($this->modx);
    }
}
