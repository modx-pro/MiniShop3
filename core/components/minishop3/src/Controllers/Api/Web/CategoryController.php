<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Catalog\CatalogContextException;
use MiniShop3\Services\Catalog\CatalogResolve;
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

        try {
            $category = $this->catalog()->getById($categoryId, $params);
        } catch (CatalogContextException $e) {
            return $this->catalogContextBadRequest($e);
        }

        if ($category === null) {
            return Response::error(
                $this->modx->lexicon('ms3_err_category_nf'),
                HttpStatus::NOT_FOUND
            );
        }

        return Response::success($category);
    }

    /**
     * GET /api/v1/category/get?alias=…|uri=…&context=…
     *
     * @param array<string, mixed> $params
     */
    public function resolve(array $params = []): Response
    {
        try {
            $parsed = CatalogResolve::parseLookup(
                $params,
                (string) ($this->modx->context->key ?? 'web'),
            );
        } catch (CatalogContextException $e) {
            return $this->catalogContextBadRequest($e);
        }

        if (!$parsed['ok']) {
            return Response::error(
                $this->modx->lexicon(CatalogResolve::lookupErrorLexiconKey($parsed['error'])),
                HttpStatus::BAD_REQUEST
            );
        }

        $category = $this->catalog()->resolveByLookup(
            $params,
            $parsed['field'],
            $parsed['value'],
            $parsed['context'],
        );

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
        try {
            $result = $this->catalog()->getList($params);
        } catch (CatalogContextException $e) {
            return $this->catalogContextBadRequest($e);
        }

        return Response::success($result);
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
        try {
            $result = $this->catalog()->getTree($params);
        } catch (CatalogContextException $e) {
            return $this->catalogContextBadRequest($e);
        }

        return Response::success($result);
    }

    private function catalogContextBadRequest(CatalogContextException $e): Response
    {
        return Response::error(
            $this->modx->lexicon($e->getLexiconKey()),
            HttpStatus::BAD_REQUEST
        );
    }

    private function catalog(): CategoryCatalogService
    {
        /** @var CategoryCatalogService $service */
        $service = $this->modx->services->get('ms3_category_catalog');

        return $service;
    }
}
