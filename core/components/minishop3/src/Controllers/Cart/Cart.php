<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
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
    }

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($ctx = 'web')
    {
        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        if ($ms3_cart_context) {
            $ctx = 'web';
        }
        $this->ctx = $ctx;
        return true;
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
        if (empty($_SERVER['HTTP_MS3TOKEN'])) {
            return $this->error('ms3_err_token');
        }
        /** @var msOrder $draft */
        $draft = $this->getDraft($_SERVER['HTTP_MS3TOKEN']);
        if (empty($draft)) {
            $draft = $this->newDraft($_SERVER['HTTP_MS3TOKEN']);
        }
        $cart = $this->loadCart($draft);
        if (empty($id) || !is_numeric($id)) {
            return $this->error('ms3_cart_add_err_id');
        }
        $count = intval($count);
        if (is_string($options)) {
            $options = json_decode($options, true);
        }
        if (!is_array($options)) {
            $options = [];
        }

        $filter = ['id' => $id, 'class_key' => msProduct::class];
        if (!$this->config['allow_deleted']) {
            $filter['deleted'] = 0;
        }
        if (!$this->config['allow_unpublished']) {
            $filter['published'] = 1;
        }
        /** @var msProduct $msProduct */
        $msProduct = $this->modx->getObject(msProduct::class, $filter);
        if (!$msProduct) {
            //return $this->error('ms3_cart_add_err_nf', $this->status());
        }

        if ($count > $this->config['max_count'] || $count <= 0) {
            //return $this->error('ms3_cart_add_err_count', $this->status(), ['count' => $count]);
        }

        $response = $this->invokeEvent('msOnBeforeAddToCart', [
            'msProduct' => $msProduct,
            'count' => $count,
            'options' => $options,
            'cart' => $this,
        ]);
        if (!($response['success'])) {
            return $this->error($response['message']);
        }
        $price = $msProduct->getPrice();
        $oldPrice = $msProduct->get('old_price');
        $weight = $msProduct->getWeight();
        $count = $response['data']['count'];
        $options = $response['data']['options'];
        $discount_price = $oldPrice > 0 ? $oldPrice - $price : 0;
        $discount_cost = $discount_price * $count;
        $key = $this->getProductKey($msProduct->toArray(), $options);

        if (array_key_exists($key, $cart)) {
            return $this->change($key, $cart[$key]['count'] + $count);
        }
        $ctx_key = 'web';
        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        if (!$ms3_cart_context) {
            $ctx_key = $this->ctx;
        }

        $properties = [
            'old_price' => $oldPrice,
            'discount_price' => $discount_price,
            'discount_cost' => $discount_cost,
        ];

        // Adding products
        $products = [];
        /** @var msOrderProduct $msOrderProduct */
        $msOrderProduct = $this->modx->newObject(msOrderProduct::class);

        $productData = [
            'product_id' => $id,
            'product_key' => $key,
            'name' => $msProduct->get('pagetitle'),
            'count' => $count,
            'price' => $price,
            'weight' => $weight,
            'cost' => $price * $count,
            'options' => $options,
            'properties' => $properties
        ];
        $msOrderProduct->fromArray($productData);

        $products[] = $msOrderProduct;
        $draft->addMany($products);
        $draft->save();

        $this->restrictDraft($draft);

        $response = $this->invokeEvent('msOnAddToCart', ['key' => $key, 'cart' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success(
            'ms3_cart_add_success',
            $this->status([
                'key' => $key,
                'cart' => $cart,
                'row' => $cart[$key]
            ]),
            ['count' => $count]
        );
    }

    public function change($key, $count)
    {
        if (empty($_SERVER['HTTP_MS3TOKEN'])) {
            return $this->error('ms3_err_token');
        }
        /** @var msOrder $draft */
        $draft = $this->getDraft($_SERVER['HTTP_MS3TOKEN']);
        if (empty($draft)) {
            $draft = $this->newDraft($_SERVER['HTTP_MS3TOKEN']);
        }

        $cart = $this->loadCart($draft);
        $status = [];
        if (!array_key_exists($key, $cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        if ($count <= 0) {
            return $this->remove($key);
        }

        if ($count > $this->config['max_count']) {
            return $this->error('ms3_cart_add_err_count', $this->status(), ['count' => $count]);
        }

        $response = $this->invokeEvent(
            'msOnBeforeChangeInCart',
            ['key' => $key, 'count' => $count, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        foreach ($draft->getMany('Products') as $product) {
            if ($key === $product->get('product_key')) {
                $price = $product->get('price');
                $product->set('count', $count);
                $product->set('cost', $price * $count);
                $product->save();
            }
        }
        $draft->save();
        $this->restrictDraft($draft);

        $response = $this->invokeEvent(
            'msOnChangeInCart',
            ['key' => $key, 'count' => $count, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $status['key'] = $key;
        $status['cost'] = $count * $cart[$key]['price'];
        $status['cart'] = $cart;
        $status['row'] = $cart[$key];

        return $this->success(
            'ms3_cart_change_success',
            $this->status($status),
            ['count' => $count]
        );
    }

    public function remove($key)
    {
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function status($data = [])
    {
        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count([]),
        ];
        foreach ($this->cart as $item) {
            if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                $status['total_count'] += $item['count'];
                $status['total_cost'] += $item['price'] * $item['count'];
                $status['total_weight'] += $item['weight'] * $item['count'];
                $status['total_discount'] += $item['discount_price'] * $item['count'];
            }
        }

        $status = array_merge($data, $status);

        $response = $this->invokeEvent('msOnGetStatusCart', [
            'status' => $status,
            'cart' => $this,
        ]);
        if ($response['success']) {
            $status = $response['data']['status'];
        }

        return $status;
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
        $key_fields = explode(',', $this->config['cart_product_key_fields']);
        $product['options'] = $options;
        $key = '';

        foreach ($key_fields as $key_field) {
            if (isset($product[$key_field])) {
                if (is_array($product[$key_field])) {
                    $key .= json_encode($product[$key_field]);
                } else {
                    $key .= $product[$key_field];
                }
            }
        }

        return 'ms' . md5($key);
    }

    protected function getDraft($token)
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);
        $where = [
            'token' => $token,
            'status_id' => $status_draft,
            'context' => $this->ctx
        ];
        return $this->modx->getObject(msOrder::class, $where);
    }

    protected function newDraft($token)
    {
        $status_draft = $this->modx->getOption('ms3_status_draft', null, 1);
        $msOrder = $this->modx->newObject(msOrder::class);
        $data = [
            'token' => $token,
            'status_id' => $status_draft,
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($this->ctx),
        ];
        $msOrder->fromArray($data);

        //TODO Событие перед созданием черновика
        //TODO Запись в лог msOrderLog
        $save = $msOrder->save();
        if ($save) {
            $msOrderAddress = $this->modx->newObject(msOrderAddress::class);
            $msOrderAddress->fromArray([
                'createdon' => time(),
                'user_id' => $this->modx->getLoginUserID($this->ctx),
                'order_id' => $msOrder->get('id')
            ]);
            $msOrderAddress->save();
            //TODO Событие по факту созданием черновика
        }

        return $msOrder;
    }

    /**
     * @param msOrder $draft
     * @return []
     */
    protected function loadCart($draft)
    {
        $output = [];
        //TODO Оптимизировать через newQUery
        $orderProducts = $draft->getMany('Products');
        if (empty($orderProducts)) {
            return $output;
        }

        foreach ($orderProducts as $item) {
            $output[$item->get('product_key')] = $item->toArray();
        }

        return $output;
    }

    protected function restrictDraft($draft)
    {
        //TODO событие до перерасчета заказа
        $products = $draft->getMany('Products');
        $cart_cost = 0;
        $weight = 0;
        if (!empty($products)) {
            foreach ($products as $product) {
                $weight += $product->get('weight');
                $cart_cost += $product->get('cost');
            }
        }

        $delivery_cost = $draft->get('delivery_cost');
        $cost = $cart_cost + $delivery_cost;

        //TODO событие перерасчета заказа
        $draft->set('cart_cost', $cart_cost);
        $draft->set('cost', $cost);
        $draft->set('weight', $weight);
        $draft->save();
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
