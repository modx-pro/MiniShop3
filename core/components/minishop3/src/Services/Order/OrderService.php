<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MODX\Revolution\modSystemEvent;
use MODX\Revolution\modX;

/**
 * Сервис для работы с заказами
 *
 * Обрабатывает бизнес-логику связанную с заказами,
 * включая пересчет стоимости, события сохранения и удаления
 */
class OrderService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Пересчитать товары в заказе
     *
     * Пересчитывает общую стоимость корзины, вес и итоговую стоимость заказа
     * на основе всех товаров в заказе
     *
     * @param msOrder $order
     * @return bool Результат сохранения заказа
     */
    public function updateProducts(msOrder $order): bool
    {
        $delivery_cost = $order->get('delivery_cost');
        $cart_cost = $cost = $weight = 0;

        $products = $order->getMany('Products');
        /** @var msOrderProduct $product */
        foreach ($products as $product) {
            $count = $product->get('count');
            $cart_cost += $product->get('price') * $count;
            $weight += $product->get('weight') * $count;
        }

        $order->fromArray([
            'cost' => $cart_cost + $delivery_cost,
            'cart_cost' => $cart_cost,
            'weight' => $weight,
            'update_products' => true
        ]);

        return $order->save();
    }

    /**
     * Обработка сохранения заказа с событиями
     *
     * @deprecated Логика перенесена в msOrder::save(), этот метод оставлен для обратной совместимости
     *
     * @param msOrder $order
     * @param bool|null $cacheFlag
     * @return bool
     */
    public function handleOrderSave(msOrder $order, ?bool $cacheFlag = null): bool
    {
        // Просто делегируем вызов в msOrder::save()
        // Он уже содержит всю логику событий
        return $order->save($cacheFlag);
    }

    /**
     * Удалить заказ с событиями
     *
     * @deprecated Логика перенесена в msOrder::remove(), этот метод оставлен для обратной совместимости
     *
     * @param msOrder $order
     * @param array $ancestors
     * @return bool Результат удаления
     */
    public function removeOrder(msOrder $order, array $ancestors = []): bool
    {
        // Просто делегируем вызов в msOrder::remove()
        // Он уже содержит всю логику событий
        return $order->remove($ancestors);
    }

    /**
     * Получить статистику по заказу
     *
     * Возвращает количество товаров и общий вес
     *
     * @param msOrder $order
     * @return array
     */
    public function getOrderStatistics(msOrder $order): array
    {
        $products = $order->getMany('Products');
        $totalCount = 0;
        $totalWeight = 0;

        /** @var msOrderProduct $product */
        foreach ($products as $product) {
            $totalCount += $product->get('count');
            $totalWeight += $product->get('weight') * $product->get('count');
        }

        return [
            'product_count' => $totalCount,
            'total_weight' => $totalWeight,
            'cart_cost' => $order->get('cart_cost'),
            'delivery_cost' => $order->get('delivery_cost'),
            'total_cost' => $order->get('cost'),
        ];
    }
}
