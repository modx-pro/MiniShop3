<?php

namespace MiniShop3;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Options\Options;
use MiniShop3\Controllers\Order\Customer;
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
    /** @var array $initialized */
    public $initialized = [];

    /** @var array $config */
    public $config = [];

    /** @var Utils $utils */
    public $utils;

    /** @var Format $format */
    public $format;

    /** @var Services $services */
    public $services;

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
        //$this->services = new Services($this->modx);
        //$this->plugins = new Plugins($this);
        $this->options = new Options($this);
    }

    public function registerFrontend($ctx = 'web')
    {
        if ($ctx != 'mgr' && (!defined('MODX_API_MODE') || !MODX_API_MODE)) {
            $this->modx->lexicon->load('minishop3:default');

            $config = $this->pdoFetch->makePlaceholders($this->config);

            // Register CSS
            $css = trim($this->modx->getOption('ms3_frontend_css'));
            if (!empty($css) && preg_match('/\.css/i', $css)) {
                if (preg_match('/\.css$/i', $css)) {
                    $css .= '?v=' . substr(md5($this->version), 0, 10);
                }
                $this->modx->regClientCSS(str_replace($config['pl'], $config['vl'], $css));
            }

            // Register notify plugin CSS
            $message_css = trim($this->modx->getOption('ms3_frontend_message_css'));
            if (!empty($message_css) && preg_match('/\.css/i', $message_css)) {
                $this->modx->regClientCSS(str_replace($config['pl'], $config['vl'], $message_css));
            }

            // Register JS
            $js = trim($this->modx->getOption('ms3_frontend_js'));
            if (!empty($js) && preg_match('/\.js/i', $js)) {
                if (preg_match('/\.js$/i', $js)) {
                    $js .= '?v=' . substr(md5($this->version), 0, 10);
                }
                $this->modx->regClientScript(str_replace($config['pl'], $config['vl'], $js));
            }

            $message_setting = [
                'close_all_message' => $this->modx->lexicon('ms3_message_close_all'),
            ];

            $js_setting = [
                'cssUrl' => $this->config['cssUrl'] . 'web/',
                'jsUrl' => $this->config['jsUrl'] . 'web/',
                'actionUrl' => $this->config['actionUrl'],
                'ctx' => $ctx,
                'price_format' => json_decode(
                    $this->modx->getOption('ms3_price_format', null, '[2, ".", " "]'),
                    true
                ),
                'price_format_no_zeros' => (bool)$this->modx->getOption('ms3_price_format_no_zeros', null, true),
                'weight_format' => json_decode(
                    $this->modx->getOption('ms3_weight_format', null, '[3, ".", " "]'),
                    true
                ),
                'weight_format_no_zeros' => (bool)$this->modx->getOption('ms3_weight_format_no_zeros', null, true),
            ];

            $data = json_encode(array_merge($message_setting, $js_setting), true);
            $this->modx->regClientStartupScript(
                '<script>miniShopConfig = ' . $data . ';</script>',
                true
            );

            // Register notify plugin JS
            $message_js = trim($this->modx->getOption('ms3_frontend_message_js'));
            if (!empty($message_js) && preg_match('/\.js/i', $message_js)) {
                $this->modx->regClientScript(str_replace($config['pl'], $config['vl'], $message_js));
            }

            $message_settings_js = trim($this->modx->getOption('ms3_frontend_message_js_settings'));
            if (!empty($message_settings_js) && preg_match('/\.js/i', $message_settings_js)) {
                $this->modx->regClientScript(str_replace($config['pl'], $config['vl'], $message_settings_js));
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
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
        $this->initialize($ctx, ['json_response' => $isAjax]);

        switch ($action) {
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
                $response = $this->error($message);
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
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'pdoTools not installed, metadata for miniShop3 objects not loaded');
        }
    }

    public function changeOrderStatus($order_id, $status_id)
    {
        $orderStatus = new OrderStatus($this);
        $orderStatus->change($order_id, $status_id);
    }

    public function getCustomerId()
    {
        $customer = new Customer($this);
        return $customer->getId();
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
