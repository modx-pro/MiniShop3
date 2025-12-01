<?php

namespace MiniShop3\Notifications\Channels;

use MODX\Revolution\modX;
use MiniShop3\Model\msOrder;
use MiniShop3\Notifications\ChannelInterface;
use MiniShop3\Notifications\Notification;
use MiniShop3\Notifications\Messages\SmsMessage;

/**
 * SMS notification channel (abstract)
 *
 * Base implementation for SMS channels.
 * Extend this class and implement sendSms() for specific SMS providers
 * (e.g., Twilio, SMSC, SMS.ru, etc.)
 */
abstract class SmsChannel implements ChannelInterface
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @inheritDoc
     */
    public function send(Notification $notification, array $recipient, msOrder $order): bool
    {
        $recipientType = $recipient['type'] ?? 'customer';

        // Get message from notification
        $message = $notification->toSms($recipientType);

        if (!$message instanceof SmsMessage) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[SmsChannel] No SMS message defined for notification"
            );
            return false;
        }

        // Get phone number
        $phone = $this->getPhoneNumber($recipient);
        if (empty($phone)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[SmsChannel] No phone number for recipient"
            );
            return false;
        }

        // Normalize phone number
        $phone = $this->normalizePhone($phone);

        return $this->sendSms($phone, $message);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'sms';
    }

    /**
     * Get phone number from recipient data
     *
     * @param array $recipient
     * @return string|null
     */
    protected function getPhoneNumber(array $recipient): ?string
    {
        // Direct phone field
        if (!empty($recipient['phone'])) {
            return $recipient['phone'];
        }

        // Customer phone
        if (!empty($recipient['customer']['phone'])) {
            return $recipient['customer']['phone'];
        }

        // Address phone (fallback)
        if (!empty($recipient['address']['phone'])) {
            return $recipient['address']['phone'];
        }

        return null;
    }

    /**
     * Normalize phone number (remove formatting)
     *
     * @param string $phone
     * @return string
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except leading +
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $phone = preg_replace('/[^0-9]/', '', $phone);

        return $hasPlus ? '+' . $phone : $phone;
    }

    /**
     * Send SMS via provider API
     *
     * Override this method in provider-specific implementations
     *
     * @param string $phone Normalized phone number
     * @param SmsMessage $message
     * @return bool
     */
    abstract protected function sendSms(string $phone, SmsMessage $message): bool;
}
