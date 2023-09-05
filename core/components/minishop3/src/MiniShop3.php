<?php

namespace MiniShop3;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\Controllers\Options\Options;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\Controllers\Order\OrderStatus;
use MiniShop3\Utils\Format;
use MiniShop3\Utils\Plugins;
use MiniShop3\Utils\Services;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use ModxPro\PdoTools\Fetch;

class MiniShop3
{
    public $version = '1.0.0-alpha';

    /** @var modX $modx */
    public $modx;
    /** @var Fetch $pdoFetch */
    public $pdoFetch;
    /** @var Cart $cart */
    public $cart;
    /** @var Order $order */
    public $order;
    /** @var Customer $customer */
    public $customer;
    /** @var array $initialized */
    public $initialized = [];

    /** @var array $config */
    public $config = [];

    /** @var Utils $utils */
    public $utils;

    /** @var Format $format */
    public $format;

    /** @var Services $services */
    private $services;

    /** @var Plugins $plugins */
    public $plugins;

    /** @var Options $options */
    public $options;

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $corePath = $this->modx->getOption('ms3_core_path', $config, MODX_CORE_PATH . 'components/minishop3/');
        $assetsPath = $this->modx->getOption(
            'ms3.assets_path',
            $config,
            MODX_ASSETS_PATH . 'components/minishop3/'
        );
        $assetsUrl = $this->modx->getOption('ms3_assets_url', $config, MODX_ASSETS_URL . 'components/minishop3/');
        $actionUrl = $this->modx->getOption('ms3_action_url', $config, $assetsUrl . 'action.php');
        $connectorUrl = $assetsUrl . 'connector.php';
        $this->config = array_merge([
            'corePath' => $corePath,
            'assetsPath' => $assetsPath,
            'customPath' => $corePath . 'custom/',
            'pluginsPath' => $corePath . 'plugins/',

            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . 'css/',
            'jsUrl' => $assetsUrl . 'js/',
            'connectorUrl' => $connectorUrl,
            'connector_url' => $connectorUrl,
            'actionUrl' => $actionUrl,

            'defaultThumb' => trim($this->modx->getOption('ms3_product_thumbnail_default', null, true)),
            'ctx' => 'web',
            'json_response' => false,
        ], $config);

        if ($this->modx->services->has(Fetch::class)) {
            $this->pdoFetch = $this->modx->services->get(Fetch::class);
        }
        if ($this->pdoFetch) {
            $this->pdoFetch->setConfig($this->config);
        }

        $this->utils = new Utils($this);
        $this->format = new Format($this);
        $this->services = new Services($this);
        //$this->plugins = new Plugins($this);
        $this->options = new Options($this);
    }

    public function setController($type, $controller)
    {
        $this->$type = $controller;
    }

    public function registerFrontend($ctx = 'web')
    {
        if ($ctx !== 'mgr' && (!defined('MODX_API_MODE') || !MODX_API_MODE)) {
            $this->modx->lexicon->load('minishop3:default');

            $config = $this->pdoFetch->makePlaceholders($this->config);

            $assets = json_decode($this->modx->getOption('ms3_frontend_assets', null, '[]'), true);

            if (!empty($assets)) {
                foreach ($assets as $file) {
                    if (!empty($file) && preg_match('/\.js/i', $file)) {
                        $file = str_replace($config['pl'], $config['vl'], $file);
                        //fix trouble with caching regClientScript
                        if (!str_contains($this->modx->getRegisteredClientScripts(), $file)) {
                            if (preg_match('/\.js$/i', $file)) {
                                $file .= '?v=' . date('dmYHi', filemtime(MODX_BASE_PATH . ltrim($file, '/')));
                            }
                            $this->modx->regClientScript('<script src="' . $file . '" defer></script>', true);
                        }
                    }

                    if (!empty($file) && preg_match('/\.css/i', $file)) {
                        if (preg_match('/\.css$/i', $file)) {
                            $file .= '?v=' . date('dmYHi', filemtime($file));
                            $this->modx->regClientCSS(str_replace($config['pl'], $config['vl'], $file));
                        }
                    }
                }
            }

            $registerGlobalConfig = json_decode($this->modx->getOption('ms3_register_global_config', null, true), true);
            if ($registerGlobalConfig) {
                $tokenName = $this->modx->getOption('ms3_token_name', null, 'ms3_token');
                $js_setting = [
                    //'actionUrl' => rtrim($this->modx->getOption('site_url'), '/') . $this->config['actionUrl'],
                    'actionUrl' => $this->modx->getOption('site_url'),
                    'ctx' => $ctx,
                    'tokenName' => $tokenName
                ];

                $data = json_encode($js_setting, JSON_UNESCAPED_UNICODE);
                $this->modx->regClientStartupScript(
                    '<script>ms3Config = ' . $data . ';</script>',
                    true
                );
            }
        }
    }

    /**
     * Handle frontend requests with actions
     *
     * @param $action
     * @param array $data
     *
     * @return array|bool|string
     */
    public function handleRequest($action, $data = [])
    {
        $ctx = !empty($data['ctx'])
            ? (string)$data['ctx']
            : 'web';
        if ($ctx != 'web') {
            $this->modx->switchContext($ctx);
        }
        $this->initialize($ctx);

        switch ($action) {
            case 'customer/token/get':
                $response = $this->customer->generateToken();
                break;
            case 'cart/add':
                $response = $this->cart->add(@$data['id'], @$data['count'], @$data['options']);
                break;
            case 'cart/change':
                $response = $this->cart->change(@$data['key'], @$data['count']);
                break;
            case 'cart/changeOption':
                $response = $this->cart->changeOption(@$data['key'], @$data['count']);
                break;
            case 'cart/remove':
                $response = $this->cart->remove(@$data['key']);
                break;
            case 'cart/clean':
                $response = $this->cart->clean();
                break;
            case 'cart/get':
                $response = $this->cart->get();
                break;
            case 'order/add':
                $response = $this->order->add(@$data['key'], @$data['value']);
                break;
            case 'order/submit':
                $response = $this->order->submit($data);
                break;
            case 'order/getcost':
                $response = $this->order->getCost();
                break;
            case 'order/getrequired':
                $response = $this->order->getDeliveryRequiresFields(@$data['id']);
                break;
            case 'order/clean':
                $response = $this->order->clean();
                break;
            case 'order/get':
                $response = $this->order->get();
                break;
            default:
                $message = ($data['ms3_action'] != $action)
                    ? 'ms3_err_register_globals'
                    : 'ms3_err_unknown';
                $response = $this->utils->error($message);
        }

        return $response;
    }

    /**
     * Initializes component into different contexts.
     *
     * @param string $ctx The context to load. Defaults to web.
     * @param array $scriptProperties Properties for initialization.
     *
     * @return bool
     */
    public function initialize($ctx = 'web', $scriptProperties = [])
    {
        if (isset($this->initialized[$ctx])) {
            return $this->initialized[$ctx];
        }
        $this->config = array_merge($this->config, $scriptProperties);
        $this->config['ctx'] = $ctx;
        $this->modx->lexicon->load('minishop3:default');

        $load = $this->services->load($ctx);
        $this->initialized[$ctx] = $load;

        return $load;
    }

    /**
     * Loads additional metadata for miniShop3 objects
     */
    public function loadMap()
    {
        if (method_exists($this->pdoFetch, 'makePlaceholders')) {
//            $plugins = $this->plugins->load();
//            foreach ($plugins as $plugin) {
//                // For legacy plugins
//                if (isset($plugin['xpdo_meta_map']) && is_array($plugin['xpdo_meta_map'])) {
//                    $plugin['map'] = $plugin['xpdo_meta_map'];
//                }
//                if (isset($plugin['map']) && is_array($plugin['map'])) {
//                    foreach ($plugin['map'] as $class => $map) {
//                        if (!isset($this->modx->map[$class])) {
//                            $this->modx->loadClass($class, $this->config['modelPath'] . 'minishop3/');
//                        }
//                        if (isset($this->modx->map[$class])) {
//                            foreach ($map as $key => $values) {
//                                $this->modx->map[$class][$key] = array_merge($this->modx->map[$class][$key], $values);
//                            }
//                        }
//                    }
//                }
//            }
        } else {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'pdoTools not installed, metadata for miniShop3 objects not loaded'
            );
        }
    }

//    public function changeOrderStatus($order_id, $status_id)
//    {
//        $orderStatus = new OrderStatus($this);
//        $orderStatus->change($order_id, $status_id);
//    }

//    public function getCustomerId()
//    {
//        $customer = new Customer($this);
//        return $customer->getId();
//    }
}
