<?php

namespace MiniShop3\Controllers\Cart;

use MiniShop3\Model\msProduct;

class SessionCart extends Cart implements CartInterface
{
    /**
     * @return array
     */
    public function get()
    {
        $cart = [];
        if (!empty($_SESSION['ms2']['cart'])) {
            $cart = $_SESSION['ms3']['cart'];
        }

        foreach ($cart as $key => $item) {
            if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                $cart[$key] = $item;
            }
        }

        return $cart;
    }

    /**
     * @param array $cart
     */
    public function set($cart = [])
    {
        $_SESSION['ms3']['cart'] = $cart;
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
        $cart = $this->get();
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

        $filter = ['id' => $id, 'class_key' => 'msProduct'];
        if (!$this->config['allow_deleted']) {
            $filter['deleted'] = 0;
        }
        if (!$this->config['allow_unpublished']) {
            $filter['published'] = 1;
        }
        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $filter);
        if (!$product) {
            return $this->error('ms3_cart_add_err_nf', $this->status());
        }

        if ($count > $this->config['max_count'] || $count <= 0) {
            return $this->error('ms3_cart_add_err_count', $this->status(), ['count' => $count]);
        }

        /* You can prevent add of product to cart by adding some text to $modx->event->_output
        <?php
                if ($modx->event->name = 'msOnBeforeAddToCart') {
                    $modx->event->output('Error');
                }

        // Also you can modify $count and $options variables by add values to $this->modx->event->returnedValues
            <?php
                if ($modx->event->name = 'msOnBeforeAddToCart') {
                    $values = & $modx->event->returnedValues;
                    $values['count'] = $count + 10;
                    $values['options'] = array('size' => '99');
                }
        */

        $eventParams = [
            'product' => $product,
            'count' => $count,
            'options' => $options,
            'cart' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeAddToCart', $eventParams);
        if (!($response['success'])) {
            return $this->error($response['message']);
        }
        $price = $product->getPrice();
        $oldPrice = $product->get('old_price');
        $weight = $product->getWeight();
        $count = $response['data']['count'];
        $options = $response['data']['options'];
        $discount_price = $oldPrice > 0 ? $oldPrice - $price : 0;
        $discount_cost = $discount_price * $count;

        $key = md5($id . $price . $weight . (json_encode($options)));
        if (array_key_exists($key, $cart)) {
            return $this->change($key, $cart[$key]['count'] + $count);
        }

        $ctx_key = 'web';
        $ms3_cart_context = (bool)$this->modx->getOption('ms3_cart_context', null, '0', true);
        if (!$ms3_cart_context) {
            $ctx_key = $this->ctx;
        }

        $cartItem = [
            'id' => $id,
            'price' => $price,
            'old_price' => $oldPrice,
            'discount_price' => $discount_price,
            'discount_cost' => $discount_cost,
            'weight' => $weight,
            'count' => $count,
            'options' => $options,
            'ctx' => $ctx_key,
        ];

        $_SESSION['ms3']['cart'][$key] = $cartItem;

        $eventParams = [
            'key' => $key,
            'cart' => $this
        ];
        $response = $this->ms3->utils->invokeEvent('msOnAddToCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $status = $this->status(['key' => $key]);
        return $this->success(
            'ms3_cart_add_success',
            $status,
            ['count' => $count]
        );
    }

    /**
     * @param string $key
     * @param int $count
     *
     * @return array|string
     */
    public function change($key, $count)
    {
        $cart = $this->get();
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

        $eventParams = [
            'key' => $key,
            'count' => $count,
            'cart' => $this
        ];
        $response = $this->invokeEvent('msOnBeforeChangeInCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $count = $response['data']['count'];
        $_SESSION['ms3']['cart'][$key]['count'] = $count;
        $response = $this->invokeEvent(
            'msOnChangeInCart',
            ['key' => $key, 'count' => $count, 'cart' => $this]
        );
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $status['key'] = $key;
        $status['cost'] = $count * $cart[$key]['price'];

        return $this->success(
            'ms3_cart_change_success',
            $this->status($status),
            ['count' => $count]
        );
    }

    /**
     * @param string $key
     *
     * @return array|string
     */
    public function remove($key)
    {
        $cart = $this->get();
        if (!array_key_exists($key, $cart)) {
            return $this->error('ms3_cart_remove_error');
        }
        $eventParams = [
            'key' => $key,
            'cart' => $this
        ];
        $response = $this->invokeEvent('msOnBeforeRemoveFromCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        unset($_SESSION['ms3']['cart'][$key]);

        $response = $this->invokeEvent('msOnRemoveFromCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success('ms3_cart_remove_success', $this->status());
    }

    /**
     * @return array|string
     */
    public function clean()
    {
        $eventParams = ['cart' => $this];
        $response = $this->invokeEvent('msOnBeforeEmptyCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        foreach ($_SESSION['ms3']['cart'] as $key => $item) {
            if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                unset($_SESSION['ms3']['cart'][$key]);
            }
        }

        $response = $this->invokeEvent('msOnEmptyCart', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success('ms3_cart_clean_success', $this->status());
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function status($data = [])
    {
        $cart = $this->get();
        $status = [
            'total_count' => 0,
            'total_cost' => 0,
            'total_weight' => 0,
            'total_discount' => 0,
            'total_positions' => count($cart),
        ];
        foreach ($cart as $item) {
            if (empty($item['ctx']) || $item['ctx'] == $this->ctx) {
                $status['total_count'] += $item['count'];
                $status['total_cost'] += $item['price'] * $item['count'];
                $status['total_weight'] += $item['weight'] * $item['count'];
                $status['total_discount'] += $item['discount_price'] * $item['count'];
            }
        }

        $status = array_merge($data, $status);

        $eventParams = [
            'status' => $status,
            'cart' => $this,
        ];
        $response = $this->invokeEvent('msOnGetStatusCart', $eventParams);
        if ($response['success']) {
            $status = $response['data']['status'];
        }

        return $status;
    }
}
