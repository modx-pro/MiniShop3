<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MODX\Revolution\modX;

class Cart
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    /** @var array $cart */
    protected $cart;
    protected $ctx = 'web';
    protected $storage = 'session';
    protected $storageHandler;

    /**
     * Cart constructor.
     *
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->storage = $this->modx->getOption('ms_tmp_storage', null, 'session');
        $this->storageInit();

        $this->config = array_merge([
            'max_count' => $this->modx->getOption('ms_cart_max_count', null, 1000, true),
            'allow_deleted' => false,
            'allow_unpublished' => false,
        ], $config);
        
        $this->modx->lexicon->load('minishop3:cart');
    }

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($ctx = 'web')
    {
        $ms2_cart_context = (bool)$this->modx->getOption('ms_cart_context', null, '0', true);
        if ($ms2_cart_context) {
            $ctx = 'web';
        }
        $this->ctx = $ctx;
        $this->storageHandler->setContext($this->ctx);
        return true;
    }

    /**
     * Set controller for Cart
     */
    protected function storageInit()
    {
        switch ($this->storage) {
            case 'session':
                $this->storageHandler = new SessionCart($this->ms3, $this->ms3->config);
                break;
            case 'db':
                //$this->storageHandler = new CartDB($this->modx, $this->ms3);
                break;
        }
    }

    /**
     * Shorthand for MS3 success method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function success(string $message = '', array $data = [], array $placeholders = [])
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 error method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function error(string $message = '', array $data = [], array $placeholders = [])
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand for MS3 invokeEvent method
     *
     * @param string $eventName
     * @param array $params
     *
     * @return array|string
     */
    protected function invokeEvent(string $eventName, array $params = [])
    {
        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
