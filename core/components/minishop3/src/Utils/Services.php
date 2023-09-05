<?php

namespace MiniShop3\Utils;

use MiniShop3\Controllers\Cart\Cart;
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

        // Custom cart class
        $cartController = $this->modx->getOption(
            'ms3_cart_controller',
            null,
            '\\MiniShop3\\Controllers\\Cart\\Cart'
        );
        if ($cartController !== '\\MiniShop3\\Controllers\\Cart\\Cart') {
            $this->loadCustomClasses('cart');
        }
        if (!class_exists($cartController)) {
            $cartController = Cart::class;
        }

        $cart = new $cartController($this->ms3, $this->ms3->config);
        $this->ms3->setController('cart', $cart);


//        $cart_class = $this->modx->getOption('ms3_cart_handler_class', null, 'msCartHandler');
//        if ($cart_class != 'msCartHandler') {
//            $this->loadCustomClasses('cart');
//        }
//        if (!class_exists($cart_class)) {
//            $cart_class = 'msCartHandler';
//        }
//
//        $this->cart = new $cart_class($this, $this->config);
//        if (!($this->cart instanceof msCartInterface) || $this->cart->initialize($ctx) !== true) {
//            $this->modx->log(
//                modX::LOG_LEVEL_ERROR,
//                'Could not initialize miniShop3 cart handler class: "' . $cart_class . '"'
//            );
//
//            return false;
//        }

        // Custom order class
//        $orderController = $this->modx->getOption(
//            'ms3_order_controller',
//            null,
//            '\\MiniShop3\\Controllers\\Order\\Order'
//        );
//        if ($orderController !== '\\MiniShop3\\Controllers\\Order\\Order') {
//            $this->loadCustomClasses('order');
//        }
//        if (!class_exists($orderController)) {
//            $orderController = Cart::class;
//        }
//
//        $cart = new $orderController($this->ms3, $this->ms3->config);
//        $this->ms3->setController('order', $cart);
//        $order_class = $this->modx->getOption('ms3_order_handler_class', null, 'msOrderHandler');
//        if ($order_class != 'msOrderHandler') {
//            $this->loadCustomClasses('order');
//        }
//        if (!class_exists($order_class)) {
//            $order_class = 'msOrderHandler';
//        }
//
//        $this->order = new $order_class($this, $this->config);
//        if (!($this->order instanceof msOrderInterface) || $this->order->initialize($ctx) !== true) {
//            $this->modx->log(
//                modX::LOG_LEVEL_ERROR,
//                'Could not initialize miniShop3 order handler class: "' . $order_class . '"'
//            );
//
//            return false;
//        }

        $customerController = $this->modx->getOption(
            'ms3_customer_controller',
            null,
            '\\MiniShop3\\Controllers\\Customer\\Customer'
        );
        if ($customerController !== '\\MiniShop3\\Controllers\\Customer\\Customer') {
            $this->loadCustomClasses('customer');
        }
        if (!class_exists($customerController)) {
            $customerController = Customer::class;
        }

        $customer = new $customerController($this->ms3, $this->ms3->config);
        $this->ms3->setController('customer', $customer);
//        if ($this->ms3->customer->initialize($ctx) !== true) {
//            $this->modx->log(
//                modX::LOG_LEVEL_ERROR,
//                'Could not initialize miniShop3 customer controller class: "' . $customerController . '"'
//            );
//
//            return false;
//        }

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
