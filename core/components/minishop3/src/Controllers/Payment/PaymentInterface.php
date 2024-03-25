<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;

interface PaymentInterface
{

    /**
     * Send user to payment service
     *
     * @param msOrder $order Object with an order
     *
     * @return array|boolean $response
     */
    public function send(msOrder $order): bool|array;


    /**
     * Receives payment
     *
     * @param msOrder $order Object with an order
     *
     * @return array|boolean $response
     */
    public function receive(msOrder $order): bool|array;

    /**
     * Returns an additional cost depending on the method of payment
     *
     * @param OrderInterface $order
     * @param msPayment $payment
     * @param float $cost
     *
     * @return int|float
     */
    public function getCost(OrderInterface $order, msPayment $payment, float $cost = 0.0): int|float;

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