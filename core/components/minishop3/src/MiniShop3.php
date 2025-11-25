<?php

namespace MiniShop3;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\Controllers\Options\Options;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\Controllers\Order\OrderStatus;
use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\Model\msOrder;
use MiniShop3\ServiceRegistry;
use MiniShop3\Utils\ExtraFields;
use MiniShop3\Utils\Format;
use MiniShop3\Utils\Plugins;
use MiniShop3\Utils\Services;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use ModxPro\PdoTools\CoreTools;
use ModxPro\PdoTools\Fetch;
use xPDO\xPDO;

class MiniShop3
{
    public $version = '1.0.0-alpha.2';

    /** @var modX $modx */
    public $modx;
    /** @var Fetch $pdoFetch */
    public $pdoFetch;
    /** @var CoreTools $pdoTools */
    public $pdoTools;

    // ВАЖНО: $cart, $order, $customer НЕ должны быть публичными свойствами!
    // Они доступны через магические методы __get() → getCart() / getOrder() / getCustomer()
    // Это позволяет ServiceRegistry управлять их созданием через DI контейнер

    /** @var Delivery $delivery */
    public $delivery;
    /** @var PaymentProviderInterface $payment */
    public $payment;
    /** @var array $initialized */
    public $initialized = [];

    /** @var array $config */
    public $config = [];

    /** @var Utils $utils */
    public $utils;

    /** @var Format $format */
    public $format;

    /** @var Services $services */
    public Services $services;

    /** @var Plugins $plugins */
    public $plugins;

    /** @var ExtraFields $extraFields */
    public $extraFields;

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
        $actionUrl = $this->modx->getOption('ms3_action_url', $config, $assetsUrl . 'api.php');
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
        if ($this->modx->services->has(CoreTools::class)) {
            $this->pdoTools = $this->modx->services->get(CoreTools::class);
        }
        if ($this->pdoTools) {
            $this->pdoTools->setConfig($this->config);
        }

        $this->utils = new Utils($this);
        $this->format = new Format($this);
        $this->services = new Services($this);
        $this->extraFields = new ExtraFields($this->modx);

        // Регистрируем все сервисы MiniShop3 через ServiceRegistry
        // Поддерживается переопределение через конфигурационный файл (ms3.services.php)
        (new ServiceRegistry($this->modx))->register();

        $this->options = new Options($this);

        $this->deleteOldDraft();
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
                        $file = str_replace($config['pl'], $config['vl'], $file);
                        if (preg_match('/\.css$/i', $file)) {
                            $file .= '?v=' . date('dmYHi', filemtime(MODX_BASE_PATH . ltrim($file, '/')));
                            $this->modx->regClientCSS($file);
                        }
                    }
                }
            }

            $registerGlobalConfig = json_decode($this->modx->getOption('ms3_register_global_config', null, true), true);
            if ($registerGlobalConfig) {
                $tokenName = $this->modx->getOption('ms3_token_name', null, 'ms3_token');
                $js_setting = [
                    'actionUrl' => $this->config['actionUrl'],  // api.php для фронтенд API
                    'connectorUrl' => $this->config['connectorUrl'],  // connector.php для админки (если нужен)
                    'ctx' => $ctx,
                    'tokenName' => $tokenName,
                    'render' => [
                        'cart' => []
                    ]
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
     * Магический метод для доступа к сервисам через свойства
     *
     * @param string $name Имя свойства
     * @return mixed
     */
    public function __get(string $name)
    {
        // Доступ к корзине через $ms3->cart
        if ($name === 'cart') {
            return $this->getCart();
        }

        // Доступ к заказу через $ms3->order
        if ($name === 'order') {
            return $this->getOrder();
        }

        // Доступ к покупателю через $ms3->customer
        if ($name === 'customer') {
            return $this->getCustomer();
        }

        // ... другие сервисы (delivery, payment и т.д.)

        return null;
    }

    /**
     * Получение сервиса корзины (ленивая загрузка)
     *
     * @return \MiniShop3\Controllers\Cart\Cart
     */
    public function getCart(): \MiniShop3\Controllers\Cart\Cart
    {
        return $this->modx->services->get('ms3_cart');
    }

    /**
     * Получение сервиса заказа (ленивая загрузка)
     *
     * @return \MiniShop3\Controllers\Order\Order
     */
    public function getOrder(): \MiniShop3\Controllers\Order\Order
    {
        return $this->modx->services->get('ms3_order');
    }

    /**
     * Получение сервиса покупателя (ленивая загрузка)
     *
     * @return \MiniShop3\Controllers\Customer\Customer
     */
    public function getCustomer(): \MiniShop3\Controllers\Customer\Customer
    {
        return $this->modx->services->get('ms3_customer');
    }

    /**
     * Loads additional metadata for miniShop3 objects
     */
    public function loadMap()
    {
        $this->extraFields->loadMap();
        if ($this->pdoTools && method_exists($this->pdoTools, 'makePlaceholders')) {
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

    public function registerSnippet($scriptProperties)
    {
        /** @var \MiniShop3\Services\TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        // Генерируем токен через TokenService (использует безопасный секрет из настроек)
        $token = $tokenService->generateSnippetToken($scriptProperties);

        // Проверяем кеш
        $cachedData = $tokenService->getSnippetData($token);

        if ($cachedData === null) {
            // Кешируем параметры с TTL из системных настроек
            $tokenService->cacheSnippetData($token, $scriptProperties);
        }

        // Формируем данные для JS
        $output = [
            'token' => $token,
        ];

        if (isset($scriptProperties['selector'])) {
            $output['selector'] = $scriptProperties['selector'];
        }

        // Регистрируем в глобальном конфиге для JS
        $this->modx->regClientStartupScript(
            '<script>ms3Config.render.cart.push(' . json_encode($output) . ');</script>',
            true
        );
    }

    //TODO Перенести метод в контроллер заказов  (Или трейт скорее)
    private function deleteOldDraft()
    {
        // Every 30 minutes, run the cleanup for old tasks
        if (date('i') % 30 === 0) {
            $deleteAfter = $this->modx->getOption('ms3_delete_drafts_after', null, '');
            $deleteAfter = !empty($deleteAfter) ? strtotime($deleteAfter) : null;
            if ($deleteAfter) {
                $statusDraft = $this->modx->getOption('ms3_status_draft', null, 1);
                $orders = $this->modx->getIterator(msOrder::class, [
                    'status_id' => $statusDraft,
                    'createdon:<' => date('Y-m-d H:i:00', $deleteAfter)
                ]);
                if (iterator_count($orders) > 0) {
                    foreach ($orders as $order) {
                        $order->remove();
                    }
                }
            }
        }
    }
}
