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
        // Default classes
//        if (!class_exists('msCartHandler')) {
//            require_once dirname(__FILE__, 3) . '/handlers/mscarthandler.class.php';
//        }
//        if (!class_exists('msOrderHandler')) {
//            require_once dirname(__FILE__, 3) . '/handlers/msorderhandler.class.php';
//        }

//        if (!class_exists(\MiniShop3\Controllers\Customer\Customer::class)) {
//            require_once dirname(__FILE__, 2) . '/Controllers/Customer/Customer.php';
//        }

        // Cart и Order теперь управляются через ServiceRegistry
        // Доступ: $ms3->cart, $ms3->order (через __get() → getCart()/getOrder())
        // Конфигурация: core/config/ms3.services.php или ms3.services.d/*.php

        // Cart, Order, Customer теперь управляются через ServiceRegistry
        // Доступ: $ms3->cart, $ms3->order, $ms3->customer (через __get() → getCart()/getOrder()/getCustomer())
        // Конфигурация: core/config/ms3.services.php или ms3.services.d/*.php

        // DEPRECATED: Старая система через setController больше не используется для cart/order/customer
        // $cart = new $cartController($this->ms3, $this->ms3->config);
        // $this->ms3->setController('cart', $cart);
        // $order = new $orderController($this->ms3, $this->ms3->config);
        // $this->ms3->setController('order', $order);
        // $customer = new $customerController($this->ms3, $this->ms3->config);
        // $this->ms3->setController('customer', $customer);


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
     * @return void
     * @var string $type Type of class
     *
     */
    public function loadCustomClasses($type)
    {
        // Original classes
//        $files = scandir($this->config['customPath'] . $type);
//        foreach ($files as $file) {
//            if (preg_match('/.*?\.class\.php$/i', $file)) {
//                include_once($this->config['customPath'] . $type . '/' . $file);
//            }
//        }
//
//        // 3rd party classes
//        $type = strtolower($type);
//        $placeholders = [
//            'base_path' => MODX_BASE_PATH,
//            'core_path' => MODX_CORE_PATH,
//            'assets_path' => MODX_ASSETS_PATH,
//        ];
//        $pl1 = $this->pdoFetch->makePlaceholders($placeholders, '', '[[+', ']]', false);
//        $pl2 = $this->pdoFetch->makePlaceholders($placeholders, '', '[[++', ']]', false);
//        $pl3 = $this->pdoFetch->makePlaceholders($placeholders, '', '{', '}', false);
//        $services = $this->services->get();
//        if (!empty($services[$type]) && is_array($services[$type])) {
//            foreach ($services[$type] as $controller) {
//                if (is_string($controller)) {
//                    $file = $controller;
//                } elseif (is_array($controller) && !empty($controller['controller'])) {
//                    $file = $controller['controller'];
//                } else {
//                    continue;
//                }
//
//                $file = str_replace($pl1['pl'], $pl1['vl'], $file);
//                $file = str_replace($pl2['pl'], $pl2['vl'], $file);
//                $file = str_replace($pl3['pl'], $pl3['vl'], $file);
//                if (strpos($file, MODX_BASE_PATH) === false && strpos($file, MODX_CORE_PATH) === false) {
//                    $file = MODX_BASE_PATH . ltrim($file, '/');
//                }
//                if (file_exists($file)) {
//                    include_once($file);
//                } else {
//                    $this->modx->log(modX::LOG_LEVEL_ERROR, "[miniShop3] Could not load custom class at \"$file\"");
//                }
//            }
//        }
    }
}
