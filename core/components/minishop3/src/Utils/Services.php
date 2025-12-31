<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;

class Services
{
    /**
     * @var modX
     */
    private $modx;
    /**
     * @var MiniShop3
     */
    private $ms3;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;
    }

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function load($ctx = 'web')
    {
        // Load delivery class
        $deliveryController = $this->modx->getOption(
            'ms3_delivery_controller',
            null,
            '\\MiniShop3\\Controllers\\Delivery\\DefaultDelivery'
        );
        if (!class_exists($deliveryController)) {
            $deliveryController = '\\MiniShop3\\Controllers\\Delivery\\DefaultDelivery';
        }

        $delivery = new $deliveryController($this->ms3, $this->ms3->config);
        $this->ms3->setController('delivery', $delivery);

        // Load payment class
        $paymentController = $this->modx->getOption(
            'ms3_payment_controller',
            null,
            '\\MiniShop3\\Controllers\\Payment\\DefaultPayment'
        );
        if (!class_exists($paymentController)) {
            $paymentController = '\\MiniShop3\\Controllers\\Payment\\DefaultPayment';
        }

        $payment = new $paymentController($this->ms3, $this->ms3->config);
        $this->ms3->setController('payment', $payment);

        return true;
    }
}
