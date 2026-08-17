<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Product\ProductCatalogFilterException;
use MiniShop3\Services\Product\ProductCatalogService;
use MiniShop3\Services\Product\ProductFacetService;
use MODX\Revolution\modX;

/**
 * Public catalog endpoints for Web API.
 *
 * No customer token required — same public surface as storefront SSR.
 */
class ProductController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * GET /api/v1/product/get/{id}
     *
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): Response
    {
        $productId = (int) ($params['id'] ?? 0);

        if ($productId <= 0) {
            return Response::error(
                $this->modx->lexicon('ms3_err_product_id_ns'),
                HttpStatus::BAD_REQUEST
            );
        }

        $product = $this->catalog()->getById($productId, $params);

        if ($product === null) {
            return Response::error(
                $this->modx->lexicon('ms3_err_product_nf'),
                HttpStatus::NOT_FOUND
            );
        }

        return Response::success($product);
    }

    /**
     * GET /api/v1/product/list
     *
     * Query: parent|category, parents, nested, price_min, price_max, in_stock, stock_min,
     *        vendor_id, new, popular, favorite, options (JSON),
     *        limit, offset|page, sort, dir, query, context, include_options, include_content
     *
     * @param array<string, mixed> $params Route + query params (Router merges $_GET)
     */
    public function getList(array $params = []): Response
    {
        try {
            $result = $this->catalog()->getList($params);
        } catch (ProductCatalogFilterException $e) {
            return Response::error(
                $this->modx->lexicon($e->getLexiconKey()),
                HttpStatus::BAD_REQUEST
            );
        }

        return Response::success($result);
    }

    /**
     * GET /api/v1/product/filters
     *
     * Soft facets for headless PLP (#565). Same filter query params as product/list,
     * plus keys, include_price (default 1), include_vendors (default 0).
     *
     * @param array<string, mixed> $params
     */
    public function filters(array $params = []): Response
    {
        try {
            $result = $this->facets()->getFilters($params);
        } catch (ProductCatalogFilterException $e) {
            return Response::error(
                $this->modx->lexicon($e->getLexiconKey()),
                HttpStatus::BAD_REQUEST
            );
        }

        return Response::success($result);
    }

    private function catalog(): ProductCatalogService
    {
        /** @var ProductCatalogService $service */
        $service = $this->modx->services->get('ms3_product_catalog');

        return $service;
    }

    private function facets(): ProductFacetService
    {
        /** @var ProductFacetService $service */
        $service = $this->modx->services->get('ms3_product_facets');

        return $service;
    }
}
