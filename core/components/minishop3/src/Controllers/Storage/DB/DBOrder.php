<?php

namespace MiniShop3\Controllers\Storage\DB;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;
use MiniShop3\Controllers\Order\OrderInterface;

class DBOrder extends DBStorage implements OrderInterface
{
    private $config;
    private $order;

    /**
     * @param string $token
     * @param $config
     * @return bool
     */
    public function initialize(string $token = '', $config = []): bool
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;
        $this->config = $config;
        return true;
    }

    public function get(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $this->initDraft();

        //TODO Добавить событие?
//        $response = $this->invokeEvent('msOnBeforeGetOrder', [
//            'draft' => $this->draft,
//            'cart' => $this,
//        ]);
//        if (!($response['success'])) {
//            return $this->error($response['message']);
//        }
        $this->order = $this->getOrder();

        //TODO Добавить событие?
//        $response = $this->invokeEvent('msOnGetOrder, [
//            'draft' => $this->draft,
//            'data' => $this->order,
//            'cart' => $this
//        ]);
//
//        if (!$response['success']) {
//            return $this->error($response['message']);
//        }
//
//        $this->cart = $response['data']['data'];

        $data = [];

        $data['order'] = $this->order;
        return $this->success(
            'ms3_order_get_success',
            $data
        );
    }

    public function getCost($with_cart = true, $only_cost = false): array
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCost', [
            'controller' => $this,
            'cart' => $this->ms3->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $cost = 0;
        $cart = [];
        $this->ms3->cart->initialize($this->ms3->config['ctx'], $this->token);
        $response = $this->ms3->cart->status();
        if ($response['success']) {
            $cart = $response['data'];
            $cost = $with_cart
                ? $cart['total_cost']
                : 0;
        }

        $delivery_cost = 0;
        if (!empty($this->order['delivery_id'])) {
            /** @var msDelivery $msDelivery */
            $msDelivery = $this->modx->getObject(
                msDelivery::class,
                ['id' => $this->order['delivery_id']]
            );
            if ($msDelivery) {
                $cost = $msDelivery->getCost($this, $cost);
                $delivery_cost = $cost - $cart['total_cost'];
                $this->setDeliveryCost($delivery_cost);
            }
        }

        if (!empty($this->order['payment_id'])) {
            /** @var msPayment $msPayment */
            $msPayment = $this->modx->getObject(
                msPayment::class,
                ['id' => $this->order['payment_id']]
            );
            if ($msPayment) {
                $cost = $msPayment->getCost($this, $cost);
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderCost', [
            'controller' => $this,
            'cart' => $this->ms3->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
            'cost' => $cost,
            'delivery_cost' => $delivery_cost,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $cost = $response['data']['cost'];
        $delivery_cost = $response['data']['delivery_cost'];

        $data = $only_cost
            ? $cost
            : $this->success('', [
                'cost' => $cost,
                'cart_cost' => $cart['total_cost'],
                'discount_cost' => $cart['total_discount'],
                'delivery_cost' => $delivery_cost
            ]);

        return $this->success(
            'ms3_order_getcost_success',
            $data
        );
    }

    public function add($key, $value = ''): bool
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddToOrder', [
            'key' => $key,
            'value' => $value,
            'controller' => $this,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $value = $response['data']['value'];

        if (empty($value)) {
            $validated = '';
            $this->order = $this->add($key);
        } else {
            $validated = $this->validate($key, $value);
            if ($validated !== false) {
                $this->order = $this->add($key, $validated);
                $response = $this->ms3->utils->invokeEvent('msOnAddToOrder', [
                    'key' => $key,
                    'value' => $validated,
                    'controller' => $this,
                ]);
                if (!$response['success']) {
                    return $this->error($response['message']);
                }
                $validated = $response['data']['value'];
            } else {
                $this->order = $this->add($key);
            }
        }

        return ($validated === false)
            ? $this->error('', [$key => $value])
            : $this->success('', [$key => $validated]);
    }

    public function validate($key, $value): mixed
    {
        $eventParams = [
            'key' => $key,
            'value' => $value,
            'controller' => $this,
        ];
        $response = $this->invokeEvent('msOnBeforeValidateOrderValue', $eventParams);
        $value = $response['data']['value'];

        //TODO Validate with delivery's validation riles

        $eventParams = [
            'key' => $key,
            'value' => $value,
            'controller' => $this,
        ];
        $response = $this->invokeEvent('msOnValidateOrderValue', $eventParams);
        return $response['data']['value'];
    }

    public function remove($key): bool
    {
        if ($exists = array_key_exists($key, $this->order)) {
            $response = $this->ms3->utils->invokeEvent('msOnBeforeRemoveFromOrder', [
                'key' => $key,
                'controller' => $this,
            ]);
            if (!$response['success']) {
                return $this->error($response['message']);
            }

            $this->order = $this->remove($key);
            $response = $this->ms3->utils->invokeEvent('msOnRemoveFromOrder', [
                'key' => $key,
                'controller' => $this,
            ]);
            if (!$response['success']) {
                return $this->error($response['message']);
            }
        }

        return $exists;
    }

    public function set(array $order): array
    {
        //TODO учесть поля Customer
        foreach ($order as $key => $value) {
            $this->add($key, $value);
        }

        return $this->get();
    }

    public function submit(): array
    {
        return [];
    }

    public function clean(): bool
    {
        return true;
    }

    protected function getOrder()
    {
        $Address = $this->draft->getOne('Address');
        $output = $this->draft->toArray();
        if (!empty($Address)) {
            $addressFields = [];
            foreach ($Address->toArray() as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            $output = array_merge($output, $addressFields);
        }
        return $output;
    }

    protected function setDeliveryCost($delivery_cost)
    {
        $cart_cost = $this->draft->get('cart_cost');
        $cost = $cart_cost + $delivery_cost;

        $this->draft->set('delivery_cost', $delivery_cost);
        $this->draft->set('cost', $cost);
        $this->draft->save();
    }
}
