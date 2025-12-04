<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;

/**
 * Payment system provider interface
 *
 * Defines contract for all payment providers (YooKassa, Stripe, PayPal, etc.)
 *
 * @package MiniShop3\Controllers\Payment
 */
interface PaymentProviderInterface
{
    /**
     * Send order to payment system
     *
     * Generates payment link to redirect customer to payment page.
     *
     * @param msOrder $order Order for payment
     * @return array Response ['success' => true, 'data' => ['payment_link' => '...', 'payment_id' => '...']]
     */
    public function send(msOrder $order): array;

    /**
     * Process callback from payment system
     *
     * Receives payment status notification from payment system (webhook).
     * Verifies signature, validates data, updates order status.
     *
     * @param msOrder $order Order to check
     * @return array Response ['success' => true/false, 'message' => '...']
     */
    public function receive(msOrder $order): array;

    /**
     * Calculate cost including payment system fee
     *
     * @param msOrder $order Order (can be used for fee calculation)
     * @param msPayment $payment Payment method with fee settings
     * @param float $cost Current order cost
     * @return float Cost including fee
     */
    public function getCost(msOrder $order, msPayment $payment, float $cost): float;

    /**
     * Generate cryptographic order hash
     *
     * Used to verify data authenticity when processing callback.
     *
     * @param msOrder $order Order to hash
     * @return string Order hash
     */
    public function getOrderHash(msOrder $order): string;
}
