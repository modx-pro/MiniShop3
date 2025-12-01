<?php

namespace MiniShop3\Notifications;

use MODX\Revolution\modX;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;

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
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] send() called for recipientType: {$recipientType}");

        $this->loadChannels();
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Channels loaded: " . implode(', ', array_keys($this->channels)));

        $results = [];
        $channels = $notification->via($recipientType);
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Channels from via(): " . json_encode($channels));

        $order = $notification->getOrder();
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Order ID: " . $order->get('id'));

        // Fire before event - allows plugins to modify or cancel notification
        $eventResult = $this->modx->invokeEvent('msOnBeforeSendNotification', [
            'notification' => $notification,
            'recipient' => &$recipient,
            'recipientType' => $recipientType,
            'channels' => &$channels,
        ]);

        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Event result type: " . gettype($eventResult) . ", value: " . json_encode($eventResult));

        // Check if notification was cancelled by plugin
        // Note: MODX invokeEvent returns:
        // - '' (empty string) or false when no plugins listen
        // - array of plugin results when plugins are registered
        // Only cancel if plugin explicitly sets $modx->event->output = false or returns 'cancel'
        $cancelled = false;
        if (is_array($eventResult) && !empty($eventResult)) {
            // Check if any plugin explicitly returned false or 'cancel'
            foreach ($eventResult as $pluginResult) {
                if ($pluginResult === false || $pluginResult === 'cancel') {
                    $cancelled = true;
                    break;
                }
            }
        }
        // Also check event output property (plugins can set $modx->event->output = false)
        if (!$cancelled && isset($this->modx->event->output) && $this->modx->event->output === false) {
            $cancelled = true;
        }

        if ($cancelled) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Notification cancelled by plugin");
            return $results;
        }

        if (empty($channels)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] No channels to send! via() returned empty array");
            return $results;
        }

        foreach ($channels as $channelName) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Processing channel: {$channelName}");

            if (!isset($this->channels[$channelName])) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Channel '{$channelName}' NOT registered!");
                $results[$channelName] = false;
                continue;
            }

            $channel = $this->channels[$channelName];

            if (!$channel->isAvailable()) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Channel '{$channelName}' NOT available!");
                $results[$channelName] = false;
                continue;
            }

            $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Channel '{$channelName}' is available, sending...");

            try {
                if ($notification->shouldQueue() && $this->isSchedulerAvailable()) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Queueing notification...");
                    $results[$channelName] = $this->queue($notification, $recipient, $recipientType, $channelName);
                } else {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Sending immediately via channel->send()...");
                    $results[$channelName] = $channel->send($notification, $recipient, $order);
                    $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] channel->send() returned: " . ($results[$channelName] ? 'TRUE' : 'FALSE'));
                }
            } catch (\Throwable $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[DEBUG NotificationManager] EXCEPTION in channel '{$channelName}': " . $e->getMessage()
                );
                $results[$channelName] = false;
            }
        }

        $this->modx->log(modX::LOG_LEVEL_ERROR, "[DEBUG NotificationManager] Final results: " . json_encode($results));

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
