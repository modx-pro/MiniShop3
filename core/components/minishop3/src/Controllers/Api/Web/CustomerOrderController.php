<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msOrder;
use MiniShop3\Router\Response;
use MiniShop3\Services\Order\OrderStatusService;
use MODX\Revolution\modX;

/**
 * API controller for customer orders (Web API)
 *
 * Handles customer-initiated order actions, e.g. cancel order.
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
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_unauthorized'), 401)->getData();
        }

        $orderId = (int)($params['id'] ?? 0);

        if (!$orderId) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_no_order'), Response::HTTP_BAD_REQUEST)->getData();
        }

        /** @var msOrder|null $order */
        $order = $this->modx->getObject(msOrder::class, [
            'id' => $orderId,
            'customer_id' => $customer->get('id'),
        ]);

        if (!$order) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_not_found'), Response::HTTP_NOT_FOUND)->getData();
        }

        /** @var OrderStatusService $orderStatusService */
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $allowedStatusIds = $orderStatusService->getAllowedCancelStatusIds();
        $currentStatusId = (int) $order->get('status_id');

        if (!in_array($currentStatusId, $allowedStatusIds, true)) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_status'), Response::HTTP_BAD_REQUEST)->getData();
        }

        $cancelledStatusId = (int) $this->modx->getOption('ms3_status_canceled', null, 5);

        $result = $orderStatusService->change($orderId, $cancelledStatusId);

        if ($result !== true) {
            $message = is_string($result) ? $result : $this->modx->lexicon('ms3_customer_order_cancel_err_failed');
            return Response::error($message, Response::HTTP_BAD_REQUEST)->getData();
        }

        return Response::success(
            ['order_id' => $orderId, 'status_id' => $cancelledStatusId],
            $this->modx->lexicon('ms3_customer_order_cancelled')
        )->getData();
    }
}
