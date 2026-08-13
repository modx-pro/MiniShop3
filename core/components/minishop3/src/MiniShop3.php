<?php

namespace MiniShop3;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\Controllers\Delivery\Delivery;
use MiniShop3\Controllers\Options\Options;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\ServiceRegistry;
use MiniShop3\Utils\ExtraFields;
use MiniShop3\Utils\Format;
use MiniShop3\Utils\Services;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use ModxPro\PdoTools\CoreTools;
use ModxPro\PdoTools\Fetch;
use xPDO\xPDO;

class MiniShop3
{
    public $version = '1.13.0-beta1';

    /** @var modX $modx */
    public $modx;
    /** @var Fetch $pdoFetch */
    public $pdoFetch;
    /** @var CoreTools $pdoTools */
    public $pdoTools;


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

    /** @var ExtraFields $extraFields */
    public $extraFields;

    /** @var Options $options */
    public $options;

    /** @var bool $mapLoaded Флаг загрузки ExtraFields в xPDO map */
    private bool $mapLoaded = false;

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

        (new ServiceRegistry($this->modx))->register();

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
                    'actionUrl' => $this->config['actionUrl'],
                    'connectorUrl' => $this->config['connectorUrl'],
                    'ctx' => $ctx,
                    'tokenName' => $tokenName,
                    'currencySymbol' => $this->format->getCurrencySymbol(),
                    'currencyPosition' => $this->modx->getOption('ms3_currency_position', null, 'after'),
                    'render' => [
                        'cart' => []
                    ],
                    'selectors' => (object)[]
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
     * Magic method to access services via properties
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'cart') {
            return $this->getCart();
        }

        if ($name === 'order') {
            return $this->getOrder();
        }

        if ($name === 'customer') {
            return $this->getCustomer();
        }

        return null;
    }

    /**
     * Get cart service (lazy loading)
     *
     * @return \MiniShop3\Controllers\Cart\Cart
     */
    public function getCart(): \MiniShop3\Controllers\Cart\Cart
    {
        return $this->modx->services->get('ms3_cart');
    }

    /**
     * Get order service (lazy loading)
     *
     * @return \MiniShop3\Controllers\Order\Order
     */
    public function getOrder(): \MiniShop3\Controllers\Order\Order
    {
        return $this->modx->services->get('ms3_order');
    }

    /**
     * Get customer service (lazy loading)
     *
     * @return \MiniShop3\Controllers\Customer\Customer
     */
    public function getCustomer(): \MiniShop3\Controllers\Customer\Customer
    {
        return $this->modx->services->get('ms3_customer');
    }

    /**
     * Loads extra fields metadata into xPDO map.
     * Idempotent - safe to call multiple times, loads only once per request.
     *
     * @return void
     */
    public function loadMap(): void
    {
        if ($this->mapLoaded) {
            return;
        }

        $this->extraFields->loadMap();
        $this->mapLoaded = true;
    }

    public function registerSnippet($scriptProperties, string $snippetName = 'msCart')
    {
        /** @var \MiniShop3\Services\TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        $token = $tokenService->generateSnippetToken($scriptProperties);
        $cachedData = $tokenService->getSnippetData($token);

        if ($cachedData === null) {
            $cacheData = $scriptProperties;
            $cacheData['_snippetName'] = $snippetName;
            $tokenService->cacheSnippetData($token, $cacheData);
        }

        $output = [
            'token' => $token,
        ];

        if (isset($scriptProperties['selector'])) {
            $output['selector'] = $scriptProperties['selector'];
        }

        $this->modx->regClientStartupScript(
            '<script>ms3Config.render.cart.push(' . json_encode($output) . ');</script>',
            true
        );
    }
}
