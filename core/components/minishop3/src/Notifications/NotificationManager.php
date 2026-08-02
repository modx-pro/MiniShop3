<?php

namespace MiniShop3\Notifications;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Utils\EventGate;

/**
 * Central notification service
 *
 * Manages notification channels and dispatches notifications
 */
class NotificationManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    /** @var ChannelInterface[] */
    protected array $channels = [];

    /** @var bool */
    protected bool $channelsLoaded = false;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->ms3 = $modx->services->get('ms3');
    }

    /**
     * Send notification to customer
     *
     * @param Notification $notification
     * @param array $recipient Customer data with routing info
     * @return array Results per channel ['email' => true, 'telegram' => false]
     */
    public function sendToCustomer(Notification $notification, array $recipient): array
    {
        return $this->send($notification, $recipient, 'customer');
    }

    /**
     * Send notification to manager
     *
     * @param Notification $notification
     * @param array $recipient Manager data with routing info
     * @return array Results per channel
     */
    public function sendToManager(Notification $notification, array $recipient): array
    {
        return $this->send($notification, $recipient, 'manager');
    }

    /**
     * Send notification through specified channels
     *
     * @param Notification $notification
     * @param array $recipient Recipient data
     * @param string $recipientType 'customer' or 'manager'
     * @return array Results per channel
     */
    public function send(Notification $notification, array $recipient, string $recipientType): array
    {
        $this->loadChannels();

        $results = [];
        $channels = $notification->via($recipientType);
        $order = $notification->getOrder();

        // Fire before event - allows plugins to modify or cancel notification.
        //
        // Two propagation paths supported:
        //   1) by-ref mutation of $recipient/$channels in the plugin scope —
        //      preserved for plugins that mutate $scriptProperties directly
        //      (long-standing extension contract for third-party packages).
        //   2) $modx->event->returnedValues['recipient']/['channels'] —
        //      explicit channel introduced in #219/#245 for plugins that prefer
        //      the returned-values contract.
        $event = EventGate::invokeRaw($this->modx, 'msOnBeforeSendNotification', [
            'notification' => $notification,
            'recipient' => &$recipient,
            'recipientType' => $recipientType,
            'channels' => &$channels,
        ]);
        $returnedValues = $event['returnedValues'];
        $recipient = EventGate::applyReturnedArray($recipient, $returnedValues, 'recipient');
        if (isset($returnedValues['channels']) && is_array($returnedValues['channels'])) {
            $channels = $returnedValues['channels'];
        }

        // Check if notification was cancelled by plugin
        $cancelled = $event['cancelled'];
        if (!$cancelled && isset($this->modx->event->output) && $this->modx->event->output === false) {
            $cancelled = true;
        }

        if ($cancelled) {
            return $results;
        }

        if (empty($channels)) {
            return $results;
        }

        foreach ($channels as $channelName) {
            if (!isset($this->channels[$channelName])) {
                $results[$channelName] = false;
                continue;
            }

            $channel = $this->channels[$channelName];

            if (!$channel->isAvailable()) {
                $results[$channelName] = false;
                continue;
            }

            try {
                if ($notification->shouldQueue() && $this->isSchedulerAvailable()) {
                    $results[$channelName] = $this->queue($notification, $recipient, $recipientType, $channelName);
                } else {
                    $results[$channelName] = $channel->send($notification, $recipient, $order);
                }
            } catch (\Throwable $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[ms3] Notification error in channel '{$channelName}': " . $e->getMessage()
                );
                $results[$channelName] = false;
            }
        }

        // Fire after event - for logging/analytics
        $this->modx->invokeEvent('msOnAfterSendNotification', [
            'notification' => $notification,
            'recipient' => $recipient,
            'recipientType' => $recipientType,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Register a notification channel
     *
     * @param ChannelInterface $channel
     * @return self
     */
    public function registerChannel(ChannelInterface $channel): self
    {
        $this->channels[$channel->getName()] = $channel;
        return $this;
    }

    /**
     * Get registered channel by name
     *
     * @param string $name
     * @return ChannelInterface|null
     */
    public function getChannel(string $name): ?ChannelInterface
    {
        $this->loadChannels();
        return $this->channels[$name] ?? null;
    }

    /**
     * Get all registered channels
     *
     * @return ChannelInterface[]
     */
    public function getChannels(): array
    {
        $this->loadChannels();
        return $this->channels;
    }

    /**
     * Get available (configured) channels
     *
     * @return ChannelInterface[]
     */
    public function getAvailableChannels(): array
    {
        $this->loadChannels();
        return array_filter($this->channels, fn($channel) => $channel->isAvailable());
    }

    /**
     * Load default and plugin-registered channels
     */
    protected function loadChannels(): void
    {
        if ($this->channelsLoaded) {
            return;
        }

        // Register built-in channels
        $this->registerChannel(new Channels\EmailChannel($this->modx));
        $this->registerChannel(new Channels\TelegramChannel($this->modx));

        // Fire event for plugins to register custom channels
        $this->modx->invokeEvent('msOnRegisterNotificationChannels', [
            'manager' => $this,
        ]);

        $this->channelsLoaded = true;
    }

    /**
     * Queue notification for delayed sending via Scheduler
     *
     * @param Notification $notification
     * @param array $recipient
     * @param string $recipientType
     * @param string $channelName
     * @return bool
     */
    protected function queue(
        Notification $notification,
        array $recipient,
        string $recipientType,
        string $channelName
    ): bool {
        if (!$this->isSchedulerAvailable()) {
            return false;
        }

        /** @var \Scheduler $scheduler */
        $scheduler = $this->modx->services->get('scheduler');

        $task = $scheduler->getTask('minishop3', 'ms3_send_notification');
        if (!$task) {
            $task = $this->modx->newObject('sTask');
            $task->fromArray([
                'class_key' => 'sFileTask',
                'content' => 'elements/tasks/sendNotification.php',
                'namespace' => 'minishop3',
                'reference' => 'ms3_send_notification',
                'description' => 'Send queued notification',
            ]);
            $task->save();
        }

        $delay = $notification->delay();
        $timing = $delay > 0 ? "+{$delay} seconds" : '+1 second';

        $taskRun = $task->schedule($timing, [
            'notification_class' => get_class($notification),
            'order_id' => $notification->getOrder()->get('id'),
            'notification_data' => $notification->getData(),
            'recipient' => $recipient,
            'recipient_type' => $recipientType,
            'channel' => $channelName,
        ]);

        return $taskRun !== false;
    }

    /**
     * Check if Scheduler component is available
     *
     * @return bool
     */
    protected function isSchedulerAvailable(): bool
    {
        return $this->modx->services->has('scheduler');
    }
}
