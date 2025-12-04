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
            'cost' => $cart_cost + $delivery_cost,
            'cart_cost' => $cart_cost,
            'weight' => $weight,
            'update_products' => true
        ]);

        return $order->save();
    }

    /**
     * Handle order save with events
     *
     * @deprecated Logic moved to msOrder::save(), this method kept for backward compatibility
     *
     * @param msOrder $order
     * @param bool|null $cacheFlag
     * @return bool
     */
    public function handleOrderSave(msOrder $order, ?bool $cacheFlag = null): bool
    {
        // Simply delegate call to msOrder::save()
        // It already contains all event logic
        return $order->save($cacheFlag);
    }

    /**
     * Delete order with events
     *
     * @deprecated Logic moved to msOrder::remove(), this method kept for backward compatibility
     *
     * @param msOrder $order
     * @param array $ancestors
     * @return bool Deletion result
     */
    public function removeOrder(msOrder $order, array $ancestors = []): bool
    {
        // Simply delegate call to msOrder::remove()
        // It already contains all event logic
        return $order->remove($ancestors);
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
