<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MODX\Revolution\modX;
use xPDO\Om\xPDOObject;

class Delivery implements DeliveryInterface
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;

    /**
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
    }

    /**
     * Returns an additional cost depending on the method of delivery
     *
     * @param OrderInterface $order
     * @param msDelivery $delivery
     * @param float $cost
     *
     * @return float|integer
     */
    public function getCost(OrderInterface $order, msDelivery $delivery, float $cost = 0.0): float|int
    {
        if (empty($this->ms3) && $this->modx->services->has('ms3')) {
            $this->ms3 = $this->modx->services->get('ms3');
        }
        if (empty($this->ms3->cart)) {
            $this->ms3->loadServices($this->ms3->config['ctx']);
        }
        $response = $this->ms3->cart->status();
        $cart = [
            'total_weight' => 0,
            'total_cost' => 0
        ];
        if ($response['success']) {
            $cart = $response['data'];
        }
        $weight_price = $delivery->get('weight_price');

        $cart_weight = $cart['total_weight'];
        $cost += $weight_price * $cart_weight;

        $free_delivery_amount = $delivery->get('free_delivery_amount');
        if ($free_delivery_amount > 0 && $free_delivery_amount <= $cart['total_cost']) {
            $add_price = 0;
        } else {
            $add_price = $delivery->get('price');
            if (str_ends_with($add_price, '%')) {
                $add_price = str_replace('%', '', $add_price);
                $add_price = $cart['total_cost'] / 100 * $add_price;
            }
        }

        $cost += $add_price;

        return $cost;
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function error(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        if (empty($this->ms3) && $this->modx->services->has('ms3')) {
            $this->ms3 = $this->modx->services->get('ms3');
        }

        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function success(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        if (empty($this->ms3) && $this->modx->services->has('ms3')) {
            $this->ms3 = $this->modx->services->get('ms3');
        }

        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
