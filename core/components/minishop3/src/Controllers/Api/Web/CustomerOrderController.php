<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Customer\CustomerOrderService;
use MODX\Revolution\modX;

/**
 * API controller for customer orders (Web API)
 *
 * List / get / cancel for the authorized customer only.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerOrderController
{
    use AuthorizedCustomerTrait;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:customer', 'minishop3:default');
    }

    /**
     * List current customer orders
     * GET /api/v1/customer/orders?limit=&offset=&status=
     *
     * @param array $params URL parameters
     * @return array
     */
    public function getList(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_order_err_unauthorized'), HttpStatus::UNAUTHORIZED)->getData();
        }

        /** @var CustomerOrderService $service */
        $service = $this->modx->services->get('ms3_customer_order');
        $query = CustomerOrderService::normalizeListParams($_GET, $service->getDraftStatusId());

        return Response::success($service->listForCustomer(
            (int)$customer->get('id'),
            $query['limit'],
            $query['offset'],
            $query['status_id']
        ))->getData();
    }

    /**
     * Get one order owned by the current customer
     * GET /api/v1/customer/orders/{id}
     *
     * @param array $params URL parameters (id = order ID)
     * @return array
     */
    public function get(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_order_err_unauthorized'), HttpStatus::UNAUTHORIZED)->getData();
        }

        $orderId = (int)($params['id'] ?? 0);

        if ($orderId < 1) {
            return Response::error($this->modx->lexicon('ms3_customer_order_err_no_id'), HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var CustomerOrderService $service */
        $service = $this->modx->services->get('ms3_customer_order');
        $detail = $service->getForCustomer((int)$customer->get('id'), $orderId);

        if ($detail === null) {
            return Response::error($this->modx->lexicon('ms3_customer_order_err_not_found'), HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success($detail)->getData();
    }

    /**
     * Cancel order (customer-initiated)
     * POST /api/v1/customer/orders/{id}/cancel
     *
     * @param array $params URL parameters (id = order ID)
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function cancel(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_unauthorized'), HttpStatus::UNAUTHORIZED)->getData();
        }

        $orderId = (int)($params['id'] ?? 0);

        if ($orderId < 1) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_no_order'), HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var CustomerOrderService $service */
        $service = $this->modx->services->get('ms3_customer_order');
        $result = $service->cancelForCustomer((int)$customer->get('id'), $orderId);

        if (!$result['ok']) {
            return match ($result['error']) {
                'not_found' => Response::error(
                    $this->modx->lexicon('ms3_customer_order_cancel_err_not_found'),
                    HttpStatus::NOT_FOUND
                )->getData(),
                'status' => Response::error(
                    $this->modx->lexicon('ms3_customer_order_cancel_err_status'),
                    HttpStatus::BAD_REQUEST
                )->getData(),
                default => Response::error(
                    $result['message'] ?? $this->modx->lexicon('ms3_customer_order_cancel_err_failed'),
                    HttpStatus::BAD_REQUEST
                )->getData(),
            };
        }

        return Response::success(
            ['order_id' => $result['order_id'], 'status_id' => $result['status_id']],
            $this->modx->lexicon('ms3_customer_order_cancelled')
        )->getData();
    }
}
