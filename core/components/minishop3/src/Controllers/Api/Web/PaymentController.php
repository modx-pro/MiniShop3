<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Payment\PaymentCatalogService;
use MODX\Revolution\modX;

/**
 * Public payment discovery for Web API (#569).
 */
class PaymentController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * GET /api/v1/payment/get/{id}
     *
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::error(
                $this->modx->lexicon('ms3_err_payment_id_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $item = $this->catalog()->getById($id, $params);
        if ($item === null) {
            return Response::error(
                $this->modx->lexicon('ms3_err_payment_nf'),
                HttpStatus::NOT_FOUND
            );
        }

        return Response::success($item);
    }

    /**
     * GET /api/v1/payment/list
     *
     * Query: delivery_id (optional), include_delivery_ids (default false)
     *
     * @param array<string, mixed> $params
     */
    public function getList(array $params = []): Response
    {
        return Response::success($this->catalog()->getList($params));
    }

    private function catalog(): PaymentCatalogService
    {
        /** @var PaymentCatalogService $service */
        $service = $this->modx->services->get('ms3_payment_catalog');

        return $service;
    }
}
