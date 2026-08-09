<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MODX\Revolution\modSystemEvent;
use MODX\Revolution\modX;

/**
 * Service for working with orders
 *
 * Handles business logic related to orders,
 * including cost recalculation, save and delete events
 */
class OrderService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Base amount for payment commission (MS2-compatible: cart cost only, delivery excluded).
     */
    public static function paymentCommissionBase(float $cartCost): float
    {
        return round($cartCost, 6);
    }

    /**
     * Clamp computed order total so it never goes negative (defence-in-depth vs misconfigured discounts).
     *
     * @param msOrder|null $order Optional persisted order — used only for log context; null allowed for drafts without ID.
     */
    public function clampComputedTotal(
        ?msOrder $order,
        float $cartCost,
        float $deliveryCost,
        float $paymentCost = 0.0
    ): float {
        $total = $cartCost + $deliveryCost + $paymentCost;
        if ($total >= 0.0) {
            return $total;
        }

        $orderCtx = null === $order
            ? 'no order context'
            : (((int) $order->get('id')) > 0
                ? 'order #' . $order->get('id')
                : 'order (unsaved)');

        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            '[MiniShop3][Order] Negative total clamped to 0 ('
                . $orderCtx
                . '): cart_cost='
                . $cartCost
                . ', delivery_cost='
                . $deliveryCost
                . ', payment_cost='
                . $paymentCost
        );

        return 0.0;
    }

    /**
     * Sum cart_cost and total weight from order product lines.
     * Weight is unit weight × count (cart status / draft / finalize / manager).
     *
     * @param iterable<object> $products Objects with get('weight'|'cost'|'count')
     * @return array{cart_cost: float, weight: float}
     */
    public static function aggregateProductsTotals(iterable $products): array
    {
        $cartCost = 0.0;
        $weight = 0.0;

        foreach ($products as $product) {
            $cartCost += (float) $product->get('cost');
            $weight += (float) $product->get('weight') * (int) $product->get('count');
        }

        return [
            'cart_cost' => round($cartCost, 6),
            'weight' => round($weight, 6),
        ];
    }

    /**
     * Recalculate products in order
     *
     * Recalculates total cart cost, weight and final order cost
     * based on all products in order
     *
     * @param msOrder $order
     * @return bool Order save result
     */
    public function updateProducts(msOrder $order): bool
    {
        $delivery_cost = $order->get('delivery_cost');
        $cart_cost = $cost = $weight = 0;

        $products = $order->getMany('Products');
        /** @var msOrderProduct $product */
        foreach ($products as $product) {
            $count = $product->get('count');
            $cart_cost += $product->get('price') * $count;
            $weight += $product->get('weight') * $count;
        }

        $order->fromArray([
            'cost' => $this->clampComputedTotal($order, (float) $cart_cost, (float) $delivery_cost, 0.0),
            'cart_cost' => $cart_cost,
            'weight' => $weight,
            'update_products' => true
        ]);

        return $order->save();
    }

    /**
     * Get order statistics
     *
     * Returns product count and total weight
     *
     * @param msOrder $order
     * @return array
     */
    public function getOrderStatistics(msOrder $order): array
    {
        $products = $order->getMany('Products');
        $totalCount = 0;
        $totalWeight = 0;

        /** @var msOrderProduct $product */
        foreach ($products as $product) {
            $totalCount += $product->get('count');
            $totalWeight += $product->get('weight') * $product->get('count');
        }

        return [
            'product_count' => $totalCount,
            'total_weight' => $totalWeight,
            'cart_cost' => $order->get('cart_cost'),
            'delivery_cost' => $order->get('delivery_cost'),
            'total_cost' => $order->get('cost'),
        ];
    }
}
