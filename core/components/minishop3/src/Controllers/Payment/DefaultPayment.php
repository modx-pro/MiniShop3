<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\Model\msOrder;

/**
 * Дефолтный провайдер оплаты (заглушка)
 *
 * Используется для методов оплаты без интеграции с внешними платежными системами:
 * - Оплата наличными при получении
 * - Оплата по счету
 * - Банковский перевод
 * - И т.д.
 *
 * Просто помечает заказ как ожидающий оплаты и редиректит на страницу успеха.
 *
 * @package MiniShop3\Controllers\Payment
 */
class DefaultPayment extends Payment
{
    /**
     * Отправка заказа (заглушка)
     *
     * Для дефолтного провайдера просто возвращает ссылку на страницу успеха
     * без реального взаимодействия с платежной системой.
     *
     * @param msOrder $order Заказ для оплаты
     * @return array Response с ссылкой на страницу успеха
     */
    public function send(msOrder $order): array
    {
        // ID страницы успешного оформления заказа
        $successPageId = (int)$this->modx->getOption('ms3_order_success_page_id', null, 0);

        if (empty($successPageId)) {
            // Если страница не настроена - редирект на главную
            $successPageId = $this->modx->getOption('site_start');
        }

        // Генерируем URL страницы успеха с номером заказа
        $paymentLink = $this->modx->makeUrl($successPageId, '', [
            'msorder' => $order->get('num'),
        ], 'full');

        return $this->success('ms3_payment_link_created', [
            'payment_link' => $paymentLink,
            'order_id' => $order->get('id'),
            'order_num' => $order->get('num'),
        ]);
    }

    /**
     * Обработка callback (заглушка)
     *
     * Для дефолтного провайдера не требуется обработка callback,
     * т.к. нет внешней платежной системы.
     *
     * @param msOrder $order Заказ для проверки
     * @return array Response об успехе
     */
    public function receive(msOrder $order): array
    {
        return $this->success('ms3_payment_received', [
            'order_id' => $order->get('id'),
            'order_num' => $order->get('num'),
        ]);
    }
}
