<?php

namespace MiniShop3\Services\Notification;

use MODX\Revolution\modX;
use MiniShop3\Model\msNotificationConfig;

/**
 * Service for managing notification configurations
 *
 * Reads and caches notification settings from the database
 */
class NotificationConfigService
{
    protected modX $modx;

    /** @var array Cached configs by event+status+recipient */
    protected array $cache = [];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get notification configs for a specific event and status
     *
     * @param string $event Event type (e.g., 'order_status_changed')
     * @param int|null $statusId Status ID (null for global configs)
     * @param string $recipientType 'customer' or 'manager'
     * @return msNotificationConfig[]
     */
    public function getConfigs(string $event, ?int $statusId, string $recipientType): array
    {
        $cacheKey = "{$event}_{$statusId}_{$recipientType}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $criteria = [
            'event' => $event,
            'recipient_type' => $recipientType,
            'enabled' => 1,
        ];

        // Get status-specific configs
        $configs = [];
        if ($statusId !== null) {
            $criteria['status_id'] = $statusId;
            $configs = $this->modx->getCollection(msNotificationConfig::class, $criteria);
        }

        // If no status-specific configs, try global (status_id = null)
        if (empty($configs)) {
            $criteria['status_id'] = null;
            $configs = $this->modx->getCollection(msNotificationConfig::class, $criteria);
        }

        // Sort by position
        $result = [];
        foreach ($configs as $config) {
            $result[] = $config;
        }
        usort($result, fn($a, $b) => $a->get('position') <=> $b->get('position'));

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Get enabled channels for a specific event/status/recipient
     *
     * @param string $event
     * @param int|null $statusId
     * @param string $recipientType
     * @return string[] Array of channel names
     */
    public function getChannels(string $event, ?int $statusId, string $recipientType): array
    {
        $configs = $this->getConfigs($event, $statusId, $recipientType);

        $channels = [];
        foreach ($configs as $config) {
            $channels[] = $config->get('channel');
        }

        return array_unique($channels);
    }

    /**
     * Get config for a specific channel
     *
     * @param string $event
     * @param int|null $statusId
     * @param string $recipientType
     * @param string $channel
     * @return msNotificationConfig|null
     */
    public function getChannelConfig(
        string $event,
        ?int $statusId,
        string $recipientType,
        string $channel
    ): ?msNotificationConfig {
        $configs = $this->getConfigs($event, $statusId, $recipientType);

        foreach ($configs as $config) {
            if ($config->get('channel') === $channel) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Check if a channel is enabled for event/status/recipient
     *
     * @param string $event
     * @param int|null $statusId
     * @param string $recipientType
     * @param string $channel
     * @return bool
     */
    public function isChannelEnabled(
        string $event,
        ?int $statusId,
        string $recipientType,
        string $channel
    ): bool {
        $channels = $this->getChannels($event, $statusId, $recipientType);
        return in_array($channel, $channels, true);
    }

    /**
     * Clear the configuration cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Create a new notification config
     *
     * @param array $data
     * @return msNotificationConfig|null
     */
    public function create(array $data): ?msNotificationConfig
    {
        $config = $this->modx->newObject(msNotificationConfig::class);
        $config->fromArray($data);

        if ($config->save()) {
            $this->clearCache();
            return $config;
        }

        return null;
    }

    /**
     * Update a notification config
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $config = $this->modx->getObject(msNotificationConfig::class, $id);
        if (!$config) {
            return false;
        }

        $config->fromArray($data);
        $result = $config->save();

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Delete a notification config
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $config = $this->modx->getObject(msNotificationConfig::class, $id);
        if (!$config) {
            return false;
        }

        $result = $config->remove();

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Get all configs for a specific status (for admin UI)
     *
     * @param int $statusId
     * @return msNotificationConfig[]
     */
    public function getConfigsByStatus(int $statusId): array
    {
        return $this->modx->getCollection(msNotificationConfig::class, [
            'status_id' => $statusId,
        ]);
    }

    /**
     * Get all configs for an event (for admin UI)
     *
     * @param string $event
     * @return msNotificationConfig[]
     */
    public function getConfigsByEvent(string $event): array
    {
        return $this->modx->getCollection(msNotificationConfig::class, [
            'event' => $event,
        ]);
    }
}
