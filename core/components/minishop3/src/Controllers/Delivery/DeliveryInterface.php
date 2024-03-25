<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\Model\msDelivery;

interface DeliveryInterface
{

    /**
     * Returns an additional cost depending on the method of delivery
     *
     * @param OrderInterface $order
     * @param msDelivery $delivery
     * @param float $cost
     *
     * @return float|integer
     */
    public function getCost(OrderInterface $order, msDelivery $delivery, float $cost = 0.0): float|int;

    /**
     * Returns failure response
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function error(string $message = '', array $data = [], array $placeholders = []): array|string;

    /**
     * Returns success response
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function success(string $message = '', array $data = [], array $placeholders = []): array|string;
}