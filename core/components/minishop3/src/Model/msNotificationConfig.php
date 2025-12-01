<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Notification configuration per status/event
 *
 * @property int $id
 * @property string $event Event type (e.g., 'order_status_changed')
 * @property int|null $status_id Related order status ID (null = all statuses)
 * @property string $recipient_type 'customer' or 'manager'
 * @property string $channel Channel name (e.g., 'email', 'telegram', 'sms')
 * @property bool $enabled Whether this notification is enabled
 * @property string|null $subject Subject line (for email)
 * @property string|null $template Chunk name for message body
 * @property int $delay Delay in seconds before sending
 * @property array|null $config Additional channel-specific configuration (JSON)
 * @property int $position Sort order
 *
 * @package MiniShop3\Model
 */
class msNotificationConfig extends xPDOSimpleObject
{
    /**
     * Get config value as array
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = $this->get('config');
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        return is_array($config) ? $config : [];
    }

    /**
     * Set config from array
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get specific config key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $config = $this->getConfig();
        return $config[$key] ?? $default;
    }
}
