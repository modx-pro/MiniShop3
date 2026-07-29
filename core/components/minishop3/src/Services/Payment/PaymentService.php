<?php

namespace MiniShop3\Services\Payment;

use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Service for working with payments
 *
 * Handles business logic related to order payments,
 * including loading controllers, sending to payment gateway,
 * receiving payments and cost calculation
 */
class PaymentService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /** @var string */
    protected $defaultControllerClass = 'MiniShop3\\Controllers\\Payment\\DefaultPayment';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        if ($modx->services->has('ms3')) {
            $this->ms3 = $modx->services->get('ms3');
        }
    }

    /**
     * Load payment controller (payment handler)
     *
     * Creates payment controller instance based on class from settings.
     * If class is not specified, uses default controller (DefaultPayment).
     *
     * @param msPayment $payment
     * @return PaymentProviderInterface|null Payment controller or null on error
     */
    public function loadPaymentHandler(msPayment $payment): ?PaymentProviderInterface
    {
        $class = $payment->get('class');
        if (empty($class)) {
            $class = $this->defaultControllerClass;
        }

        try {
            $controller = new $class($this->ms3, []);

            if (!$controller instanceof PaymentProviderInterface) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        'PaymentService: Class "%s" does not implement PaymentProviderInterface for payment method ID=%d',
                        $class,
                        $payment->get('id')
                    )
                );
                return null;
            }

            return $controller;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    'PaymentService: Error loading payment controller "%s": %s',
                    $class,
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Send user to payment gateway
     *
     * Redirects user to payment system website for order payment.
     * Controller can generate auto-submit form or return URL for redirect.
     *
     * @param msPayment $payment Payment method
     * @param PaymentProviderInterface|null $controller Payment controller (if null - will be loaded)
     * @param msOrder $order Order
     * @return array|bool Array with redirect data or false on error
     */
    public function sendToPaymentGateway(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order
    ) {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return false;
            }
        }

        return $controller->send($order);
    }

    /**
     * Receive payment from payment system
     *
     * Handles callback from payment gateway after payment.
     * Usually called on user return or via webhook.
     *
     * @param msPayment $payment Payment method
     * @param PaymentProviderInterface|null $controller Payment controller (if null - will be loaded)
     * @param msOrder $order Order
     * @return array|bool Payment processing result or false on error
     */
    public function receivePayment(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order
    ) {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return false;
            }
        }

        return $controller->receive($order);
    }

    /**
     * Calculate payment cost
     *
     * Delegates cost calculation to payment controller.
     * Controller can add commission for using this payment method.
     *
     * @param msPayment $payment Payment method
     * @param PaymentProviderInterface|null $controller Payment controller (if null - will be loaded)
     * @param msOrder $order Order
     * @param float $cost Current order cost
     * @return float Additional cost for payment method
     */
    public function calculatePaymentCost(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order,
        float $cost = 0.0
    ): float {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return 0.0;
            }
        }

        return (float)$controller->getCost($order, $payment, $cost);
    }

    /**
     * Delete payment method with relationship cleanup
     *
     * Removes all payment method relationships with deliveries
     * from msDeliveryMember table before deletion
     *
     * @param msPayment $payment
     * @param array $ancestors
     * @return bool
     */
    public function removePayment(msPayment $payment, array $ancestors = []): bool
    {
        $paymentId = $payment->get('id');

        // Remove all payment method relationships with deliveries
        $this->modx->removeCollection(msDeliveryMember::class, [
            'payment_id' => $paymentId
        ]);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            sprintf(
                'PaymentService: Deleted DeliveryMember relationships for payment method ID=%d "%s"',
                $paymentId,
                $payment->get('name')
            )
        );

        return true;
    }
}
