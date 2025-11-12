<?php

namespace MiniShop3\Model;

use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Services\Payment\PaymentService;
use MODX\Revolution\modX;
use xPDO\Om\xPDOSimpleObject;
use xPDO\xPDO;

/**
 * Class msPayment
 *
 * @property string $name
 * @property string $description
 * @property string $price
 * @property string $logo
 * @property integer $position
 * @property integer $active
 * @property string $class
 * @property array $properties
 *
 * @package MiniShop3\Model
 */
class msPayment extends xPDOSimpleObject
{
    /** @var PaymentProviderInterface|null $controller */
    public $controller;
    /** @var MiniShop3 $ms3 */
    public $ms3;

    /** @var PaymentService|null */
    protected $paymentService;

    /**
     * msPayment constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        parent::__construct($xpdo);
        if ($this->xpdo->services->has('ms3')) {
            $this->ms3 = $this->xpdo->services->get('ms3');
        }
    }

    /**
     * Loads payment handler class
     *
     * @return bool
     */
    public function loadHandler()
    {
        $this->controller = $this->getPaymentService()->loadPaymentHandler($this);
        return $this->controller instanceof PaymentProviderInterface;
    }

    /**
     * Send user to payment service
     *
     * @param msOrder $order Object with an order
     *
     * @return array|boolean $response
     */
    public function send(msOrder $order)
    {
        return $this->getPaymentService()->sendToPaymentGateway($this, $this->controller, $order);
    }

    /**
     * Receives payment
     *
     * @param msOrder $order Object with an order
     *
     * @return array|boolean $response
     */
    public function receive(msOrder $order)
    {
        return $this->getPaymentService()->receivePayment($this, $this->controller, $order);
    }

    /**
     * Returns an additional cost depending on the method of payment
     *
     * @param msOrder $order Order object
     * @param float $cost Current cost of order
     *
     * @return float
     */
    public function getCost(msOrder $order, float $cost = 0.0): float
    {
        return $this->getPaymentService()->calculatePaymentCost(
            $this,
            $this->controller,
            $order,
            (float)$cost
        );
    }

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->getPaymentService()->removePayment($this, $ancestors);
        return parent::remove($ancestors);
    }

    /**
     * Получить сервис оплаты (lazy loading)
     *
     * @return PaymentService
     */
    protected function getPaymentService(): PaymentService
    {
        if ($this->paymentService === null) {
            if ($this->xpdo->services->has('ms3_payment_service')) {
                $this->paymentService = $this->xpdo->services->get('ms3_payment_service');
            } else {
                $this->paymentService = new PaymentService($this->xpdo);
            }
        }

        return $this->paymentService;
    }
}
