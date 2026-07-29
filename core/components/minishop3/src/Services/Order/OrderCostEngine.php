<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MiniShop3\Utils\PriceAdjustment;
use MODX\Revolution\modX;

/**
 * Shared order cost formulas for checkout and manager recalc (#366).
 *
 * Event hooks stay in adapters:
 * - Web: {@see OrderCostCalculator} — msOnBefore/GetCartCost, DeliveryCost, PaymentCost, OrderCost
 * - Manager: {@see ManagerOrderCostRecalculator} — no cost events; mgr modes + warnings + persistence
 *
 * Payment commission base is cart-only (MS2 parity, #460); canonical helper is {@see OrderService::paymentCommissionBase()}.
 */
final class OrderCostEngine
{
    public static function paymentCommissionBase(float $cartCost): float
    {
        return OrderService::paymentCommissionBase($cartCost);
    }

    /**
     * Default delivery formula (weight_price × weight, free_delivery_amount, price/percent on cart).
     */
    public static function calculateDefaultDeliveryCost(
        modX $modx,
        msDelivery $delivery,
        float $cartCost,
        float $orderWeight
    ): float {
        $freeDeliveryAmount = (float) $delivery->get('free_delivery_amount');

        if ($freeDeliveryAmount > 0 && $cartCost >= $freeDeliveryAmount) {
            return 0.0;
        }

        $deliveryCost = 0.0;
        $weightPrice = (float) $delivery->get('weight_price');
        if ($weightPrice < 0) {
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderCostEngine] Invalid weight_price for delivery #' . $delivery->get('id') . ': ' . $weightPrice
            );
            $weightPrice = 0.0;
        }

        $deliveryCost += $weightPrice * $orderWeight;

        $addPrice = $delivery->get('price');
        if (empty($addPrice)) {
            return round($deliveryCost, 6);
        }

        if (PriceAdjustment::isPercent($addPrice)) {
            $percent = PriceAdjustment::getPercent($addPrice);
            if (!PriceAdjustment::isAllowedPercent($percent)) {
                $modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        '[OrderCostEngine] Invalid percent for delivery #%s: %s%%. Must be between -100%% and 100%%.',
                        $delivery->get('id'),
                        $percent
                    )
                );

                return round($deliveryCost, 6);
            }
        }

        return round($deliveryCost + PriceAdjustment::calculate($cartCost, $addPrice), 6);
    }

    /**
     * Payment surcharge only (excluding commission base), aligned with {@see \MiniShop3\Controllers\Payment\Payment::getCost()}.
     */
    public static function calculatePaymentSurcharge(modX $modx, msPayment $payment, float $baseCost): float
    {
        $addPrice = $payment->get('price');
        if (empty($addPrice)) {
            return 0.0;
        }

        if (PriceAdjustment::isPercent($addPrice)) {
            $percent = PriceAdjustment::getPercent($addPrice);
            if (!PriceAdjustment::isAllowedPercent($percent)) {
                $modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        '[OrderCostEngine] Invalid percent for payment #%s: %s%%. Must be between -100%% and 100%%.',
                        $payment->get('id'),
                        $percent
                    )
                );

                return 0.0;
            }
        }

        return round(PriceAdjustment::calculate($baseCost, $addPrice), 6);
    }

    public static function calculatePaymentTotal(float $baseCost, float $surcharge): float
    {
        return round($baseCost + $surcharge, 6);
    }

    /**
     * @return array{cart_cost: float, delivery_cost: float, payment_cost: float, cost: float}
     */
    public static function composeBreakdown(
        ?msOrder $order,
        OrderService $orderService,
        float $cartCost,
        float $deliveryCost,
        float $paymentSurcharge
    ): array {
        $cartCost = round($cartCost, 6);
        $deliveryCost = round($deliveryCost, 6);
        $paymentCost = round($paymentSurcharge, 6);
        $cost = round(
            $orderService->clampComputedTotal($order, $cartCost, $deliveryCost, $paymentCost),
            6
        );

        return [
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'payment_cost' => $paymentCost,
            'cost' => $cost,
        ];
    }
}
