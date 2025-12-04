<?php

namespace MiniShop3\Utils;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\Controllers\Customer\Customer;
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
        if ($deliveryController !== '\\MiniShop3\\Controllers\\Delivery\\DefaultDelivery') {
            $this->loadCustomClasses('Delivery');
        }
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
        if ($paymentController !== '\\MiniShop3\\Controllers\\Payment\\DefaultPayment') {
            $this->loadCustomClasses('Payment');
        }
        if (!class_exists($paymentController)) {
            $paymentController = '\\MiniShop3\\Controllers\\Payment\\DefaultPayment';
        }

        $payment = new $paymentController($this->ms3, $this->ms3->config);
        $this->ms3->setController('payment', $payment);

        return true;
    }

    /**
     * Register service into miniShop3
     *
     * @param $type
     * @param $name
     * @param $controller
     */
    public function add($type, $name, $controller)
    {
        $services = $this->ms3->utils->getSetting('ms3_services');
        $type = strtolower($type);
        $name = strtolower($name);
        if (!isset($services[$type])) {
            $services[$type] = [$name => $controller];
        } else {
            $services[$type][$name] = $controller;
        }

        $this->ms3->utils->updateSetting('ms3_services', $services);
    }

    /**
     * Remove service from miniShop3
     *
     * @param $type
     * @param $name
     */
    public function remove($type, $name)
    {
        $services = $this->ms3->utils->getSetting('ms3_services');
        $type = strtolower($type);
        $name = strtolower($name);
        unset($services[$type][$name]);
        $this->ms3->utils->updateSetting('ms3_services', $services);
    }

    /**
     * Get all registered services
     *
     * @param string $type
     *
     * @return array|mixed
     */
    public function get($type = '')
    {
        $services = $this->ms3->utils->getSetting('ms3_services');

        if (is_array($services)) {
            return !empty($type) && isset($services[$type])
                ? $services[$type]
                : $services;
        }

        return [];
    }

    /**
     * Load custom classes from specified directory
     *
     * @param string $type Type of class
     * @return void
     */
    public function loadCustomClasses($type)
    {
    }
}
