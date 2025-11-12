<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;

/**
 * Интерфейс провайдера платежной системы
 *
 * Определяет контракт для всех платежных провайдеров (ЮKassa, Stripe, PayPal и т.д.)
 *
 * @package MiniShop3\Controllers\Payment
 */
interface PaymentProviderInterface
{
    /**
     * Отправка заказа в платежную систему
     *
     * Генерирует платежную ссылку для редиректа покупателя на страницу оплаты.
     *
     * @param msOrder $order Заказ для оплаты
     * @return array Response ['success' => true, 'data' => ['payment_link' => '...', 'payment_id' => '...']]
     */
    public function send(msOrder $order): array;

    /**
     * Обработка callback от платежной системы
     *
     * Принимает уведомление о статусе оплаты от платежной системы (webhook).
     * Проверяет подпись, валидирует данные, обновляет статус заказа.
     *
     * @param msOrder $order Заказ для проверки
     * @return array Response ['success' => true/false, 'message' => '...']
     */
    public function receive(msOrder $order): array;

    /**
     * Расчет стоимости с учетом комиссии платежной системы
     *
     * @param msOrder $order Заказ (может использоваться для расчета комиссии)
     * @param msPayment $payment Способ оплаты с настройками комиссии
     * @param float $cost Текущая стоимость заказа
     * @return float Стоимость с учетом комиссии
     */
    public function getCost(msOrder $order, msPayment $payment, float $cost): float;

    /**
     * Генерация криптографического хеша заказа
     *
     * Используется для проверки подлинности данных при обработке callback.
     *
     * @param msOrder $order Заказ для хеширования
     * @return string Хеш заказа
     */
    public function getOrderHash(msOrder $order): string;
}
