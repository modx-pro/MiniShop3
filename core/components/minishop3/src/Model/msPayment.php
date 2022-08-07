<?php

namespace MiniShop3\Model;

use MiniShop3\Controllers\Delivery\DeliveryInterface;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\Controllers\Order\OrderInterface;
use MiniShop3\Controllers\Payment\Payment;
use MiniShop3\Controllers\Payment\PaymentInterface;
use MiniShop3\MiniShop3;
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
    /** @var Payment $controller */
    public $controller;
    /** @var MiniShop3 $ms3 */
    public $ms3;

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
        require_once dirname(__FILE__, 2) . '/Controllers/Payment/Payment.php';

        if (!$class = $this->get('class')) {
            $class = 'Payment';
        }

        if ($class !== 'Payment') {
            $this->ms3->loadCustomClasses('payment');
        }

        if (!class_exists($class)) {
            $this->xpdo->log(modX::LOG_LEVEL_ERROR, 'Payment controller class "' . $class . '" not found.');
            $class = 'Payment';
        }

        $this->controller = new $class($this, []);
        if (!($this->controller instanceof PaymentInterface)) {
            $this->xpdo->log(modX::LOG_LEVEL_ERROR, 'Could not initialize payment controller class: "' . $class . '"');

            return false;
        }

        return true;
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
        if (!is_object($this->controller) || !($this->controller instanceof PaymentInterface)) {
            if (!$this->loadHandler()) {
                return false;
            }
        }

        return $this->controller->send($order);
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
        if (!is_object($this->controller) || !($this->controller instanceof PaymentInterface)) {
            if (!$this->loadHandler()) {
                return false;
            }
        }

        return $this->controller->receive($order);
    }

    /**
     * Returns an additional cost depending on the method of payment
     *
     * @param OrderInterface|Order $order
     * @param float $cost Current cost of order
     *
     * @return float|integer
     */
    public function getCost(OrderInterface $order, $cost = 0.0)
    {
        if (!is_object($this->controller) || !($this->controller instanceof DeliveryInterface)) {
            if (!$this->loadHandler()) {
                return false;
            }
        }

        return $this->controller->getCost($order, $this, $cost);
    }

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->xpdo->removeCollection(msDeliveryMember::class, ['payment_id' => $this->id]);
        return parent::remove($ancestors);
    }
}
