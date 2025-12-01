<?php

namespace MiniShop3\Notifications\Channels;

use MODX\Revolution\modX;
use MiniShop3\Model\msOrder;
use MiniShop3\Notifications\ChannelInterface;
use MiniShop3\Notifications\Notification;
use MiniShop3\Notifications\Messages\TelegramMessage;

/**
 * Telegram notification channel
 *
 * Sends notifications via Telegram Bot API
 * Requires system settings: ms3_telegram_bot_token
 */
class TelegramChannel implements ChannelInterface
{
    protected modX $modx;
    protected string $apiUrl = 'https://api.telegram.org/bot';

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
        $message = $notification->toTelegram($recipientType);

        if (!$message instanceof TelegramMessage) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[TelegramChannel] No Telegram message defined for notification"
            );
            return false;
        }

        // Get chat ID
        $chatId = $this->getChatId($recipient);
        if (empty($chatId)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[TelegramChannel] No chat_id for recipient"
            );
            return false;
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'telegram';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        $token = $this->modx->getOption('ms3_telegram_bot_token');
        return !empty($token);
    }

    /**
     * @inheritDoc
     */
    public function getRequirements(): array
    {
        return ['ms3_telegram_bot_token'];
    }

    /**
     * Get chat ID from recipient data
     *
     * @param array $recipient
     * @return string|int|null
     */
    protected function getChatId(array $recipient): string|int|null
    {
        // Direct chat_id field
        if (!empty($recipient['telegram_chat_id'])) {
            return $recipient['telegram_chat_id'];
        }

        // Customer extended field
        if (!empty($recipient['customer']['telegram_chat_id'])) {
            return $recipient['customer']['telegram_chat_id'];
        }

        return null;
    }

    /**
     * Send message via Telegram Bot API
     *
     * @param string|int $chatId
     * @param TelegramMessage $message
     * @return bool
     */
    protected function sendMessage(string|int $chatId, TelegramMessage $message): bool
    {
        $token = $this->modx->getOption('ms3_telegram_bot_token');
        $url = $this->apiUrl . $token . '/sendMessage';

        $payload = $message->toPayload($chatId);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[TelegramChannel] API error (HTTP {$httpCode}): {$response}"
                );
                return false;
            }

            $result = json_decode($response, true);
            if (!($result['ok'] ?? false)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[TelegramChannel] API error: " . ($result['description'] ?? 'Unknown')
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[TelegramChannel] Exception: " . $e->getMessage()
            );
            return false;
        }
    }
}
