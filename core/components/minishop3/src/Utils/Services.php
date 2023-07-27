<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;

class Services extends MiniShop3
{
    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function load($ctx = 'web')
    {
        // Default classes
        if (!class_exists('msCartHandler')) {
            require_once dirname(__FILE__, 3) . '/handlers/mscarthandler.class.php';
        }
        if (!class_exists('msOrderHandler')) {
            require_once dirname(__FILE__, 3) . '/handlers/msorderhandler.class.php';
        }

        // Custom cart class
        $cart_class = $this->modx->getOption('ms3_cart_handler_class', null, 'msCartHandler');
        if ($cart_class != 'msCartHandler') {
            $this->loadCustomClasses('cart');
        }
        if (!class_exists($cart_class)) {
            $cart_class = 'msCartHandler';
        }

        $this->cart = new $cart_class($this, $this->config);
        if (!($this->cart instanceof msCartInterface) || $this->cart->initialize($ctx) !== true) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Could not initialize miniShop3 cart handler class: "' . $cart_class . '"'
            );

            return false;
        }

        // Custom order class
        $order_class = $this->modx->getOption('ms3_order_handler_class', null, 'msOrderHandler');
        if ($order_class != 'msOrderHandler') {
            $this->loadCustomClasses('order');
        }
        if (!class_exists($order_class)) {
            $order_class = 'msOrderHandler';
        }

        $this->order = new $order_class($this, $this->config);
        if (!($this->order instanceof msOrderInterface) || $this->order->initialize($ctx) !== true) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Could not initialize miniShop3 order handler class: "' . $order_class . '"'
            );

            return false;
        }

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
        $services = $this->utils->getSetting('ms3_services');
        $type = strtolower($type);
        $name = strtolower($name);
        if (!isset($services[$type])) {
            $services[$type] = array($name => $controller);
        } else {
            $services[$type][$name] = $controller;
        }

        $this->utils->updateSetting('ms3_services', $services);
    }

    /**
     * Remove service from miniShop3
     *
     * @param $type
     * @param $name
     */
    public function remove($type, $name)
    {
        $services = $this->utils->getSetting('ms3_services');
        $type = strtolower($type);
        $name = strtolower($name);
        unset($services[$type][$name]);
        $this->utils->updateSetting('ms3_services', $services);
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
        $services = $this->utils->getSetting('ms3_services');

        if (is_array($services)) {
            return !empty($type) && isset($services[$type])
                ? $services[$type]
                : $services;
        }

        return array();
    }
}
