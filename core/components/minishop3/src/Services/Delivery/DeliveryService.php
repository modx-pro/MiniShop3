<?php

namespace MiniShop3\Services\Delivery;

use MiniShop3\Controllers\Delivery\DeliveryProviderInterface;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Сервис для работы с доставкой
 *
 * Обрабатывает бизнес-логику связанную с доставкой заказов,
 * включая загрузку контроллеров, расчет стоимости и управление связями
 */
class DeliveryService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /** @var string */
    protected $defaultControllerClass = 'MiniShop3\\Controllers\\Delivery\\DefaultDelivery';

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
     * Загрузка контроллера доставки
     *
     * Создает экземпляр контроллера доставки на основе класса из настроек.
     * Если класс не указан, используется контроллер по умолчанию (DefaultDelivery).
     *
     * @param msDelivery $delivery
     * @return DeliveryProviderInterface|null Контроллер доставки или null при ошибке
     */
    public function loadDeliveryController(msDelivery $delivery): ?DeliveryProviderInterface
    {
        $class = $delivery->get('class');
        if (empty($class)) {
            $class = $this->defaultControllerClass;
        }

        try {
            $controller = new $class($this->ms3, []);

            if (!$controller instanceof DeliveryProviderInterface) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        'DeliveryService: Класс "%s" не реализует DeliveryProviderInterface для доставки ID=%d',
                        $class,
                        $delivery->get('id')
                    )
                );
                return null;
            }

            return $controller;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                sprintf(
                    'DeliveryService: Ошибка загрузки контроллера доставки "%s": %s',
                    $class,
                    $e->getMessage()
                )
            );
            return null;
        }
    }

    /**
     * Расчет стоимости доставки
     *
     * Делегирует расчет стоимости контроллеру доставки.
     * Контроллер может учитывать вес, расстояние, стоимость заказа и другие факторы.
     *
     * @param msDelivery $delivery Метод доставки
     * @param DeliveryProviderInterface|null $controller Контроллер доставки (если null - будет загружен)
     * @param msOrder $order Заказ
     * @param float $cost Текущая стоимость заказа
     * @return float Стоимость доставки
     */
    public function calculateDeliveryCost(
        msDelivery $delivery,
        ?DeliveryProviderInterface $controller,
        msOrder $order,
        float $cost = 0.0
    ): float {
        if (!$controller instanceof DeliveryProviderInterface) {
            $controller = $this->loadDeliveryController($delivery);
            if (!$controller) {
                return 0.0;
            }
        }

        return (float)$controller->getCost($order, $delivery, $cost);
    }

    /**
     * Получить первый активный способ оплаты для доставки
     *
     * Возвращает ID первого активного способа оплаты,
     * связанного с данным методом доставки
     *
     * @param msDelivery $delivery
     * @return int ID способа оплаты или 0 если не найден
     */
    public function getFirstActivePayment(msDelivery $delivery): int
    {
        $deliveryId = $delivery->get('id');

        $query = $this->modx->newQuery(msPayment::class);
        $query->leftJoin(msDeliveryMember::class, 'Member', msPayment::class . '.id = Member.payment_id');
        $query->leftJoin(msDelivery::class, 'Delivery', 'Member.delivery_id = Delivery.id');
        $query->sortby(msPayment::class . '.id', 'ASC');
        $query->select(msPayment::class . '.id');
        $query->where([
            msPayment::class . '.active' => 1,
            'Delivery.id' => $deliveryId
        ]);
        $query->limit(1);

        if ($query->prepare() && $query->stmt->execute()) {
            $paymentId = $query->stmt->fetchColumn();
            return $paymentId ? (int)$paymentId : 0;
        }

        return 0;
    }

    /**
     * Удаление доставки с очисткой связей
     *
     * Удаляет все связи доставки со способами оплаты
     * из таблицы msDeliveryMember перед удалением доставки
     *
     * @param msDelivery $delivery
     * @param array $ancestors
     * @return bool
     */
    public function removeDelivery(msDelivery $delivery, array $ancestors = []): bool
    {
        $deliveryId = $delivery->get('id');

        // Удаляем все связи доставки со способами оплаты
        $this->modx->removeCollection(msDeliveryMember::class, [
            'delivery_id' => $deliveryId
        ]);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            sprintf(
                'DeliveryService: Удалены связи DeliveryMember для доставки ID=%d "%s"',
                $deliveryId,
                $delivery->get('name')
            )
        );

        return true;
    }

    /**
     * Получить список доступных способов оплаты для доставки
     *
     * Возвращает массив активных способов оплаты,
     * связанных с данным методом доставки
     *
     * @param msDelivery $delivery
     * @return array Массив объектов msPayment
     */
    public function getAvailablePayments(msDelivery $delivery): array
    {
        $deliveryId = $delivery->get('id');

        $query = $this->modx->newQuery(msPayment::class);
        $query->leftJoin(msDeliveryMember::class, 'Member', msPayment::class . '.id = Member.payment_id');
        $query->where([
            msPayment::class . '.active' => 1,
            'Member.delivery_id' => $deliveryId
        ]);
        $query->sortby(msPayment::class . '.position', 'ASC');

        return $this->modx->getCollection(msPayment::class, $query);
    }
}
