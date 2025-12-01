<?php
/**
 * Scheduler task for sending queued notifications
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

use MiniShop3\Model\msOrder;
use MiniShop3\Notifications\NotificationManager;

// Required parameters
$notificationClass = $scriptProperties['notification_class'] ?? null;
$orderId = $scriptProperties['order_id'] ?? null;
$notificationData = $scriptProperties['notification_data'] ?? [];
$recipient = $scriptProperties['recipient'] ?? [];
$recipientType = $scriptProperties['recipient_type'] ?? 'customer';
$channelName = $scriptProperties['channel'] ?? null;

if (!$notificationClass || !$orderId || !$channelName) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] Missing required parameters"
    );
    return false;
}

// Load MiniShop3
if (!$modx->services->has('ms3')) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] MiniShop3 service not available"
    );
    return false;
}

// Get the order
/** @var msOrder|null $order */
$order = $modx->getObject(msOrder::class, ['id' => $orderId]);
if (!$order) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] Order #{$orderId} not found"
    );
    return false;
}

// Check if notification class exists
if (!class_exists($notificationClass)) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] Notification class not found: {$notificationClass}"
    );
    return false;
}

// Get notification manager
/** @var NotificationManager $notificationManager */
$notificationManager = $modx->services->get('ms3_notifications');

// Get the channel
$channel = $notificationManager->getChannel($channelName);
if (!$channel) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] Channel not found: {$channelName}"
    );
    return false;
}

if (!$channel->isAvailable()) {
    $modx->log(
        modX::LOG_LEVEL_WARN,
        "[sendNotification task] Channel not available: {$channelName}"
    );
    return false;
}

// Recreate notification based on class
// For StatusChangedNotification, we need to load status objects
try {
    if ($notificationClass === 'MiniShop3\Notifications\Order\StatusChangedNotification') {
        $newStatusId = $notificationData['new_status']['id'] ?? null;
        $oldStatusId = $notificationData['old_status']['id'] ?? null;

        $newStatus = $newStatusId
            ? $modx->getObject(\MiniShop3\Model\msOrderStatus::class, ['id' => $newStatusId])
            : null;
        $oldStatus = $oldStatusId
            ? $modx->getObject(\MiniShop3\Model\msOrderStatus::class, ['id' => $oldStatusId])
            : null;

        if (!$newStatus) {
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                "[sendNotification task] New status not found for StatusChangedNotification"
            );
            return false;
        }

        $notification = new $notificationClass($modx, $order, $newStatus, $oldStatus);
    } else {
        // Generic notification recreation
        $notification = new $notificationClass($modx, $order, $notificationData);
    }

    // Send via specific channel
    $result = $channel->send($notification, $recipient, $order);

    if ($result) {
        $modx->log(
            modX::LOG_LEVEL_INFO,
            "[sendNotification task] Successfully sent via {$channelName} for order #{$orderId}"
        );
    } else {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            "[sendNotification task] Failed to send via {$channelName} for order #{$orderId}"
        );
    }

    return $result;
} catch (\Throwable $e) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[sendNotification task] Exception: " . $e->getMessage()
    );
    return false;
}
