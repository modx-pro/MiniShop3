<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Delivery\DeliveryCatalogService;
use MODX\Revolution\modX;

/**
 * Public delivery discovery for Web API (#568).
 */
class DeliveryController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * GET /api/v1/delivery/get/{id}
     *
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::error(
                $this->modx->lexicon('ms3_err_delivery_id_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $item = $this->catalog()->getById($id, $params);
        if ($item === null) {
            return Response::error(
                $this->modx->lexicon('ms3_err_delivery_nf'),
                HttpStatus::NOT_FOUND
            );
        }

        return Response::success($item);
    }

    /**
     * GET /api/v1/delivery/list
     *
     * Query: include_payments (default true), include_required_fields (default false)
     *
     * @param array<string, mixed> $params
     */
    public function getList(array $params = []): Response
    {
        return Response::success($this->catalog()->getList($params));
    }

    private function catalog(): DeliveryCatalogService
    {
        /** @var DeliveryCatalogService $service */
        $service = $this->modx->services->get('ms3_delivery_catalog');

        return $service;
    }
}
