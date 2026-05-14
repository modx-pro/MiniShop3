<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for managing notification settings (Notification Center)
 *
 * Handles CRUD operations for msNotificationConfig in admin panel.
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
     * Get notification settings list
     * GET /api/mgr/notifications
     *
     * @param array $params URL parameters (start, limit, status_id, channel, recipient_type)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 50);

        $criteria = [];
        if (isset($params['status_id']) && $params['status_id'] !== '') {
            $criteria['status_id'] = (int)$params['status_id'] ?: null;
        }

        if (!empty($params['channel'])) {
            $criteria['channel'] = $params['channel'];
        }

        if (!empty($params['recipient_type'])) {
            $criteria['recipient_type'] = $params['recipient_type'];
        }

        if (!empty($params['event'])) {
            $criteria['event'] = $params['event'];
        }

        if (isset($params['enabled']) && $params['enabled'] !== '') {
            $criteria['enabled'] = (bool)$params['enabled'];
        }

        $total = $this->modx->getCount(msNotificationConfig::class, $criteria);

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
     * Get specific notification setting
     * GET /api/mgr/notifications/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success($this->formatConfig($config))->getData();
    }

    /**
     * Create new notification setting
     * POST /api/mgr/notifications
     *
     * @param array $data Data to create
     * @return array Response
     */
    public function create(array $data = []): array
    {
        $required = ['event', 'recipient_type', 'channel'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Response::error("Field '{$field}' is required", HttpStatus::BAD_REQUEST)->getData();
            }
        }

        $exists = $this->modx->getObject(msNotificationConfig::class, [
            'event' => $data['event'],
            'status_id' => $data['status_id'] ?? null,
            'recipient_type' => $data['recipient_type'],
            'channel' => $data['channel'],
        ]);

        if ($exists) {
            return Response::error('Notification config with this combination already exists', HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var msNotificationConfig $config */
        $config = $this->modx->newObject(msNotificationConfig::class);

        $config->set('event', $data['event']);
        $config->set('status_id', !empty($data['status_id']) ? (int)$data['status_id'] : null);
        $config->set('recipient_type', $data['recipient_type']);
        $config->set('channel', $data['channel']);
        $config->set('enabled', isset($data['enabled']) ? (bool)$data['enabled'] : true);
        $config->set('subject', $data['subject'] ?? null);
        $config->set('template', $data['template'] ?? null);
        $config->set('delay', (int)($data['delay'] ?? 0));
        $config->set('position', (int)($data['position'] ?? 0));

        if (!empty($data['config']) && is_array($data['config'])) {
            $config->setConfig($data['config']);
        }

        if (!$config->save()) {
            return Response::error('Failed to save notification config', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(
            $this->formatConfig($config),
            $this->modx->lexicon('ms3_notification_created')
        )->getData();
    }

    /**
     * Update notification setting
     * PUT /api/mgr/notifications/{id}
     *
     * @param array $data Data to update
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', HttpStatus::NOT_FOUND)->getData();
        }

        $allowedFields = ['event', 'status_id', 'recipient_type', 'channel', 'enabled', 'subject', 'template', 'delay', 'position'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

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
            return Response::error('Failed to save notification config', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(
            $this->formatConfig($config),
            $this->modx->lexicon('ms3_notification_updated')
        )->getData();
    }

    /**
     * Delete notification setting
     * DELETE /api/mgr/notifications/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Notification config ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $config = $this->modx->getObject(msNotificationConfig::class, $id);

        if (!$config) {
            return Response::error('Notification config not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (!$config->remove()) {
            return Response::error('Failed to delete notification config', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], $this->modx->lexicon('ms3_notification_deleted'))->getData();
    }

    /**
     * Get references for form
     * GET /api/mgr/notifications/references
     *
     * @return array Response
     */
    public function getReferences(): array
    {
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:setting');
        $this->modx->lexicon->load('minishop3:notifications');

        $statuses = [];
        $statusIterator = $this->modx->getIterator(msOrderStatus::class, [], [
            'sortby' => 'position',
            'sortdir' => 'ASC'
        ]);
        foreach ($statusIterator as $status) {
            $statusName = $status->get('name');
            $translatedName = $this->modx->lexicon($statusName);
            if ($translatedName === $statusName && strpos($statusName, 'ms3_') === 0) {
                $translatedName = $statusName;
            }

            $statuses[] = [
                'id' => $status->get('id'),
                'name' => $translatedName,
                'color' => $status->get('color'),
            ];
        }

        $events = [
            ['id' => 'order_status_changed', 'name' => $this->modx->lexicon('ms3_notification_event_status_changed')],
            ['id' => 'order_created', 'name' => $this->modx->lexicon('ms3_notification_event_order_created')],
        ];

        $recipientTypes = [
            ['id' => 'customer', 'name' => $this->modx->lexicon('ms3_notification_recipient_customer')],
            ['id' => 'manager', 'name' => $this->modx->lexicon('ms3_notification_recipient_manager')],
        ];

        $channels = [
            ['id' => 'email', 'name' => 'Email'],
        ];

        if ($this->modx->services->has('ms3_notifications')) {
            /** @var \MiniShop3\Notifications\NotificationManager $notificationManager */
            $notificationManager = $this->modx->services->get('ms3_notifications');
            foreach ($notificationManager->getChannels() as $channel) {
                $channelName = $channel->getName();
                if ($channelName !== 'email') {
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
     * Format notification config object for API response
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
