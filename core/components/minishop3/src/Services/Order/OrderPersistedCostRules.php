<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Utils\PriceAdjustment;

/**
 * Pure cost rules for orders built from persisted msOrderProducts (manager recalculate, draft finalize).
 *
 * Shared by {@see ManagerOrderCostRecalculator} and smoke tests; no MODX I/O.
 */
final class OrderPersistedCostRules
{
    public static function calculateDefaultDeliveryCost(
        float $freeDeliveryAmount,
        float $weightPrice,
        mixed $addPrice,
        float $cartCost,
        float $orderWeight
    ): float {
        if ($freeDeliveryAmount > 0 && $cartCost >= $freeDeliveryAmount) {
            return 0.0;
        }

        if ($weightPrice < 0) {
            $weightPrice = 0.0;
        }

        $deliveryCost = $weightPrice * $orderWeight;

        if (empty($addPrice)) {
            return round($deliveryCost, 6);
        }

        if (PriceAdjustment::isPercent($addPrice)) {
            $percent = PriceAdjustment::getPercent($addPrice);
            if (!PriceAdjustment::isAllowedPercent($percent)) {
                return round($deliveryCost, 6);
            }
        }

        return round($deliveryCost + PriceAdjustment::calculate($cartCost, $addPrice), 6);
    }

    /**
     * Surcharge only (excluding base), aligned with {@see \MiniShop3\Controllers\Payment\Payment::getCost()}.
     */
    public static function calculateDefaultPaymentCommission(mixed $addPrice, float $baseCost): float
    {
        if (empty($addPrice)) {
            return 0.0;
        }

        if (PriceAdjustment::isPercent($addPrice)) {
            $percent = PriceAdjustment::getPercent($addPrice);
            if (!PriceAdjustment::isAllowedPercent($percent)) {
                return 0.0;
            }
        }

        return round(PriceAdjustment::calculate($baseCost, $addPrice), 6);
    }
}
