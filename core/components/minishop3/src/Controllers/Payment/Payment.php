<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;
use xPDO\Om\xPDOObject;

class Payment implements PaymentInterface
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];

    /**
     * @param xPDOObject $object
     * @param array $config
     */
    public function __construct(xPDOObject $object, $config = [])
    {
        $this->modx = $object->xpdo;
        $this->ms3 = $object->xpdo->services->get('ms3');
        $this->config = $config;
    }

    /**
     * @param msOrder $order
     *
     * @return array|string
     */
    public function send(msOrder $order)
    {
        return $this->success('', ['msorder' => $order->get('id')]);
    }

    /**
     * @param msOrder $order
     *
     * @return array|string
     */
    public function receive(msOrder $order)
    {
        return $this->success('');
    }

    /**
     * @param OrderInterface $order
     * @param msPayment $payment
     * @param float $cost
     *
     * @return float|int
     */
    public function getCost(OrderInterface $order, msPayment $payment, $cost = 0.0)
    {
        $add_price = $payment->get('price');
        if (preg_match('/%$/', $add_price)) {
            $add_price = str_replace('%', '', $add_price);
            $add_price = $cost / 100 * $add_price;
        }
        $cost += $add_price;

        return $cost;
    }

    /**
     * Returns hash of order for various checks
     *
     * @param msOrder $order
     *
     * @return string
     */
    public function getOrderHash(msOrder $order)
    {
        return md5(
            $order->get('id') .
            $order->get('num') .
            $order->get('cart_cost') .
            $order->get('delivery_cost') .
            $order->get('createdon')
        );
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function error(string $message = '', array $data = [], array $placeholders = [])
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
    public function success(string $message = '', array $data = [], array $placeholders = [])
    {
        if (empty($this->ms3) && $this->modx->services->has('ms3')) {
            $this->ms3 = $this->modx->services->get('ms3');
        }

        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
