<?php

namespace MiniShop3\Notifications;

use MiniShop3\Model\msOrder;

/**
 * Interface for notification channels
 *
 * Implement this interface to create custom notification channels
 * (e.g., Telegram, SMS, Viber, WhatsApp, webhooks, etc.)
 */
interface ChannelInterface
{
    /**
     * Send notification through this channel
     *
     * @param Notification $notification The notification to send
     * @param array $recipient Recipient data (email, phone, chat_id, etc.)
     * @param msOrder $order The order related to notification
     * @return bool Success status
     */
    public function send(Notification $notification, array $recipient, msOrder $order): bool;

    /**
     * Get unique channel identifier
     *
     * @return string Channel name (e.g., 'email', 'telegram', 'sms')
     */
    public function getName(): string;

    /**
     * Check if channel is properly configured and available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get channel configuration requirements
     *
     * @return array List of required system settings or config keys
     */
    public function getRequirements(): array;
}
