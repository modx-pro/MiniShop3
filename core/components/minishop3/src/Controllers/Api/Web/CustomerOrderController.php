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
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_no_order'), 400)->getData();
        }

        /** @var msOrder|null $order */
        $order = $this->modx->getObject(msOrder::class, [
            'id' => $orderId,
            'customer_id' => $customer->get('id'),
        ]);

        if (!$order) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_not_found'), 404)->getData();
        }

        $allowedStatusIds = $this->getAllowedCancelStatusIds();
        $currentStatusId = (int) $order->get('status_id');

        if (!in_array($currentStatusId, $allowedStatusIds, true)) {
            return Response::error($this->modx->lexicon('ms3_customer_order_cancel_err_status'), 400)->getData();
        }

        $cancelledStatusId = (int) $this->modx->getOption('ms3_status_canceled', null, 5) ?: 5;

        /** @var OrderStatusService $orderStatusService */
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $result = $orderStatusService->change($orderId, $cancelledStatusId);

        if ($result !== true) {
            $message = is_string($result) ? $result : $this->modx->lexicon('ms3_customer_order_cancel_err_failed');
            return Response::error($message, 400)->getData();
        }

        return Response::success(
            ['order_id' => $orderId, 'status_id' => $cancelledStatusId],
            $this->modx->lexicon('ms3_customer_order_cancelled')
        )->getData();
    }

    /**
     * Get list of status IDs from which customer is allowed to cancel
     *
     * @return int[]
     */
    protected function getAllowedCancelStatusIds(): array
    {
        $setting = $this->modx->getOption('ms3_customer_cancel_allowed_statuses', null, '');

        if ($setting !== '') {
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $setting))));
            return array_values(array_filter($ids));
        }

        $newId = (int) $this->modx->getOption('ms3_status_new', null, 2);
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);

        return array_filter([$newId, $paidId]);
    }

    /**
     * Get authorized customer (session or API token)
     *
     * @return msCustomer|null
     */
    protected function getAuthorizedCustomer(): ?msCustomer
    {
        $ms3 = $this->modx->services->get('ms3');
        $ms3->initialize();

        $tokenString = $_REQUEST['ms3_token'] ?? $_SESSION['ms3']['customer_token'] ?? '';

        if (!empty($tokenString)) {
            $tokenObj = $this->modx->getObject(\MiniShop3\Model\msCustomerToken::class, [
                'token' => $tokenString,
                'type' => \MiniShop3\Model\msCustomerToken::TYPE_API
            ]);

            if ($tokenObj && !$tokenObj->isExpired()) {
                $customer = $this->modx->getObject(msCustomer::class, $tokenObj->get('customer_id'));
                if ($customer) {
                    return $customer;
                }
            }
        }

        if (!empty($_SESSION['ms3']['customer_id'])) {
            $customer = $this->modx->getObject(msCustomer::class, (int)$_SESSION['ms3']['customer_id']);
            if ($customer) {
                return $customer;
            }
        }

        return null;
    }
}
