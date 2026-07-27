<?php

namespace MiniShop3\Notifications\Order;

use MODX\Revolution\modX;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Services\Notification\NotificationConfigService;
use MiniShop3\Services\Payment\PaymentLinkResolver;
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
    private ?string $paymentLink = null;
    private bool $paymentLinkResolved = false;

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
        return $this->getConfigService()->getChannels(
            self::EVENT,
            $this->newStatus->get('id'),
            $recipientType
        );
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
        // Ensure lexicon is loaded (manager contains status translations)
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:manager');
        $this->modx->lexicon->load('minishop3:notifications');

        $config = $this->getChannelConfig($recipientType, 'telegram');

        // Use template from config if available
        if ($config && $config->get('template')) {
            $content = $this->renderTemplate($config->get('template'));
        } else {
            // Default message with translated status
            $content = $this->buildDefaultTelegramMessage($recipientType);
        }

        return (new TelegramMessage())
            ->content($content)
            ->parseMode('HTML');
    }

    /**
     * Build default Telegram message with proper localization
     *
     * @param string $recipientType
     * @return string
     */
    protected function buildDefaultTelegramMessage(string $recipientType): string
    {
        $orderNum = $this->order->get('num');
        $statusKey = $this->newStatus->get('name');
        $statusName = $this->modx->lexicon($statusKey);

        // If lexicon not found, use the key itself
        if ($statusName === $statusKey) {
            $statusName = $statusKey;
        }

        $siteName = $this->modx->getOption('site_name');

        if ($recipientType === 'customer') {
            // Customer message
            $template = $this->modx->lexicon('ms3_telegram_order_status_customer');
            if ($template === 'ms3_telegram_order_status_customer') {
                // Fallback if lexicon not defined
                $template = "🛒 <b>{$siteName}</b>\n\n";
                $template .= $this->modx->lexicon('ms3_telegram_order') . " <b>#{$orderNum}</b>\n";
                $template .= $this->modx->lexicon('ms3_telegram_status') . ": <b>{$statusName}</b>";
            } else {
                $template = str_replace(
                    ['{$site_name}', '{$order_num}', '{$status_name}'],
                    [$siteName, $orderNum, $statusName],
                    $template
                );
            }
        } else {
            // Manager message
            $template = $this->modx->lexicon('ms3_telegram_order_status_manager');
            if ($template === 'ms3_telegram_order_status_manager') {
                // Fallback if lexicon not defined
                $template = "📦 <b>{$statusName}</b>\n\n";
                $template .= $this->modx->lexicon('ms3_telegram_order') . " <b>#{$orderNum}</b>";

                // Add order cost
                $cost = $this->order->get('cost');
                if ($cost > 0) {
                    $template .= "\n" . $this->modx->lexicon('ms3_telegram_total') . ": <b>{$cost}</b>";
                }
            } else {
                $cost = $this->order->get('cost');
                $template = str_replace(
                    ['{$site_name}', '{$order_num}', '{$status_name}', '{$cost}'],
                    [$siteName, $orderNum, $statusName, $cost],
                    $template
                );
            }
        }

        return $template;
    }

    /**
     * @inheritDoc
     */
    public function toSms(string $recipientType): ?SmsMessage
    {
        // Ensure lexicon is loaded (manager contains status translations)
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:manager');
        $this->modx->lexicon->load('minishop3:notifications');

        $config = $this->getChannelConfig($recipientType, 'sms');

        $orderNum = $this->order->get('num');
        $statusKey = $this->newStatus->get('name');
        $statusName = $this->modx->lexicon($statusKey) ?: $statusKey;

        // Use template from config or default
        if ($config && $config->get('template')) {
            $content = $this->renderTemplate($config->get('template'));
        } else {
            $content = $this->modx->lexicon('ms3_telegram_order') . " #{$orderNum}: {$statusName}";
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
        // Ensure lexicon is loaded for translations (manager contains status translations)
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:manager');

        $pls = parent::getPlaceholders();

        // Add status-specific placeholders with translations
        $pls['status'] = $this->newStatus->toArray();
        $statusKey = $this->newStatus->get('name');
        $pls['status_key'] = $statusKey;
        $pls['status_name'] = $this->modx->lexicon($statusKey) ?: $statusKey;
        $pls['status_color'] = $this->newStatus->get('color');

        if ($this->oldStatus) {
            $pls['old_status'] = $this->oldStatus->toArray();
            $oldStatusKey = $this->oldStatus->get('name');
            $pls['old_status_key'] = $oldStatusKey;
            $pls['old_status_name'] = $this->modx->lexicon($oldStatusKey) ?: $oldStatusKey;
        }

        $paymentLink = $this->resolvePaymentLink();
        if ($paymentLink !== null) {
            $pls['payment_link'] = $paymentLink;
        }

        // Site info
        $pls['site_name'] = $this->modx->getOption('site_name');
        $pls['site_url'] = $this->modx->getOption('site_url');

        return $pls;
    }

    private function resolvePaymentLink(): ?string
    {
        if (!$this->paymentLinkResolved) {
            /** @var PaymentLinkResolver $resolver */
            $resolver = $this->modx->services->get('ms3_payment_link_resolver');
            $this->paymentLink = $resolver->resolveForOrder($this->order, $this->newStatus);
            $this->paymentLinkResolved = true;
        }

        return $this->paymentLink;
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
