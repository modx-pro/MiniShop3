<?php

/**
 * #811: order_created must have seeded configs, reuse StatusChangedNotification
 * plumbing via late static binding, and not double-notify on draft exit.
 *
 * Run: php tests/OrderCreatedNotificationContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$service = (string) file_get_contents($root . '/core/components/minishop3/src/Services/Order/OrderStatusService.php');
if (substr_count($service, '$this->dispatchNotification(') < 1) {
    $fail('OrderStatusService must dispatch notifications (#811)');
}
$draftBlock = strpos($service, 'isCreatedFromDraft($oldStatus)');
$afterBlock = substr($service, (int) $draftBlock, 700);
if (substr_count($afterBlock, 'dispatchNotification(') !== 1) {
    $fail('Draft exit must dispatch exactly one notification, not both events (#811)');
}

$status = (string) file_get_contents($root . '/core/components/minishop3/src/Notifications/Order/StatusChangedNotification.php');
foreach (['static::EVENT', 'buildDefaultSmsMessage('] as $needle) {
    if (!str_contains($status, $needle)) {
        $fail("StatusChangedNotification must support subclasses: {$needle} (#811)");
    }
}

$task = (string) file_get_contents($root . '/core/components/minishop3/elements/tasks/sendNotification.php');
if (!str_contains($task, 'is_a($notificationClass')) {
    $fail('Scheduler must recreate notification subclasses (#811)');
}

$migrations = glob($root . '/core/components/minishop3/migrations/*order_created_notification_configs.php') ?: [];
if ($migrations === []) {
    $fail('Missing migration seeding order_created notification configs (#811)');
}
$migration = (string) file_get_contents($migrations[0]);
if (!str_contains($migration, "'order_created'") || !str_contains($migration, 'status_id` IS NULL')) {
    $fail('Migration must seed global order_created configs (#811)');
}

fwrite(STDOUT, "OK OrderCreatedNotificationContractTest (#811)\n");
exit(0);
