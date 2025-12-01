<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API контроллер для управления настройками уведомлений (Notification Center)
 *
 * Обрабатывает CRUD операции для msNotificationConfig в админке.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class NotificationsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получить список настроек уведомлений
     * GET /api/mgr/notifications
     *
     * @param array $params URL параметры (start, limit, status_id, channel, recipient_type)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 50);

        // Базовый критерий
        $criteria = [];

        // Фильтр по статусу
        if (isset($params['status_id']) && $params['status_id'] !== '') {
            $criteria['status_id'] = (int)$params['status_id'] ?: null;
        }

        // Фильтр по каналу
        if (!empty($params['channel'])) {
            $criteria['channel'] = $params['channel'];
        }

        // Фильтр по типу получателя
        if (!empty($params['recipient_type'])) {
            $criteria['recipient_type'] = $params['recipient_type'];
        }

        // Фильтр по событию
        if (!empty($params['event'])) {
            $criteria['event'] = $params['event'];
        }

        // Фильтр по enabled
        if (isset($params['enabled']) && $params['enabled'] !== '') {
            $criteria['enabled'] = (bool)$params['enabled'];
        }

        // Получаем общее количество
        $total = $this->modx->getCount(msNotificationConfig::class, $criteria);

        // Получаем записи с пагинацией
        $configs = $this->modx->getIterator(msNotificationConfig::class, $criteria, [
            'limit' => $limit,
            'offset' => $start,
            'sortby' => 'position',
            'sortdir' => 'ASC'
        ]);

        $results = [];
        foreach ($configs as $config) {
            $results[] = $this->formatConfig($config);
        }

        return Response::success([
            'results' => $results,
            'total' => $total
        ])->getData();
    }

    /**
     * Получить конкретную настройку уведомления
     * GET /api/mgr/notifications/{id}
     *
     * @param array $params URL параметры (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', 400)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', 404)->getData();
        }

        return Response::success($this->formatConfig($config))->getData();
    }

    /**
     * Создать новую настройку уведомления
     * POST /api/mgr/notifications
     *
     * @param array $data Данные для создания
     * @return array Response
     */
    public function create(array $data = []): array
    {
        // Валидация обязательных полей
        $required = ['event', 'recipient_type', 'channel'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Response::error("Field '{$field}' is required", 400)->getData();
            }
        }

        // Проверка уникальности комбинации
        $exists = $this->modx->getObject(msNotificationConfig::class, [
            'event' => $data['event'],
            'status_id' => $data['status_id'] ?? null,
            'recipient_type' => $data['recipient_type'],
            'channel' => $data['channel'],
        ]);

        if ($exists) {
            return Response::error('Notification config with this combination already exists', 400)->getData();
        }

        /** @var msNotificationConfig $config */
        $config = $this->modx->newObject(msNotificationConfig::class);

        // Заполняем поля
        $config->set('event', $data['event']);
        $config->set('status_id', !empty($data['status_id']) ? (int)$data['status_id'] : null);
        $config->set('recipient_type', $data['recipient_type']);
        $config->set('channel', $data['channel']);
        $config->set('enabled', isset($data['enabled']) ? (bool)$data['enabled'] : true);
        $config->set('subject', $data['subject'] ?? null);
        $config->set('template', $data['template'] ?? null);
        $config->set('delay', (int)($data['delay'] ?? 0));
        $config->set('position', (int)($data['position'] ?? 0));

        // Дополнительная конфигурация
        if (!empty($data['config']) && is_array($data['config'])) {
            $config->setConfig($data['config']);
        }

        if (!$config->save()) {
            return Response::error('Failed to save notification config', 500)->getData();
        }

        return Response::success(
            $this->formatConfig($config),
            $this->modx->lexicon('ms3_notification_created')
        )->getData();
    }

    /**
     * Обновить настройку уведомления
     * PUT /api/mgr/notifications/{id}
     *
     * @param array $data Данные для обновления
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', 400)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', 404)->getData();
        }

        // Обновляемые поля
        $allowedFields = ['event', 'status_id', 'recipient_type', 'channel', 'enabled', 'subject', 'template', 'delay', 'position'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // Преобразование типов
                if ($field === 'status_id') {
                    $value = !empty($value) ? (int)$value : null;
                } elseif ($field === 'enabled') {
                    $value = (bool)$value;
                } elseif (in_array($field, ['delay', 'position'])) {
                    $value = (int)$value;
                }

                $config->set($field, $value);
            }
        }

        // Дополнительная конфигурация
        if (array_key_exists('config', $data)) {
            if (is_array($data['config'])) {
                $config->setConfig($data['config']);
            } elseif (is_string($data['config'])) {
                $decoded = json_decode($data['config'], true);
                if (is_array($decoded)) {
                    $config->setConfig($decoded);
                }
            }
        }

        if (!$config->save()) {
            return Response::error('Failed to save notification config', 500)->getData();
        }

        return Response::success(
            $this->formatConfig($config),
            $this->modx->lexicon('ms3_notification_updated')
        )->getData();
    }

    /**
     * Удалить настройку уведомления
     * DELETE /api/mgr/notifications/{id}
     *
     * @param array $params URL параметры (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', 400)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', 404)->getData();
        }

        if (!$config->remove()) {
            return Response::error('Failed to delete notification config', 500)->getData();
        }

        return Response::success([], $this->modx->lexicon('ms3_notification_deleted'))->getData();
    }

    /**
     * Получить справочники для формы
     * GET /api/mgr/notifications/references
     *
     * @return array Response
     */
    public function getReferences(): array
    {
        // Загружаем нужные лексиконы
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:setting');
        $this->modx->lexicon->load('minishop3:notifications');

        // Статусы заказов
        $statuses = [];
        $statusIterator = $this->modx->getIterator(msOrderStatus::class, [], [
            'sortby' => 'position',
            'sortdir' => 'ASC'
        ]);
        foreach ($statusIterator as $status) {
            $statusName = $status->get('name');
            // Если имя - ключ лексикона, получаем перевод
            $translatedName = $this->modx->lexicon($statusName);
            // Если перевод не найден (вернулся тот же ключ), используем оригинал
            if ($translatedName === $statusName && strpos($statusName, 'ms3_') === 0) {
                $translatedName = $statusName; // оставляем как есть
            }

            $statuses[] = [
                'id' => $status->get('id'),
                'name' => $translatedName,
                'color' => $status->get('color'),
            ];
        }

        // Типы событий
        $events = [
            ['id' => 'order_status_changed', 'name' => $this->modx->lexicon('ms3_notification_event_status_changed')],
            ['id' => 'order_created', 'name' => $this->modx->lexicon('ms3_notification_event_order_created')],
        ];

        // Типы получателей
        $recipientTypes = [
            ['id' => 'customer', 'name' => $this->modx->lexicon('ms3_notification_recipient_customer')],
            ['id' => 'manager', 'name' => $this->modx->lexicon('ms3_notification_recipient_manager')],
        ];

        // Каналы (из зарегистрированных в системе)
        $channels = [
            ['id' => 'email', 'name' => 'Email'],
        ];

        // Добавляем каналы из системы уведомлений если доступны
        if ($this->modx->services->has('ms3_notification_manager')) {
            /** @var \MiniShop3\Notifications\NotificationManager $notificationManager */
            $notificationManager = $this->modx->services->get('ms3_notification_manager');
            foreach ($notificationManager->getChannels() as $channel) {
                $channelName = $channel->getName();
                if ($channelName !== 'email') { // email уже добавлен
                    $channels[] = [
                        'id' => $channelName,
                        'name' => ucfirst($channelName),
                        'available' => $channel->isAvailable(),
                    ];
                }
            }
        }

        return Response::success([
            'statuses' => $statuses,
            'events' => $events,
            'recipient_types' => $recipientTypes,
            'channels' => $channels,
        ])->getData();
    }

    /**
     * Форматировать объект настройки уведомления для API ответа
     *
     * @param msNotificationConfig $config
     * @return array
     */
    protected function formatConfig(msNotificationConfig $config): array
    {
        $data = [
            'id' => $config->get('id'),
            'event' => $config->get('event'),
            'status_id' => $config->get('status_id'),
            'recipient_type' => $config->get('recipient_type'),
            'channel' => $config->get('channel'),
            'enabled' => (bool)$config->get('enabled'),
            'subject' => $config->get('subject'),
            'template' => $config->get('template'),
            'delay' => (int)$config->get('delay'),
            'config' => $config->getConfig(),
            'position' => (int)$config->get('position'),
        ];

        // Добавляем имя статуса если есть
        if ($config->get('status_id')) {
            $status = $config->getOne('Status');
            if ($status) {
                $data['status_name'] = $status->get('name');
                $data['status_color'] = $status->get('color');
            }
        }

        return $data;
    }
}
