<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Controllers\Delivery\DefaultDelivery;
use MiniShop3\Controllers\Payment\DefaultPayment;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Recomputation of order totals from persisted msOrderProducts and configured delivery/payment.
 *
 * Used by manager «Пересчитать стоимость» ({@see calculateBreakdown()} / {@see recalculate()})
 * and draft finalize ({@see OrderFinalizeService}).
 *
 * Manager adapter: modes (auto/manual/force_provider), warnings, order log — no msOn* cost events.
 * Default-handler formulas delegate to {@see OrderCostEngine} (shared with web checkout).
 *
 * External delivery/payment provider classes are not invoked in {@see self::MODE_AUTO};
 * callers should use manual delivery cost or {@see self::MODE_FORCE_PROVIDER}.
 *
 * Payment commission is reflected only in aggregated {@see msOrder cost}, not stored in a column.
 */
class ManagerOrderCostRecalculator
{
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';
    public const MODE_FORCE_PROVIDER = 'force_provider';

    public const WARNING_DELIVERY_MANUAL_REQUIRED = 'delivery_manual_required';
    public const WARNING_PAYMENT_MANUAL_REQUIRED = 'payment_manual_required';
    public const WARNING_DELIVERY_PROVIDER_ERROR = 'delivery_provider_error';
    public const WARNING_PAYMENT_PROVIDER_ERROR = 'payment_provider_error';

    protected modX $modx;

    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * @return array{cart_cost: float, weight: float}
     */
    public function calculateProductTotals(msOrder $order): array
    {
        $products = $this->modx->getIterator(msOrderProduct::class, [
            'order_id' => $order->get('id'),
        ]);

        return OrderService::aggregateProductsTotals($products);
    }

    /**
     * Compute cost breakdown without persisting the order.
     *
     * Shared by manager recalculate and draft finalize so both paths use the same rules.
     *
     * @param array<string, mixed> $options
     * @return array{success: bool, message?: string, data?: array}
     */
    public function calculateBreakdown(msOrder $order, array $options = []): array
    {
        $mode = (string) ($options['mode'] ?? self::MODE_AUTO);

        if ($mode !== self::MODE_AUTO && $mode !== self::MODE_MANUAL && $mode !== self::MODE_FORCE_PROVIDER) {
            return $this->ms3->utils->error('ms3_mgr_order_recalc_invalid_mode');
        }

        if ($mode === self::MODE_MANUAL && !array_key_exists('manual_delivery_cost', $options)) {
            return $this->ms3->utils->error('ms3_mgr_order_recalc_manual_delivery_missing');
        }

        $totals = $this->calculateProductTotals($order);
        $cartCost = $totals['cart_cost'];
        $orderWeight = $totals['weight'];

        $warnings = [];

        $prevDeliveryCost = round((float) $order->get('delivery_cost'), 6);
        $deliveryResult = $this->resolveDeliveryCost($order, $cartCost, $orderWeight, $prevDeliveryCost, $mode, $options);
        if (!$deliveryResult['success']) {
            return $deliveryResult;
        }

        $warnings = array_merge($warnings, $deliveryResult['warnings']);
        $deliveryCost = $deliveryResult['delivery_cost'];

        $paymentBase = OrderCostEngine::paymentCommissionBase($cartCost);
        $paymentResult = $this->resolvePaymentFee($order, $paymentBase, $mode, $options);
        if (!$paymentResult['success']) {
            return $paymentResult;
        }

        $warnings = array_merge($warnings, $paymentResult['warnings']);
        $paymentFee = $paymentResult['payment_fee'];

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $breakdown = OrderCostEngine::composeBreakdown($order, $orderService, $cartCost, $deliveryCost, $paymentFee);
        $cost = $breakdown['cost'];

        return $this->ms3->utils->success('', [
            'breakdown' => [
                'cart_cost' => $cartCost,
                'weight' => $orderWeight,
                'delivery_cost' => $deliveryCost,
                'payment_cost' => $paymentFee,
                'cost' => $cost,
            ],
            'warnings' => $warnings,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, message?: string, data?: array}
     */
    public function recalculate(msOrder $order, array $options = []): array
    {
        $result = $this->calculateBreakdown($order, $options);
        if (!$result['success']) {
            return $result;
        }

        $breakdown = $result['data']['breakdown'];
        $warnings = $result['data']['warnings'];
        $cartCost = $breakdown['cart_cost'];
        $orderWeight = $breakdown['weight'];
        $deliveryCost = $breakdown['delivery_cost'];
        $paymentFee = $breakdown['payment_cost'];
        $cost = $breakdown['cost'];

        $before = [
            'cart_cost' => (float)$order->get('cart_cost'),
            'delivery_cost' => (float)$order->get('delivery_cost'),
            'cost' => (float)$order->get('cost'),
            'weight' => (float)$order->get('weight'),
        ];

        $order->set('cart_cost', $cartCost);
        $order->set('delivery_cost', $deliveryCost);
        $order->set('weight', $orderWeight);
        $order->set('cost', $cost);
        $order->set('updatedon', date('Y-m-d H:i:s'));

        if (!$order->save()) {
            return $this->ms3->utils->error('ms3_err_unknown');
        }

        $changed = [];
        $trackKeys = ['cart_cost', 'delivery_cost', 'cost', 'weight'];
        foreach ($trackKeys as $key) {
            $newVal = (float)$order->get($key);
            if (((float)$before[$key]) != $newVal) {
                $changed[$key] = ['old' => $before[$key], 'new' => $newVal];
            }
        }

        if (!empty($changed)) {
            $this->addFieldLogEntry((int)$order->get('id'), $changed);
        }

        return $this->ms3->utils->success('', [
            'breakdown' => [
                'cart_cost' => $cartCost,
                'weight' => $orderWeight,
                'delivery_cost' => $deliveryCost,
                'payment_cost' => $paymentFee,
                'cost' => $cost,
            ],
            'warnings' => $warnings,
            'cost_fields_changed' => !empty($changed),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, delivery_cost: float, warnings: string[], message?: string}
     */
    protected function resolveDeliveryCost(
        msOrder $order,
        float $cartCost,
        float $orderWeight,
        float $persistedDeliveryCost,
        string $mode,
        array $options
    ): array {
        $warnings = [];
        $deliveryId = (int) $order->get('delivery_id');

        if ($mode === self::MODE_MANUAL) {
            $manual = $options['manual_delivery_cost'];

            return [
                'success' => true,
                'delivery_cost' => round((float)$manual, 6),
                'warnings' => $warnings,
            ];
        }

        if ($deliveryId === 0) {
            return [
                'success' => true,
                'delivery_cost' => 0.0,
                'warnings' => $warnings,
            ];
        }

        /** @var msDelivery|null $msDelivery */
        $msDelivery = $this->modx->getObject(msDelivery::class, ['id' => $deliveryId]);
        if (!$msDelivery) {
            return [
                'success' => true,
                'delivery_cost' => 0.0,
                'warnings' => $warnings,
            ];
        }

        if ($mode === self::MODE_FORCE_PROVIDER) {
            try {
                if (!$msDelivery->loadController()) {
                    return [
                        'success' => true,
                        'delivery_cost' => $persistedDeliveryCost,
                        'warnings' => array_merge($warnings, [self::WARNING_DELIVERY_PROVIDER_ERROR]),
                    ];
                }

                return [
                    'success' => true,
                    'delivery_cost' => round((float) $msDelivery->getCost($order, $cartCost), 6),
                    'warnings' => $warnings,
                ];
            } catch (\Throwable $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ManagerOrderCostRecalculator] Delivery provider error (order #' . $order->get('id') . '): '
                    . $e->getMessage()
                );

                return [
                    'success' => true,
                    'delivery_cost' => $persistedDeliveryCost,
                    'warnings' => array_merge($warnings, [self::WARNING_DELIVERY_PROVIDER_ERROR]),
                ];
            }
        }

        // auto
        if ($this->isSimpleDelivery($msDelivery)) {
            return [
                'success' => true,
                'delivery_cost' => OrderCostEngine::calculateDefaultDeliveryCost(
                    $this->modx,
                    $msDelivery,
                    $cartCost,
                    $orderWeight
                ),
                'warnings' => $warnings,
            ];
        }

        $warnings[] = self::WARNING_DELIVERY_MANUAL_REQUIRED;

        return [
            'success' => true,
            'delivery_cost' => $persistedDeliveryCost,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{success: bool, payment_fee: float, warnings: string[], message?: string}
     */
    protected function resolvePaymentFee(msOrder $order, float $paymentBase, string $mode, array $options): array
    {
        $warnings = [];

        $paymentId = (int) $order->get('payment_id');
        if ($paymentId === 0) {
            return [
                'success' => true,
                'payment_fee' => 0.0,
                'warnings' => $warnings,
            ];
        }

        /** @var msPayment|null $msPayment */
        $msPayment = $this->modx->getObject(msPayment::class, ['id' => $paymentId]);

        if (!$msPayment) {
            return [
                'success' => true,
                'payment_fee' => 0.0,
                'warnings' => $warnings,
            ];
        }

        if ($mode === self::MODE_FORCE_PROVIDER) {
            try {
                if (!$msPayment->loadHandler()) {
                    $warnings[] = self::WARNING_PAYMENT_PROVIDER_ERROR;

                    return [
                        'success' => true,
                        'payment_fee' => 0.0,
                        'warnings' => $warnings,
                    ];
                }

                $withFee = (float)$msPayment->getCost($order, $paymentBase);

                return [
                    'success' => true,
                    'payment_fee' => round($withFee - $paymentBase, 6),
                    'warnings' => $warnings,
                ];
            } catch (\Throwable $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ManagerOrderCostRecalculator] Payment provider error (order #' . $order->get('id') . '): '
                    . $e->getMessage()
                );
                $warnings[] = self::WARNING_PAYMENT_PROVIDER_ERROR;

                return [
                    'success' => true,
                    'payment_fee' => 0.0,
                    'warnings' => $warnings,
                ];
            }
        }

        if ($this->isSimplePayment($msPayment)) {
            return [
                'success' => true,
                'payment_fee' => OrderCostEngine::calculatePaymentSurcharge($this->modx, $msPayment, $paymentBase),
                'warnings' => $warnings,
            ];
        }

        $warnings[] = self::WARNING_PAYMENT_MANUAL_REQUIRED;

        return [
            'success' => true,
            'payment_fee' => 0.0,
            'warnings' => $warnings,
        ];
    }

    protected function isSimpleDelivery(msDelivery $delivery): bool
    {
        return $this->isDefaultShippingPaymentHandlerClass((string) $delivery->get('class'), DefaultDelivery::class);
    }

    protected function isSimplePayment(msPayment $payment): bool
    {
        return $this->isDefaultShippingPaymentHandlerClass((string) $payment->get('class'), DefaultPayment::class);
    }

    /** Compares persisted `class` FQCN to built-in handlers (slashes and case normalized). */
    protected function isDefaultShippingPaymentHandlerClass(string $storedClass, string $expectedDefaultClass): bool
    {
        $normalized = strtolower(str_replace('\\', '', trim(trim($storedClass), '\\')));
        $default = strtolower(str_replace('\\', '', $expectedDefaultClass));

        return $normalized === '' || $normalized === $default;
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     */
    protected function addFieldLogEntry(int $orderId, array $changedFields): void
    {
        if ($orderId <= 0) {
            return;
        }

        if ($this->modx->services->has('ms3_order_log')) {
            /** @var OrderLogService $log */
            $log = $this->modx->services->get('ms3_order_log');
        } else {
            $log = new OrderLogService($this->modx, $this->ms3);
        }

        $log->addEntry($orderId, msOrderLog::ACTION_FIELD, ['fields' => $changedFields]);
    }
}
