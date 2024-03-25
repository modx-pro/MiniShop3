<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;
use MiniShop3\Controllers\Storage\DB\DBCart;

class Cart
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    /** @var array $cart */
    protected $cart = null;
    protected $ctx = 'web';
    /** @var msOrder $draft */
    protected $draft;
    protected $token = '';
    protected $storage;

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

        $this->config = array_merge([
            'max_count' => $this->modx->getOption('ms3_cart_max_count', null, 1000, true),
            'allow_deleted' => false,
            'allow_unpublished' => false,
            'cart_product_key_fields' => $this->modx->getOption(
                'ms3_cart_product_key_fields',
                null,
                'id,options',
                true
            ),
        ], $config);

        $this->modx->lexicon->load('minishop3:cart');
        $this->storage = new DBCart($this->modx, $this->ms3);
    }

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($ctx = 'web', $token = '')
    {
        return $this->storage->initialize($ctx, $token, $this->config);
    }

    /**
     * @return array
     */
    public function get()
    {
        return $this->storage->get();
    }

    /**
     * @param int $id
     * @param int $count
     * @param array $options
     *
     * @return array|string
     */
    public function add($id, $count = 1, $options = [])
    {
        return $this->storage->add($id, $count, $options);
    }

    public function change($product_key, $count)
    {
        return $this->storage->change($product_key, $count);
    }

    public function changeOption($product_key, $options)
    {
        return $this->storage->changeOption($product_key, $options);
    }

    public function remove($product_key)
    {
        return $this->storage->remove($product_key);
    }

    public function clean()
    {
        return $this->storage->clean();
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function status($data = [])
    {
        return $this->storage->status($data);
    }

    /**
     * Generate cart product key
     *
     * @param array $product
     * @param array $options
     * @return string
     *
     */
    public function getProductKey(array $product, array $options = [])
    {
        return $this->storage->getProductKey($product, $options);
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
