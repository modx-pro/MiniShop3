<?php

namespace MiniShop3\Services\Payment;

use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Сервис для работы с оплатой
 *
 * Обрабатывает бизнес-логику связанную с оплатой заказов,
 * включая загрузку контроллеров, отправку на платежный шлюз,
 * прием платежей и расчет стоимости
 */
class PaymentService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /** @var string */
    protected $defaultControllerClass = 'MiniShop3\\Controllers\\Payment\\DefaultPayment';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        if ($modx->services->has('ms3')) {
            $this->ms3 = $modx->services->get('ms3');
        }
    }

    /**
     * Загрузка контроллера оплаты (обработчика платежей)
     *
     * Создает экземпляр контроллера оплаты на основе класса из настроек.
     * Если класс не указан, используется контроллер по умолчанию (DefaultPayment).
     *
     * @param msPayment $payment
     * @return PaymentProviderInterface|null Контроллер оплаты или null при ошибке
     */
    public function loadPaymentHandler(msPayment $payment): ?PaymentProviderInterface
    {
        $class = $payment->get('class');
        if (empty($class)) {
            $class = $this->defaultControllerClass;
        }

        try {
            $controller = new $class($this->ms3, []);

            if (!$controller instanceof PaymentProviderInterface) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        'PaymentService: Класс "%s" не реализует PaymentProviderInterface для способа оплаты ID=%d',
                        $class,
                        $payment->get('id')
                    )
                );
                return null;
            }

            return $controller;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    'PaymentService: Ошибка загрузки контроллера оплаты "%s": %s',
                    $class,
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Отправка пользователя на платежный шлюз
     *
     * Перенаправляет пользователя на сайт платежной системы для оплаты заказа.
     * Контроллер может сформировать форму автоотправки или вернуть URL для редиректа.
     *
     * @param msPayment $payment Способ оплаты
     * @param PaymentProviderInterface|null $controller Контроллер оплаты (если null - будет загружен)
     * @param msOrder $order Заказ
     * @return array|bool Массив с данными для редиректа или false при ошибке
     */
    public function sendToPaymentGateway(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order
    ) {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return false;
            }
        }

        return $controller->send($order);
    }

    /**
     * Прием платежа от платежной системы
     *
     * Обрабатывает callback от платежного шлюза после оплаты.
     * Обычно вызывается при возврате пользователя или через webhook.
     *
     * @param msPayment $payment Способ оплаты
     * @param PaymentProviderInterface|null $controller Контроллер оплаты (если null - будет загружен)
     * @param msOrder $order Заказ
     * @return array|bool Результат обработки платежа или false при ошибке
     */
    public function receivePayment(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order
    ) {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return false;
            }
        }

        return $controller->receive($order);
    }

    /**
     * Расчет стоимости оплаты
     *
     * Делегирует расчет стоимости контроллеру оплаты.
     * Контроллер может добавлять комиссию за использование данного способа оплаты.
     *
     * @param msPayment $payment Способ оплаты
     * @param PaymentProviderInterface|null $controller Контроллер оплаты (если null - будет загружен)
     * @param msOrder $order Заказ
     * @param float $cost Текущая стоимость заказа
     * @return float Дополнительная стоимость за способ оплаты
     */
    public function calculatePaymentCost(
        msPayment $payment,
        ?PaymentProviderInterface $controller,
        msOrder $order,
        float $cost = 0.0
    ): float {
        if (!$controller instanceof PaymentProviderInterface) {
            $controller = $this->loadPaymentHandler($payment);
            if (!$controller) {
                return 0.0;
            }
        }

        return (float)$controller->getCost($order, $payment, $cost);
    }

    /**
     * Удаление способа оплаты с очисткой связей
     *
     * Удаляет все связи способа оплаты с доставками
     * из таблицы msDeliveryMember перед удалением
     *
     * @param msPayment $payment
     * @param array $ancestors
     * @return bool
     */
    public function removePayment(msPayment $payment, array $ancestors = []): bool
    {
        $paymentId = $payment->get('id');

        // Удаляем все связи способа оплаты с доставками
        $this->modx->removeCollection(msDeliveryMember::class, [
            'payment_id' => $paymentId
        ]);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            sprintf(
                'PaymentService: Удалены связи DeliveryMember для способа оплаты ID=%d "%s"',
                $paymentId,
                $payment->get('name')
            )
        );

        return true;
    }
}
