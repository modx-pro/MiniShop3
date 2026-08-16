<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Category\CategoryCatalogService;
use MODX\Revolution\modX;

/**
 * Public category catalog endpoints for Web API.
 *
 * No customer token required — same public surface as product catalog.
 */
class CategoryController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * GET /api/v1/category/get/{id}
     *
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): Response
    {
        $categoryId = (int) ($params['id'] ?? 0);

        if ($categoryId <= 0) {
            return Response::error(
                $this->modx->lexicon('ms3_err_category_id_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $category = $this->catalog()->getById($categoryId, $params);

        if ($category === null) {
            return Response::error(
                $this->modx->lexicon('ms3_err_category_nf'),
                HttpStatus::NOT_FOUND
            );
        }

        return Response::success($category);
    }

    /**
     * GET /api/v1/category/list
     *
     * Query: parent, limit, offset|page, sort, dir, context,
     *        include_hidden, include_content
     *
     * @param array<string, mixed> $params
     */
    public function getList(array $params = []): Response
    {
        return Response::success($this->catalog()->getList($params));
    }

    /**
     * GET /api/v1/category/tree
     *
     * Query: parent, depth, context, include_hidden, sort, dir
     *
     * @param array<string, mixed> $params
     */
    public function getTree(array $params = []): Response
    {
        return Response::success($this->catalog()->getTree($params));
    }

    private function catalog(): CategoryCatalogService
    {
        /** @var CategoryCatalogService $service */
        $service = $this->modx->services->get('ms3_category_catalog');

        return $service;
    }
}
