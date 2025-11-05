<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;

/**
 * Interface DeliveryProviderInterface
 *
 * Определяет контракт для всех провайдеров доставки (СДЭК, Почта России, Курьер и т.д.)
 * Все кастомные классы доставки должны реализовывать этот интерфейс
 *
 * @package MiniShop3\Controllers\Delivery
 */
interface DeliveryProviderInterface
{
    /**
     * Расчет стоимости доставки
     *
     * @param msOrder $order Заказ для расчета доставки
     * @param msDelivery $delivery Способ доставки
     * @param float $cost Текущая стоимость заказа
     * @return float Дополнительная стоимость за доставку
     */
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float;
}
