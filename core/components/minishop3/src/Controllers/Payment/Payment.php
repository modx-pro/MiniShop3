<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Order\OrderCostEngine;
use MODX\Revolution\modX;

/**
 * Base abstract class for payment providers
 *
 * Provides basic functionality for payment system integration:
 * - Fee calculation (percentage/fixed)
 * - Secure order hash generation
 * - Helper methods success/error
 *
 * To create custom provider:
 * 1. Create class extending Payment
 * 2. Implement required methods send() and receive()
 * 3. Override getCost() if needed for custom fee logic
 *
 * Example of creating YooKassa provider:
 * ```php
 * class YooKassaPayment extends Payment {
 *     protected string $shopId;
 *     protected string $secretKey;
 *
 *     public function __construct(MiniShop3 $ms3, array $config = []) {
 *         parent::__construct($ms3, $config);
 *         $this->shopId = $this->modx->getOption('ms3_yookassa_shop_id');
 *         $this->secretKey = $this->modx->getOption('ms3_yookassa_secret_key');
 *     }
 *
 *     public function send(msOrder $order): array {
 *         // Create payment via YooKassa API
 *         $client = new \YooKassa\Client();
 *         $client->setAuth($this->shopId, $this->secretKey);
 *
 *         $payment = $client->createPayment([
 *             'amount' => ['value' => $order->get('cost'), 'currency' => 'RUB'],
 *             'confirmation' => ['type' => 'redirect', 'return_url' => '...'],
 *             'description' => "Order #{$order->get('num')}",
 *             'metadata' => ['order_hash' => $this->getOrderHash($order)],
 *         ]);
 *
 *         return $this->success('', [
 *             'payment_link' => $payment->getConfirmation()->getConfirmationUrl(),
 *             'payment_id' => $payment->getId(),
 *         ]);
 *     }
 *
 *     public function receive(msOrder $order): array {
 *         // Process webhook from YooKassa
 *         $json = file_get_contents('php://input');
 *         $data = json_decode($json, true);
 *
 *         // Verify order hash
 *         if (!hash_equals($this->getOrderHash($order), $data['metadata']['order_hash'])) {
 *             return $this->error('Invalid order hash');
 *         }
 *
 *         if ($data['status'] === 'succeeded') {
 *             $order->set('status_id', $this->getPaidStatusId());
 *             $order->save();
 *             return $this->success('Payment confirmed');
 *         }
 *
 *         if ($data['status'] === 'canceled') {
 *             $order->set('status_id', $this->getCanceledStatusId());
 *             $order->save();
 *             return $this->error('Payment canceled');
 *         }
 *
 *         return $this->error('Payment failed');
 *     }
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Payment
 */
abstract class Payment implements PaymentProviderInterface
{
    /** @var modX MODX instance */
    protected modX $modx;

    /** @var MiniShop3 MiniShop3 instance */
    protected MiniShop3 $ms3;

    /** @var array Provider configuration */
    protected array $config = [];

    /**
     * Constructor
     *
     * @param MiniShop3 $ms3 MiniShop3 instance
     * @param array $config Additional provider configuration
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->config = $config;

        $this->modx->lexicon->load('minishop3:payment');
    }

    /**
     * Send order to payment system (abstract method)
     *
     * Must be implemented in child class to create payment
     * and get redirect link to payment page.
     *
     * @param msOrder $order Order for payment
     * @return array Response ['success' => true, 'data' => ['payment_link' => '...', 'payment_id' => '...']]
     */
    abstract public function send(msOrder $order): array;

    /**
     * Process callback from payment system (abstract method)
     *
     * Must be implemented in child class to handle notifications
     * from payment system about payment status (webhook).
     *
     * @param msOrder $order Order to check
     * @return array Response ['success' => true/false, 'message' => '...']
     */
    abstract public function receive(msOrder $order): array;

    /**
     * Calculate cost including payment system fee
     *
     * Supports two fee formats:
     * - Percentage: "3%" - 3% fee of order amount
     * - Fixed: "50" - 50 rubles fee
     *
     * @param msOrder $order Order (can be used for fee calculation)
     * @param msPayment $payment Payment method with fee settings
     * @param float $cost Commission base before surcharge: cart lines only (MS2 parity, #460)
     * @return float Cost including fee
     */
    public function getCost(msOrder $order, msPayment $payment, float $cost): float
    {
        $surcharge = OrderCostEngine::calculatePaymentSurcharge($this->modx, $payment, $cost);

        return OrderCostEngine::calculatePaymentTotal($cost, $surcharge);
    }

    /**
     * Get payment link for order
     *
     * Calls send() method and extracts payment_link from response.
     * Used to display "Pay" button on order page.
     *
     * Note: Method calls send() each time without caching.
     * For payment systems with API limits caching is recommended.
     *
     * @param msOrder $order Order for payment
     * @return string|null Payment link or null if failed
     */
    public function getPaymentLink(msOrder $order): ?string
    {
        try {
            $response = $this->send($order);
            return $response['data']['payment_link'] ?? null;
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Payment] Error getting payment link for order #{$order->get('id')}: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Generate cryptographic order hash
     *
     * Used to verify data authenticity when processing callback from payment system.
     * Uses secure HMAC-SHA256 algorithm with secret key.
     *
     * System setting ms3_payment_secret must be configured.
     * If not set, site_id will be used as fallback.
     *
     * @param msOrder $order Order to hash
     * @return string Order hash (64 hex characters)
     */
    public function getOrderHash(msOrder $order): string
    {
        $secret = $this->modx->getOption('ms3_payment_secret', null, '');

        if (empty($secret)) {
            $secret = $this->modx->getOption('site_id');
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[Payment] ms3_payment_secret not set, using site_id as fallback. Please set ms3_payment_secret for better security."
            );
        }

        // Use | as separator to prevent collisions
        // (avoid situation when "123" + "456" == "12" + "3456")
        $data = implode('|', [
            $order->get('id'),
            $order->get('num'),
            $order->get('cost'),
            $order->get('createdon')
        ]);

        // HMAC-SHA256 - secure algorithm with secret key
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Return error response
     *
     * @param string $message Error message (lexicon key or text)
     * @param array $data Additional data
     * @param array $placeholders Placeholders for message
     * @return array ['success' => false, 'message' => '...', 'data' => [...]]
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Return success response
     *
     * @param string $message Success message (lexicon key or text)
     * @param array $data Additional data
     * @param array $placeholders Placeholders for message
     * @return array ['success' => true, 'message' => '...', 'data' => [...]]
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Get "Paid" status ID from system settings
     *
     * Returns status ID that should be set after successful payment.
     * Configured via ms3_status_paid system setting.
     *
     * @return int Status ID (default: 3)
     */
    protected function getPaidStatusId(): int
    {
        return (int) $this->modx->getOption('ms3_status_paid', null, 3) ?: 3;
    }

    /**
     * Get "Canceled" status ID from system settings
     *
     * Returns status ID that should be set when payment is canceled/failed.
     * Configured via ms3_status_canceled system setting.
     *
     * @return int Status ID (default: 5)
     */
    protected function getCanceledStatusId(): int
    {
        return (int) $this->modx->getOption('ms3_status_canceled', null, 5);
    }
}
