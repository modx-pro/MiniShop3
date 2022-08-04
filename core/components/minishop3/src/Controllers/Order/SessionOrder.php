<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msProduct;

class SessionOrder extends Order implements OrderInterface
{
    /**
     * @return array
     */
    public function get()
    {
        return $_SESSION['ms3']['order'];
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return array|string
     */
    public function add($key, $value)
    {
        $order =& $_SESSION['ms3']['order'];
        $eventParams = [
            'key' => $key,
            'value' => $value,
            'order' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeAddToOrder', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $value = $response['data']['value'];

        if (empty($value)) {
            $validated = '';
            unset($order[$key]);
        } else {
            $validated = $this->validate($key, $value);
            if ($validated !== false) {
                $order[$key] = $validated;
                $eventParams = [
                    'key' => $key,
                    'value' => $validated,
                    'order' => $this,
                ];
                $response = $this->invokeEvent('msOnAddToOrder', $eventParams);
                if (!$response['success']) {
                    return $this->error($response['message']);
                }
                $validated = $response['data']['value'];
            } else {
                $order[$key] = $value;
            }
        }

        return ($validated === false)
            ? $this->error('', [$key => $value])
            : $this->success('', [$key => $validated]);
    }

    /**
     * @param string $key
     *
     * @return array|bool|string
     */
    public function remove($key)
    {
        $order =& $_SESSION['ms3']['order'];
        $exists = array_key_exists($key, $order);
        if ($exists) {
            $eventParams = [
                'key' => $key,
                'order' => $this,
            ];
            $response = $this->invokeEvent('msOnBeforeRemoveFromOrder', $eventParams);
            if (!$response['success']) {
                return $this->error($response['message']);
            }

            unset($order[$key]);

            $response = $this->invokeEvent('msOnRemoveFromOrder', $eventParams);
            if (!$response['success']) {
                return $this->error($response['message']);
            }
        }

        return $exists;
    }


    /**
     * @param array $order
     *
     * @return array
     */
    public function set(array $order)
    {
        $order =& $_SESSION['ms3']['order'];
        foreach ($order as $key => $value) {
            $this->add($key, $value);
        }

        return $this->get();
    }

    /**
     * @param array $data
     *
     * @return array|string
     */
    public function submit($data = [])
    {
        $order =& $_SESSION['ms3']['order'];
        $eventParams = [
            'data' => $data,
            'order' => $this,
        ];
        $response = $this->invokeEvent('msOnSubmitOrder', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        if (!empty($response['data']['data'])) {
            $this->set($response['data']['data']);
        }

        $response = $this->getDeliveryRequiresFields();
        if ($this->ms3->config['json_response']) {
            $response = json_decode($response, true);
        }
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $requires = $response['data']['requires'];

        $errors = [];
        foreach ($requires as $v) {
            if (!empty($v) && empty($order[$v])) {
                $errors[] = $v;
            }
        }
        if (!empty($errors)) {
            return $this->error('ms_order_err_requires', $errors);
        }

        $customer = new Customer($this->ms3);
        $user_id = $customer->getId();
        if (empty($user_id) || !is_int($user_id)) {
            return $this->error(is_string($user_id) ? $user_id : 'ms_err_user_nf');
        }

        $cart_status = $this->ms3->cart->status();
        if (empty($cart_status['total_count'])) {
            return $this->error('ms_order_err_empty');
        }

        $delivery_cost = $this->getCost(false, true);
        $cart_cost = $this->getCost(true, true) - $delivery_cost;
        $num = $this->getNum();

        /** @var msOrder $msOrder */
        $order = $this->get();
        $createdon = date('Y-m-d H:i:s');
        /** @var msOrder $msOrder */
        $msOrder = $this->modx->newObject(msOrder::class);

        $orderData = array_merge($order, $data, [
            'createdon' => $createdon,
            'weight' => $data['cart_status']['total_weight'],
            'cost' => $data['cart_cost'] + $data['delivery_cost'],
            'status' => 0,
            'context' => $this->ctx,
        ]);
        $msOrder->fromArray($orderData);

        // Adding address
        /** @var msOrderAddress $address */
        $address = $this->modx->newObject(msOrderAddress::class);
        $address->fromArray(array_merge($order, array(
            'user_id' => $data['user_id'],
            'createdon' => $createdon,
        )));
        $msOrder->addOne($address);

        // Adding products
        $cart = $this->ms3->cart->get();
        $products = array();
        foreach ($cart as $v) {
            if ($tmp = $this->modx->getObject(msProduct::class, array('id' => $v['id']))) {
                $name = $tmp->get('pagetitle');
            } else {
                $name = '';
            }
            /** @var msOrderProduct $product */
            $product = $this->modx->newObject(msOrderProduct::class);
            $product->fromArray(array_merge($v, array(
                'product_id' => $v['id'],
                'name' => $name,
                'cost' => $v['price'] * $v['count'],
            )));
            $products[] = $product;
        }
        $msOrder->addMany($products);

        $eventParams = [
            'msOrder' => $msOrder,
            'order' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeCreateOrder', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        if ($msOrder->save()) {
            $response = $this->invokeEvent('msOnCreateOrder', $eventParams);
            if (!$response['success']) {
                return $this->error($response['message']);
            }

            if ($this->storage === 'session') {
                $this->ms3->cart->clean();
                $this->clean();
            }
            if (empty($_SESSION['ms3']['orders'])) {
                $_SESSION['ms3']['orders'] = [];
            }
            $_SESSION['ms3']['orders'][] = $msOrder->get('id');

            // Trying to set status "new"
            $orderStatus = new OrderStatus($this->ms3);
            $response = $orderStatus->change($msOrder->get('id'), 1);
            if ($response !== true) {
                return $this->error($response, ['msorder' => $msOrder->get('id')]);
            }

            // Reload order object after changes in changeOrderStatus method
            /** @var msOrder $msOrder */
            $msOrder = $this->modx->getObject(msOrder::class, ['id' => $msOrder->get('id')]);

            /** @var msPayment $payment */
            $payment = $this->modx->getObject(
                msPayment::class,
                ['id' => $msOrder->get('payment'), 'active' => 1]
            );
            if (!$payment) {
                if ($this->config['json_response']) {
                    return $this->success('', ['msorder' => $msOrder->get('id')]);
                }
                $redirect = $this->modx->context->makeUrl(
                    $this->modx->resource->id,
                    ['msorder' => $msOrder->get('id')]
                );
                $this->modx->sendRedirect($redirect);
            }
            $response = $payment->send($msOrder);
            if ($this->config['json_response']) {
                @session_write_close();
                echo is_array($response) ? json_encode($response) : $response;
                die();
            }
            if (!empty($response['data']['redirect'])) {
                $this->modx->sendRedirect($response['data']['redirect']);
            }
            if (!empty($response['data']['msorder'])) {
                $redirect = $this->modx->context->makeUrl(
                    $this->modx->resource->id,
                    ['msorder' => $response['data']['msorder']]
                );
                $this->modx->sendRedirect($redirect);
            }
            $this->modx->sendRedirect($this->modx->context->makeUrl($this->modx->resource->id));
        }

        return $this->error();
    }

    /**
     * @return array|string
     */
    public function clean()
    {
        $response = $this->invokeEvent('msOnBeforeEmptyOrder', ['order' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        unset($_SESSION['ms3']['order']);
        $response = $this->invokeEvent('msOnEmptyOrder', ['order' => $this]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return $this->success('', []);
    }

    /**
     * @param bool $with_cart
     * @param bool $only_cost
     *
     * @return array|string
     */
    public function getCost($with_cart = true, $only_cost = false)
    {
        $eventParams = [
            'order' => $this,
            'cart' => $this->ms3->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
        ];
        $response = $this->invokeEvent('msOnBeforeGetOrderCost', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $cart = $this->ms3->cart->status();
        $cost = $with_cart
            ? $cart['total_cost']
            : 0;

        $delivery_cost = 0;
        /** @var msDelivery $delivery */
        if (!empty($this->order['delivery'])) {
            $delivery = $this->modx->getObject(
                msDelivery::class,
                ['id' => $this->order['delivery']]
            );
            if ($delivery) {
                $cost = $delivery->getCost($this, $cost);
                $delivery_cost = $cost - $cart['total_cost'];
            }
        }

        /** @var msPayment $payment */
        if (!empty($this->order['payment'])) {
            $payment = $this->modx->getObject(
                msPayment::class,
                ['id' => $this->order['payment']]
            );
            if ($payment) {
                $cost = $payment->getCost($this, $cost);
            }
        }

        $eventParams = [
            'order' => $this,
            'cart' => $this->ms3->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
            'cost' => $cost,
            'delivery_cost' => $delivery_cost,
        ];
        $response = $this->invokeEvent('msOnGetOrderCost', $eventParams);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $cost = $response['data']['cost'];
        $delivery_cost = $response['data']['delivery_cost'];

        return $only_cost
            ? $cost
            : $this->success('', [
                'cost' => $cost,
                'cart_cost' => $cart['total_cost'],
                'discount_cost' => $cart['total_discount'],
                'delivery_cost' => $delivery_cost
            ]);
    }
}
