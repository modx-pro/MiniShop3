<?php

namespace MiniShop3\Controllers\Payment;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Базовый абстрактный класс для платежных провайдеров
 *
 * Предоставляет базовую функциональность для интеграции с платежными системами:
 * - Расчет комиссии (процент/фиксированная)
 * - Генерация безопасного хеша заказа
 * - Вспомогательные методы success/error
 *
 * Для создания собственного провайдера:
 * 1. Создайте класс, наследующий Payment
 * 2. Реализуйте обязательные методы send() и receive()
 * 3. При необходимости переопределите getCost() для нестандартной логики комиссии
 *
 * Пример создания провайдера для ЮKassa:
 * ```php
 * class YooKassaPayment extends Payment {
 *     protected string $shopId;
 *     protected string $secretKey;
 *
 *     public function __construct(MiniShop3 $ms3, array $config = []) {
 *         parent::__construct($ms3, $config);
 *         $this->shopId = $this->modx->getOption('ms3_yookassa_shop_id');
 *         $this->secretKey = $this->modx->getOption('ms3_yookassa_secret_key');
 *     }
 *
 *     public function send(msOrder $order): array {
 *         // Создание платежа через API ЮKassa
 *         $client = new \YooKassa\Client();
 *         $client->setAuth($this->shopId, $this->secretKey);
 *
 *         $payment = $client->createPayment([
 *             'amount' => ['value' => $order->get('cost'), 'currency' => 'RUB'],
 *             'confirmation' => ['type' => 'redirect', 'return_url' => '...'],
 *             'description' => "Заказ #{$order->get('num')}",
 *             'metadata' => ['order_hash' => $this->getOrderHash($order)],
 *         ]);
 *
 *         return $this->success('', [
 *             'payment_link' => $payment->getConfirmation()->getConfirmationUrl(),
 *             'payment_id' => $payment->getId(),
 *         ]);
 *     }
 *
 *     public function receive(msOrder $order): array {
 *         // Обработка webhook от ЮKassa
 *         $json = file_get_contents('php://input');
 *         $data = json_decode($json, true);
 *
 *         // Проверка хеша заказа
 *         if (!hash_equals($this->getOrderHash($order), $data['metadata']['order_hash'])) {
 *             return $this->error('Invalid order hash');
 *         }
 *
 *         if ($data['status'] === 'succeeded') {
 *             $order->set('status', 2); // Оплачен
 *             $order->save();
 *             return $this->success('Payment confirmed');
 *         }
 *
 *         return $this->error('Payment failed');
 *     }
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Payment
 */
abstract class Payment implements PaymentProviderInterface
{
    /** @var modX MODX объект */
    protected modX $modx;

    /** @var MiniShop3 MiniShop3 объект */
    protected MiniShop3 $ms3;

    /** @var array Конфигурация провайдера */
    protected array $config = [];

    /**
     * Конструктор
     *
     * @param MiniShop3 $ms3 MiniShop3 объект
     * @param array $config Дополнительная конфигурация провайдера
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->config = $config;

        $this->modx->lexicon->load('minishop3:payment');
    }

    /**
     * Отправка заказа в платежную систему (абстрактный метод)
     *
     * Должен быть реализован в наследнике для создания платежа
     * и получения ссылки для редиректа на страницу оплаты.
     *
     * @param msOrder $order Заказ для оплаты
     * @return array Response ['success' => true, 'data' => ['payment_link' => '...', 'payment_id' => '...']]
     */
    abstract public function send(msOrder $order): array;

    /**
     * Обработка callback от платежной системы (абстрактный метод)
     *
     * Должен быть реализован в наследнике для обработки уведомлений
     * от платежной системы о статусе оплаты (webhook).
     *
     * @param msOrder $order Заказ для проверки
     * @return array Response ['success' => true/false, 'message' => '...']
     */
    abstract public function receive(msOrder $order): array;

    /**
     * Расчет стоимости с учетом комиссии платежной системы
     *
     * Поддерживает два формата комиссии:
     * - Процентная: "3%" - комиссия 3% от суммы заказа
     * - Фиксированная: "50" - комиссия 50 рублей
     *
     * @param msOrder $order Заказ (может использоваться для расчета комиссии)
     * @param msPayment $payment Способ оплаты с настройками комиссии
     * @param float $cost Текущая стоимость заказа
     * @return float Стоимость с учетом комиссии
     */
    public function getCost(msOrder $order, msPayment $payment, float $cost): float
    {
        $add_price = $payment->get('price');

        // Если комиссия не указана - возвращаем исходную стоимость
        if (empty($add_price)) {
            return $cost;
        }

        // Процентная комиссия
        if (str_ends_with($add_price, '%')) {
            $percent = (float)str_replace('%', '', $add_price);

            // Валидация диапазона 0-100%
            if ($percent < 0 || $percent > 100) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Payment] Invalid percent value for payment #{$payment->get('id')}: {$percent}%. Must be 0-100%."
                );
                return $cost;
            }

            $add_price = $cost / 100 * $percent;
        } else {
            // Фиксированная комиссия
            $add_price = (float)$add_price;

            // Валидация неотрицательности
            if ($add_price < 0) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Payment] Invalid fixed price for payment #{$payment->get('id')}: {$add_price}. Must be >= 0."
                );
                return $cost;
            }
        }

        return $cost + $add_price;
    }

    /**
     * Генерация криптографического хеша заказа
     *
     * Используется для проверки подлинности данных при обработке callback от платежной системы.
     * Использует безопасный алгоритм HMAC-SHA256 с секретным ключом.
     *
     * Для работы необходимо установить системную настройку ms3_payment_secret.
     * Если настройка не задана, будет использован site_id как fallback.
     *
     * @param msOrder $order Заказ для хеширования
     * @return string Хеш заказа (64 символа hex)
     */
    public function getOrderHash(msOrder $order): string
    {
        $secret = $this->modx->getOption('ms3_payment_secret', null, '');

        // Если секрет не установлен - используем site_id как fallback
        if (empty($secret)) {
            $secret = $this->modx->getOption('site_id');
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[Payment] ms3_payment_secret not set, using site_id as fallback. Please set ms3_payment_secret for better security."
            );
        }

        // Используем | как разделитель для предотвращения коллизий
        // (избегаем ситуации когда "123" + "456" == "12" + "3456")
        $data = implode('|', [
            $order->get('id'),
            $order->get('num'),
            $order->get('cost'),
            $order->get('createdon')
        ]);

        // HMAC-SHA256 - безопасный алгоритм с секретным ключом
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Возврат ответа об ошибке
     *
     * @param string $message Сообщение об ошибке (ключ лексикона или текст)
     * @param array $data Дополнительные данные
     * @param array $placeholders Плейсхолдеры для подстановки в сообщение
     * @return array ['success' => false, 'message' => '...', 'data' => [...]]
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Возврат успешного ответа
     *
     * @param string $message Сообщение об успехе (ключ лексикона или текст)
     * @param array $data Дополнительные данные
     * @param array $placeholders Плейсхолдеры для подстановки в сообщение
     * @return array ['success' => true, 'message' => '...', 'data' => [...]]
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
