<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Order Cost Calculator
 *
 * Web/checkout adapter: cart status, delivery/payment providers, MODX cost events.
 * Core formulas live in {@see OrderCostEngine}; manager path uses {@see ManagerOrderCostRecalculator}.
 *
 * Events (web-only): msOnBeforeGetCartCost, msOnGetCartCost, msOnBeforeGetDeliveryCost,
 * msOnGetDeliveryCost, msOnBeforeGetPaymentCost, msOnGetPaymentCost,
 * msOnBeforeGetOrderCost, msOnGetOrderCost.
 */
class OrderCostCalculator
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Get cart cost from draft order
     *
     * @param msOrder|null $draft Draft order (null = no cart)
     * @param string $token Session token for cart
     * @param string $ctx Context
     * @return array Response with 'cost' key
     */
    public function getCartCost(?msOrder $draft, string $token, string $ctx = 'web'): array
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetCartCost', [
            'calculator' => $this,
            'cart' => $this->ms3->getCart(),
            'draft' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $this->ms3->getCart()->initialize($ctx, $token);
        $response = $this->ms3->getCart()->status();

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $status = $response['data'];
        $cost = $status['total_cost'];

        $response = $this->ms3->utils->invokeEvent('msOnGetCartCost', [
            'calculator' => $this,
            'cart' => $this->ms3->getCart(),
            'draft' => $draft,
            'cost' => $cost,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $cost = $response['data']['cost'];

        return $this->success('ms3_order_getcost_success', ['cost' => $cost]);
    }

    /**
     * Get delivery cost
     *
     * @param msOrder|null $draft Draft order
     * @param array $orderData Order data array (with delivery_id)
     * @param string $token Session token
     * @param string $ctx Context
     * @return array Response with 'cost' key
     */
    public function getDeliveryCost(?msOrder $draft, array $orderData, string $token, string $ctx = 'web'): array
    {
        // No draft = no order = zero delivery cost
        if (!$draft) {
            return $this->success('ms3_order_getcost_success', ['cost' => 0]);
        }

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetDeliveryCost', [
            'calculator' => $this,
            'cartController' => $this->ms3->getCart(),
            'draft' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $deliveryCost = 0;

        if (empty($orderData['delivery_id'])) {
            return $this->success('ms3_order_getcost_success', ['cost' => $deliveryCost]);
        }

        /** @var msDelivery $msDelivery */
        $msDelivery = $this->modx->getObject(
            msDelivery::class,
            ['id' => $orderData['delivery_id']]
        );

        if (!$msDelivery) {
            return $this->success('ms3_order_getcost_success', ['cost' => $deliveryCost]);
        }

        // Load delivery controller (provider class)
        if (!$msDelivery->loadController()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[OrderCostCalculator] Failed to load delivery controller for delivery ID={$msDelivery->get('id')}"
            );
            return $this->success('ms3_order_getcost_success', ['cost' => $deliveryCost]);
        }

        // Get cart cost for delivery calculation
        $cartCostResponse = $this->getCartCost($draft, $token, $ctx);
        $cartCost = $cartCostResponse['success'] ? $cartCostResponse['data']['cost'] : 0;

        $deliveryCost = $msDelivery->getCost($draft, $cartCost);

        $response = $this->ms3->utils->invokeEvent('msOnGetDeliveryCost', [
            'calculator' => $this,
            'cartController' => $this->ms3->getCart(),
            'draft' => $draft,
            'cost' => $deliveryCost,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $deliveryCost = $response['data']['cost'];

        return $this->success('ms3_order_getcost_success', ['cost' => $deliveryCost]);
    }

    /**
     * Get payment cost (extra cost for payment method)
     *
     * @param msOrder|null $draft Draft order
     * @param array $orderData Order data array (with payment_id)
     * @param string $token Session token
     * @param string $ctx Context
     * @param float|null $knownCartCost Skip cart pipeline when already computed (e.g. from {@see getTotalCost})
     * @param float|null $knownDeliveryCost Skip delivery pipeline when already computed
     * @return array Response with 'cost' key
     */
    public function getPaymentCost(
        ?msOrder $draft,
        array $orderData,
        string $token,
        string $ctx = 'web',
        ?float $knownCartCost = null,
        ?float $knownDeliveryCost = null
    ): array {
        // No draft = no order = zero payment cost
        if (!$draft) {
            return $this->success('ms3_order_getcost_success', ['cost' => 0]);
        }

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetPaymentCost', [
            'calculator' => $this,
            'cartController' => $this->ms3->getCart(),
            'draft' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $paymentCost = 0;

        if (empty($orderData['payment_id'])) {
            return $this->success('ms3_order_getcost_success', ['cost' => $paymentCost]);
        }

        /** @var msPayment $msPayment */
        $msPayment = $this->modx->getObject(
            msPayment::class,
            ['id' => $orderData['payment_id']]
        );

        if (!$msPayment) {
            return $this->success('ms3_order_getcost_success', ['cost' => $paymentCost]);
        }

        // Payment commission base: cart-only (MS2 parity, #460)
        if ($knownCartCost === null) {
            $cartCostResponse = $this->getCartCost($draft, $token, $ctx);
            $knownCartCost = $cartCostResponse['success'] ? (float) $cartCostResponse['data']['cost'] : 0.0;
        }

        $paymentBase = OrderCostEngine::paymentCommissionBase($knownCartCost);

        // Payment getCost returns total with payment fee, so subtract commission base
        $costWithPayment = $msPayment->getCost($draft, $paymentBase);
        $paymentCost = $costWithPayment - $paymentBase;

        $response = $this->ms3->utils->invokeEvent('msOnGetPaymentCost', [
            'calculator' => $this,
            'cartController' => $this->ms3->getCart(),
            'draft' => $draft,
            'cost' => $paymentCost,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $paymentCost = $response['data']['cost'];

        return $this->success('', ['cost' => $paymentCost]);
    }

    /**
     * Get total cost (cart + delivery + payment)
     *
     * @param msOrder|null $draft Draft order
     * @param array $orderData Order data array
     * @param string $token Session token
     * @param string $ctx Context
     * @param bool $onlyCost Return only total cost (skip detailed breakdown)
     * @return array Response with cost breakdown
     */
    public function getTotalCost(
        ?msOrder $draft,
        array $orderData,
        string $token,
        string $ctx = 'web',
        bool $onlyCost = false
    ): array {
        $before = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCost', [
            'calculator' => $this,
            'cart' => $this->ms3->getCart(),
            'draft' => $draft,
            'with_cart' => true,
            'only_cost' => $onlyCost,
        ]);
        if (!$before['success']) {
            return $this->error($before['message']);
        }

        $cartCostResponse = $this->getCartCost($draft, $token, $ctx);
        $cartCost = $cartCostResponse['success'] ? (float) $cartCostResponse['data']['cost'] : 0.0;

        $deliveryCostResponse = $this->getDeliveryCost($draft, $orderData, $token, $ctx);
        $deliveryCost = $deliveryCostResponse['success'] ? (float) $deliveryCostResponse['data']['cost'] : 0.0;

        $paymentCostResponse = $this->getPaymentCost(
            $draft,
            $orderData,
            $token,
            $ctx,
            $cartCost,
            $deliveryCost
        );
        $paymentCost = $paymentCostResponse['success'] ? (float) $paymentCostResponse['data']['cost'] : 0.0;

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $breakdown = OrderCostEngine::composeBreakdown($draft, $orderService, $cartCost, $deliveryCost, $paymentCost);
        $cost = $breakdown['cost'];

        $after = $this->ms3->utils->invokeEvent('msOnGetOrderCost', [
            'calculator' => $this,
            'cart' => $this->ms3->getCart(),
            'draft' => $draft,
            'with_cart' => true,
            'only_cost' => $onlyCost,
            'cost' => $cost,
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'payment_cost' => $paymentCost,
        ]);
        if (!$after['success']) {
            return $this->error($after['message']);
        }

        $cost = (float) ($after['data']['cost'] ?? $cost);
        $cartCost = (float) ($after['data']['cart_cost'] ?? $cartCost);
        $deliveryCost = (float) ($after['data']['delivery_cost'] ?? $deliveryCost);
        $paymentCost = (float) ($after['data']['payment_cost'] ?? $paymentCost);
        $breakdown = OrderCostEngine::composeBreakdown($draft, $orderService, $cartCost, $deliveryCost, $paymentCost);
        $cost = $breakdown['cost'];

        if ($onlyCost) {
            return $this->success('ms3_order_getcost_success', ['cost' => $cost]);
        }

        $data = [
            'cost' => $cost,
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'payment_cost' => $paymentCost,
        ];

        // Add cart status info
        $response = $this->ms3->getCart()->status();
        if ($response['success']) {
            $status = $response['data'];
            $data = array_merge($data, $status);
        }

        return $this->success('ms3_order_getcost_success', $data);
    }

    /**
     * Shorthand for success response
     */
    protected function success(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->success($message, $data);
    }

    /**
     * Shorthand for error response
     */
    protected function error(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->error($message ?: 'ms3_err_unknown', $data);
    }
}
