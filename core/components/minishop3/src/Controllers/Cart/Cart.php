<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use xPDO\xPDO;

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
    protected $token = '';
    protected $draft;

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
    public function initialize($ctx = 'web', $token = '')
    {
        if (empty($token)) {
            return false;
        }
        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        if ($ms3_cart_context) {
            $ctx = 'web';
        }
        $this->ctx = $ctx;
        $this->token = $token;
        return true;
    }

    /**
     * @return bool
     */
    public function initDraft()
    {
        if (empty($this->token)) {
            return false;
        }
        $this->draft = $this->getDraft($this->token);
        if (empty($this->draft)) {
            $this->draft = $this->newDraft($this->token);
        }
        $this->cart = $this->loadCart($this->draft);
        return true;
    }

    /**
     * @return array
     */
    public function get()
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        $response = $this->invokeEvent('msOnBeforeGetCart', [
            'draft' => $this->draft,
            'cart' => $this,
        ]);
        if (!($response['success'])) {
            return $this->error($response['message']);
        }
        $this->cart = $this->getCart();

        $response = $this->invokeEvent('msOnGetCart', [
            'draft' => $this->draft,
            'data' => $this->cart,
            'cart' => $this
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $this->cart = $response['data']['data'];

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['cart'] = $this->cart;
        $data['status'] = $this->status();
        return $this->success(
            'ms3_cart_get_success',
            $data
        );
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
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
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
            return $this->error('ms3_cart_add_err_nf', $this->status());
        }

        if ($count > $this->config['max_count'] || $count <= 0) {
            return $this->error('ms3_cart_add_err_count', $this->status(), ['count' => $count]);
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
        $product_key = $this->getProductKey($msProduct->toArray(), $options);
        if (array_key_exists($product_key, $this->cart)) {
            return $this->change($product_key, $this->cart[$product_key]['count'] + $count);
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
            'product_key' => $product_key,
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
        $this->draft->addMany($products);
        $this->draft->save();

        $this->restrictDraft($this->draft);
        $this->cart = $this->getCart();

        $response = $this->invokeEvent('msOnAddToCart', ['key' => $product_key, 'cart' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['last_key'] = $product_key;
        $data['cart'] = $this->cart;
        $data['status'] = $this->status();

        return $this->success(
            'ms3_cart_add_success',
            $data,
            ['count' => $count]
        );
    }

    public function change($product_key, $count)
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        $status = [];
        if (!array_key_exists($product_key, $this->cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        if ($count <= 0) {
            return $this->remove($product_key);
        }

        if ($count > $this->config['max_count']) {
            return $this->error('ms3_cart_add_err_count', $this->status(), ['count' => $count]);
        }

        $response = $this->invokeEvent(
            'msOnBeforeChangeInCart',
            ['product_key' => $product_key, 'count' => $count, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        /** @var msOrderProduct $product */
        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $price = $product->get('price');
                $product->set('count', $count);
                $product->set('cost', $price * $count);
                $product->save();
                break;
            }
        }
        $this->draft->save();
        $this->restrictDraft($this->draft);
        $this->loadCart($this->draft);
        $this->cart = $this->getCart();

        $response = $this->invokeEvent(
            'msOnChangeInCart',
            ['product_key' => $product_key, 'count' => $count, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['last_key'] = $product_key;
        $data['cart'] = $this->cart;
        $data['status'] = $this->status();

        return $this->success(
            'ms3_cart_change_success',
            $data,
            ['count' => $count]
        );
    }

    public function changeOption($product_key, $options)
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        $status = [];
        if (!array_key_exists($product_key, $this->cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        if (empty($options)) {
            return $this->error('ms3_cart_change_options_error', $this->status($status));
        }

        $response = $this->invokeEvent(
            'msOnBeforeChangeOptionsInCart',
            ['product_key' => $product_key, 'options' => $options, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $count = $response['data']['count'];

        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $orderProductOptions = $product->get('options');
                $count = $product->get('count');

                foreach ($options as $key => $value) {
                    if (!empty($value)) {
                        $orderProductOptions[$key] = $value;
                    } else {
                        unset($orderProductOptions[$key]);
                    }
                }

                $product_key = $this->getProductKey($product->Product->toArray(), $orderProductOptions);

                if (array_key_exists($product_key, $this->cart)) {
                    $product->remove();
                    return $this->change($product_key, $this->cart[$product_key]['count'] + $count);
                }

                $product->set('product_key', $product_key);
                $product->set('options', $orderProductOptions);
                $product->save();

                break;
            }
        }

        $this->draft->save();
        $this->restrictDraft($this->draft);
        $this->cart = $this->getCart();

        //TODO добавить старый ключ, новый ключ ?
        $response = $this->invokeEvent(
            'msOnChangeOptionInCart',
            ['product_key' => $product_key, 'options' => $options, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['last_key'] = $product_key;
        $data['cart'] = $this->cart;
        $data['status'] = $this->status();

        return $this->success(
            'ms3_cart_change_success',
            $data,
            ['count' => $count]
        );
    }

    public function remove($product_key)
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();
        $status = [];
        if (!array_key_exists($product_key, $this->cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        $response = $this->ms3->utils->invokeEvent(
            'msOnBeforeRemoveFromCart',
            ['product_key' => $product_key, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        foreach ($this->draft->getMany('Products') as $product) {
            if ($product_key === $product->get('product_key')) {
                $product->remove();
                break;
            }
        }

        $count = $this->modx->getCount(msOrderProduct::class, ['order_id' => $this->draft->get('id')]);
        if ($count === 0) {
            $this->draft->remove();
        } else {
            $this->draft->save();
            $this->restrictDraft($this->draft);
        }
        $this->cart = $this->getCart();

        $response = $this->ms3->utils->invokeEvent(
            'msOnRemoveFromCart',
            ['product_key' => $product_key, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['last_key'] = $product_key;
        $data['cart'] = $this->cart;
        $data['status'] = $this->status();

        return $this->success(
            'ms3_cart_remove_success',
            $data,
            ['count' => $count]
        );
    }

    public function clean()
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        $response = $this->ms3->utils->invokeEvent('msOnBeforeEmptyCart', ['cart' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        if ($this->draft) {
            $this->draft->remove();
        }

        $this->cart = [];

        $response = $this->ms3->utils->invokeEvent('msOnEmptyCart', ['cart' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $data = [];

        if (!empty($_POST['render'])) {
            $renderItems = json_decode($_POST['render'], true);
            if (is_array($renderItems) && !empty($renderItems['cart'])) {
                foreach ($renderItems['cart'] as $item) {
                    $data['render']['cart'][$item['token']]['render'] = $this->render($item['token']);
                    $data['render']['cart'][$item['token']]['selector'] = $item['selector'];
                }
            }
        }

        $data['status'] = $this->status();

        return $this->success(
            'ms3_cart_clean_success',
            $data
        );
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function status($data = [])
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        if ($this->cart === null) {
            $this->initDraft();
        }

        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count($this->cart),
        ];
        if (!empty($this->cart)) {
            foreach ($this->cart as $item) {
                if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                    $status['total_count'] += $item['count'];
                    $status['total_cost'] += $item['price'] * $item['count'];
                    $status['total_weight'] += $item['weight'] * $item['count'];
                    $status['total_discount'] += $item['discount_price'] * $item['count'];
                }
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
        /** @var msOrder $msOrder */
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
    public function loadCart($draft)
    {
        $output = [];
        //TODO Оптимизировать через newQUery
        $orderProducts = $draft->getMany('Products');
        if (empty($orderProducts)) {
            return $output;
        }

        /** @var msOrderProduct $item */
        foreach ($orderProducts as $item) {
            $output[$item->get('product_key')] = $item->toArray();
        }

        return $output;
    }

    protected function getCart()
    {
        $cart = [];
        foreach ($this->cart as $key => $item) {
            if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                $cart[$key] = $item;
            }
        }

        return $cart;
    }

    public function restrictDraft($draft)
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
        $draft->set('updatedon', time());
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

    protected function render($token)
    {
        $properties = $this->modx->cacheManager->get($token, [xPDO::OPT_CACHE_KEY => 'ms3/msCart']);
        if ($properties) {
            //TODO Текущий контекст
            $this->modx->context->key = 'web';
            // TODO запуск сниппета, изначально указанного при вызове
            $snippet = !empty($properties['runSnippet']) ? $properties['runSnippet'] : 'msCart';
            return $this->ms3->pdoTools->runSnippet($snippet, $properties);
        }
        return '';
    }
}
