<?php

namespace MiniShop3\Notifications\Order;

use MODX\Revolution\modX;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Services\Notification\NotificationConfigService;
use MiniShop3\Notifications\Notification;
use MiniShop3\Notifications\Messages\EmailMessage;
use MiniShop3\Notifications\Messages\TelegramMessage;
use MiniShop3\Notifications\Messages\SmsMessage;

/**
 * Notification for order status changes
 *
 * Sent when an order transitions from one status to another
 */
class StatusChangedNotification extends Notification
{
    public const EVENT = 'order_status_changed';

    protected ?msOrderStatus $oldStatus;
    protected msOrderStatus $newStatus;
    protected ?NotificationConfigService $configService = null;

    public function __construct(
        modX $modx,
        msOrder $order,
        msOrderStatus $newStatus,
        ?msOrderStatus $oldStatus = null
    ) {
        parent::__construct($modx, $order, [
            'old_status' => $oldStatus?->toArray() ?? [],
            'new_status' => $newStatus->toArray(),
        ]);

        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get NotificationConfigService (lazy loading)
     *
     * @return NotificationConfigService
     */
    protected function getConfigService(): NotificationConfigService
    {
        if (!$this->configService) {
            $this->configService = $this->modx->services->get('ms3_notification_config');
        }
        return $this->configService;
    }

    /**
     * @inheritDoc
     */
    public function via(string $recipientType): array
    {
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG StatusChangedNotification] via() called for recipientType: {$recipientType}");
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG StatusChangedNotification] Event: " . self::EVENT . ", Status ID: " . $this->newStatus->get('id'));

        $channels = $this->getConfigService()->getChannels(
            self::EVENT,
            $this->newStatus->get('id'),
            $recipientType
        );

        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG StatusChangedNotification] Channels returned: " . json_encode($channels));

        return $channels;
    }

    /**
     * Get channel config for building messages
     *
     * @param string $recipientType
     * @param string $channel
     * @return msNotificationConfig|null
     */
    protected function getChannelConfig(string $recipientType, string $channel): ?msNotificationConfig
    {
        return $this->getConfigService()->getChannelConfig(
            self::EVENT,
            $this->newStatus->get('id'),
            $recipientType,
            $channel
        );
    }

    /**
     * @inheritDoc
     */
    public function toEmail(string $recipientType): ?EmailMessage
    {
        $config = $this->getChannelConfig($recipientType, 'email');
        if (!$config) {
            return null;
        }

        $subject = $config->get('subject');
        $template = $config->get('template');

        if (empty($template)) {
            return null;
        }

        // Process subject placeholders
        $subject = $this->processSubject($subject ?? '');

        return (new EmailMessage())
            ->subject($subject)
            ->chunk($template)
            ->with($this->getPlaceholders());
    }

    /**
     * @inheritDoc
     */
    public function toTelegram(string $recipientType): ?TelegramMessage
    {
        $config = $this->getChannelConfig($recipientType, 'telegram');
        if (!$config) {
            // Default message if no config
            $orderNum = $this->order->get('num');
            $statusName = $this->newStatus->get('name');

            $content = $recipientType === 'customer'
                ? "Статус заказа #{$orderNum} изменён на: <b>{$statusName}</b>"
                : "Заказ #{$orderNum} переведён в статус: <b>{$statusName}</b>";

            return (new TelegramMessage())
                ->content($content)
                ->parseMode('HTML');
        }

        // Use template from config if available
        $template = $config->get('template');
        if ($template) {
            // Render chunk with placeholders
            $content = $this->renderTemplate($template);
        } else {
            $orderNum = $this->order->get('num');
            $statusName = $this->newStatus->get('name');
            $content = $recipientType === 'customer'
                ? "Статус заказа #{$orderNum} изменён на: <b>{$statusName}</b>"
                : "Заказ #{$orderNum} переведён в статус: <b>{$statusName}</b>";
        }

        return (new TelegramMessage())
            ->content($content)
            ->parseMode('HTML');
    }

    /**
     * @inheritDoc
     */
    public function toSms(string $recipientType): ?SmsMessage
    {
        $config = $this->getChannelConfig($recipientType, 'sms');

        $orderNum = $this->order->get('num');
        $statusName = $this->newStatus->get('name');

        // Use template from config or default
        if ($config && $config->get('template')) {
            $content = $this->renderTemplate($config->get('template'));
        } else {
            $content = "Заказ #{$orderNum}: {$statusName}";
        }

        return (new SmsMessage())->content($content);
    }

    /**
     * @inheritDoc
     */
    public function delay(): int
    {
        // Get delay from the first available channel config
        $recipientTypes = ['customer', 'manager'];
        foreach ($recipientTypes as $type) {
            $configs = $this->getConfigService()->getConfigs(
                self::EVENT,
                $this->newStatus->get('id'),
                $type
            );
            foreach ($configs as $config) {
                $delay = $config->get('delay');
                if ($delay > 0) {
                    return $delay;
                }
            }
        }
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getPlaceholders(): array
    {
        $pls = parent::getPlaceholders();

        // Add status-specific placeholders
        $pls['status'] = $this->newStatus->toArray();
        $pls['status_name'] = $this->newStatus->get('name');
        $pls['status_color'] = $this->newStatus->get('color');

        if ($this->oldStatus) {
            $pls['old_status'] = $this->oldStatus->toArray();
            $pls['old_status_name'] = $this->oldStatus->get('name');
        }

        // Site info
        $pls['site_name'] = $this->modx->getOption('site_name');
        $pls['site_url'] = $this->modx->getOption('site_url');

        return $pls;
    }

    /**
     * Process subject line with placeholders
     *
     * @param string $subject
     * @return string
     */
    protected function processSubject(string $subject): string
    {
        $pls = $this->getPlaceholders();

        // Simple placeholder replacement
        foreach ($pls as $key => $value) {
            if (is_scalar($value)) {
                $subject = str_replace("{{$key}}", (string) $value, $subject);
            }
        }

        return $subject;
    }

    /**
     * Render a template with placeholders
     *
     * @param string $template Chunk name
     * @return string
     */
    protected function renderTemplate(string $template): string
    {
        $placeholders = $this->getPlaceholders();

        // Use pdoTools for Fenom rendering if available
        if ($this->modx->services->has(\ModxPro\PdoTools\Fetch::class)) {
            $pdoFetch = $this->modx->services->get(\ModxPro\PdoTools\Fetch::class);
            return $pdoFetch->getChunk($template, $placeholders);
        }

        return $this->modx->getChunk($template, $placeholders) ?: '';
    }

    /**
     * Get the new status
     *
     * @return msOrderStatus
     */
    public function getNewStatus(): msOrderStatus
    {
        return $this->newStatus;
    }

    /**
     * Get the old status
     *
     * @return msOrderStatus|null
     */
    public function getOldStatus(): ?msOrderStatus
    {
        return $this->oldStatus;
    }
}
