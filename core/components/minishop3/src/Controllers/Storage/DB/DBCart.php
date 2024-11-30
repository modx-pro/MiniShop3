<?php

namespace MiniShop3\Controllers\Storage\DB;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use xPDO\xPDO;

class DBCart extends DBStorage
{
    public $cart;
    protected $config;

    /**
     * @param string $ctx
     *
     * @return bool
     */
    public function initialize($ctx = 'web', $token = '', $config = [])
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
        $this->config = $config;
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
        $this->draft = $this->getDraft($this->token);
        $this->cart = $this->loadCart($this->draft);
        $response = $this->invokeEvent('msOnBeforeGetCart', [
            'draft' => $this->draft,
            'controller' => $this,
        ]);
        if (!($response['success'])) {
            return $this->error($response['message']);
        }
        $this->cart = $this->getCart();

        $response = $this->invokeEvent('msOnGetCart', [
            'draft' => $this->draft,
            'data' => $this->cart,
            'controller' => $this
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
        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;
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
        $this->cart = $this->loadCart($this->draft);
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
            'controller' => $this,
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

        $response = $this->invokeEvent('msOnAddToCart', [
            'msProduct' => $msProduct,
            'count' => $count,
            'options' => $options,
            'controller' => $this,
        ]);
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
        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;

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
        $this->cart = $this->loadCart($this->draft);
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
            [
                'product_key' => $product_key,
                'count' => $count,
                'controller' => $this
            ]
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
            [
                'product_key' => $product_key,
                'count' => $count,
                'controller' => $this
            ]
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
        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;

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
        $this->cart = $this->loadCart($this->draft);
        $status = [];
        if (!array_key_exists($product_key, $this->cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        if (empty($options)) {
            return $this->error('ms3_cart_change_options_error', $this->status($status));
        }

        $response = $this->invokeEvent(
            'msOnBeforeChangeOptionsInCart',
            [
                'product_key' => $product_key,
                'options' => $options,
                'controller' => $this
            ]
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
            [
                'product_key' => $product_key,
                'options' => $options,
                'controller' => $this
            ]
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
        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;

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
        $this->cart = $this->loadCart($this->draft);
        $status = [];
        if (!array_key_exists($product_key, $this->cart)) {
            return $this->error('ms3_cart_change_error', $this->status($status));
        }

        $response = $this->ms3->utils->invokeEvent(
            'msOnBeforeRemoveFromCart',
            [
                'product_key' => $product_key,
                'controller' => $this
            ]
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
            [
                'product_key' => $product_key,
                'controller' => $this
            ]
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
        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;

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
        $this->cart = $this->loadCart($this->draft);

        $response = $this->ms3->utils->invokeEvent('msOnBeforeEmptyCart', ['controller' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        if ($this->draft) {
            $this->draft->remove();
        }

        $this->cart = [];

        $response = $this->ms3->utils->invokeEvent('msOnEmptyCart', ['controller' => $this]);
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

        $response = $this->status();
        $status = [];
        if ($response['success']) {
            $status = $response['data'];
        }
        $data['status'] = $status;

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
    public function status(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        if ($this->cart === null) {
            $this->initDraft();
            $this->cart = $this->loadCart($this->draft);
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
                    $status['total_discount'] += $item['properties']['discount_price'] * $item['count'];
                }
            }
        }

        $status = array_merge($data, $status);

        $response = $this->invokeEvent('msOnGetStatusCart', [
            'status' => $status,
            'controller' => $this,
        ]);
        if ($response['success']) {
            $status = $response['data']['status'];
        }

        return $this->success(
            'ms3_cart_status_success',
            $status
        );
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

    /**
     * @param msOrder $draft
     * @return array
     */
    public function loadCart($draft)
    {
        $output = [];
        if (empty($draft)) {
            return $output;
        }
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
