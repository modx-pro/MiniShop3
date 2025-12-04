<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;

/**
 * Interface DeliveryProviderInterface
 *
 * Defines contract for all delivery providers (CDEK, Russian Post, Courier, etc.)
 * All custom delivery classes must implement this interface
 *
 * @package MiniShop3\Controllers\Delivery
 */
interface DeliveryProviderInterface
{
    /**
     * Calculate delivery cost
     *
     * @param msOrder $order Order for delivery calculation
     * @param msDelivery $delivery Delivery method
     * @param float $cost Current order cost
     * @return float Additional delivery cost
     */
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float;
}
