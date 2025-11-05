<?php

namespace MiniShop3\Controllers\Delivery;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;

/**
 * Базовый абстрактный класс для провайдеров доставки
 *
 * Предоставляет общую функциональность для всех методов доставки.
 * Кастомные провайдеры (СДЭК, Почта России, DPD и т.д.) могут наследовать
 * этот класс и переопределять метод getCost() для своей логики расчета.
 *
 * Пример создания провайдера для СДЭК:
 * ```php
 * class CdekDelivery extends Delivery {
 *     public function getCost(msOrder $order, msDelivery $delivery, float $cost): float {
 *         $address = $order->getOne('Address');
 *         $cityTo = $address->get('city');
 *
 *         // Вызов API СДЭК для расчета стоимости
 *         $cdekApi = new CdekApiClient();
 *         $tariff = $cdekApi->calculate([
 *             'city_to' => $cityTo,
 *             'weight' => $order->get('weight'),
 *             'packages' => $this->getPackagesFromOrder($order)
 *         ]);
 *
 *         return (float)$tariff['delivery_sum'];
 *     }
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Delivery
 */
abstract class Delivery implements DeliveryProviderInterface
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /** @var array */
    protected array $config = [];

    /**
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->config = $config;
    }

    /**
     * Расчет стоимости доставки (дефолтная реализация)
     *
     * Стандартная логика расчета включает:
     * - Стоимость по весу (weight_price * вес заказа)
     * - Бесплатная доставка при превышении порога (free_delivery_amount)
     * - Фиксированная стоимость или процент от суммы заказа
     *
     * Кастомные провайдеры могут переопределить этот метод для
     * интеграции с внешними API (СДЭК, Почта России и т.д.)
     *
     * @param msOrder $order Заказ для расчета доставки
     * @param msDelivery $delivery Способ доставки
     * @param float $cost Текущая стоимость заказа
     * @return float Дополнительная стоимость за доставку
     */
    public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
    {
        // Получаем данные корзины для расчета веса
        $cart = [
            'total_weight' => 0,
            'total_cost' => 0
        ];

        // Загружаем контроллеры если еще не загружены
        if (empty($this->ms3->cart)) {
            $this->ms3->services->load($this->ms3->config['ctx'] ?? 'web');
        }

        // Получаем данные корзины если контроллер доступен
        if (!empty($this->ms3->cart)) {
            $response = $this->ms3->cart->status();
            if ($response['success']) {
                $cart = $response['data'];
            }
        }

        // Стоимость по весу
        $weightPrice = (float)$delivery->get('weight_price');
        $cartWeight = (float)($cart['total_weight'] ?? 0);

        if ($weightPrice < 0) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Delivery] Invalid weight_price for delivery #{$delivery->get('id')}: {$weightPrice}. Must be >= 0."
            );
            $weightPrice = 0;
        }

        $cost += $weightPrice * $cartWeight;

        // Проверка бесплатной доставки
        $freeDeliveryAmount = (float)$delivery->get('free_delivery_amount');
        $cartCost = (float)($cart['total_cost'] ?? 0);

        if ($freeDeliveryAmount > 0 && $cartCost >= $freeDeliveryAmount) {
            // Бесплатная доставка при превышении порога
            return $cost;
        }

        // Базовая стоимость доставки
        $addPrice = $delivery->get('price');

        if (empty($addPrice)) {
            return $cost;
        }

        // Процентная стоимость
        if (str_ends_with($addPrice, '%')) {
            $percent = (float)str_replace('%', '', $addPrice);

            // Валидация диапазона 0-100%
            if ($percent < 0 || $percent > 100) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Delivery] Invalid percent value for delivery #{$delivery->get('id')}: {$percent}%. Must be 0-100%."
                );
                return $cost;
            }

            $addPrice = $cartCost / 100 * $percent;
        } else {
            // Фиксированная стоимость
            $addPrice = (float)$addPrice;

            if ($addPrice < 0) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Delivery] Invalid fixed price for delivery #{$delivery->get('id')}: {$addPrice}. Must be >= 0."
                );
                return $cost;
            }
        }

        return $cost + $addPrice;
    }

    /**
     * Вспомогательный метод для возврата ошибки
     *
     * @param string $message Код сообщения из лексикона
     * @param array $data Дополнительные данные
     * @param array $placeholders Плейсхолдеры для сообщения
     * @return array Массив с success=false
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Вспомогательный метод для возврата успеха
     *
     * @param string $message Код сообщения из лексикона
     * @param array $data Дополнительные данные
     * @param array $placeholders Плейсхолдеры для сообщения
     * @return array Массив с success=true
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
