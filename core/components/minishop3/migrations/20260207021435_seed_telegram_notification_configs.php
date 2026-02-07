<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed Telegram notification configurations for order status changes
 *
 * Creates telegram notifications for managers (disabled by default):
 * - New order
 * - Paid order
 *
 * Customer notifications are NOT created because Telegram bots cannot
 * initiate conversations - customers must first message the bot and
 * have their chat_id stored in their profile. This requires custom integration.
 *
 * To enable notifications:
 * 1. Configure ms3_telegram_bot_token
 * 2. Configure ms3_telegram_manager (comma-separated chat IDs)
 * 3. Enable desired notifications in Notification Center
 */
final class SeedTelegramNotificationConfigs extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $this->table('ms3_notification_configs');

        // Check if telegram notifications already exist
        $count = $this->fetchRow(
            "SELECT COUNT(*) as cnt FROM {$prefix}ms3_notification_configs WHERE channel = 'telegram'"
        );
        if ($count && $count['cnt'] > 0) {
            $this->output->writeln('<comment>Telegram notification configs already exist, skipping</comment>');
            return;
        }

        // Get status IDs from database
        $statuses = $this->getStatusIds($prefix);

        if (empty($statuses)) {
            $this->output->writeln('<error>No order statuses found. Run seed_order_statuses migration first.</error>');
            return;
        }

        // Get max position from existing configs
        $maxPos = $this->fetchRow("SELECT MAX(position) as pos FROM {$prefix}ms3_notification_configs");
        $position = ($maxPos && $maxPos['pos']) ? (int)$maxPos['pos'] + 1 : 100;

        $data = [];

        // New order - manager notification
        if (isset($statuses['new'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['new'],
                'recipient_type' => 'manager',
                'channel' => 'telegram',
                'enabled' => 0,  // Disabled by default
                'subject' => null,
                'template' => null,  // Uses built-in message from StatusChangedNotification
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        // Paid order - manager notification
        if (isset($statuses['paid'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['paid'],
                'recipient_type' => 'manager',
                'channel' => 'telegram',
                'enabled' => 0,
                'subject' => null,
                'template' => null,
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        if (!empty($data)) {
            $table->insert($data)->saveData();
            $this->output->writeln('<info>✓ Inserted ' . count($data) . ' Telegram notification configs (disabled by default)</info>');
        }
    }

    public function down(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');

        $this->execute("DELETE FROM {$prefix}ms3_notification_configs WHERE channel = 'telegram'");

        $this->output->writeln('<info>✓ Removed Telegram notification configs</info>');
    }

    /**
     * Get status IDs by name from database
     */
    private function getStatusIds(string $prefix): array
    {
        $statuses = [];

        $statusMap = [
            'new' => 'ms3_order_status_new',
            'paid' => 'ms3_order_status_paid',
        ];

        foreach ($statusMap as $key => $name) {
            $row = $this->fetchRow("SELECT id FROM {$prefix}ms3_order_statuses WHERE name = '{$name}'");
            if ($row && isset($row['id'])) {
                $statuses[$key] = (int)$row['id'];
            }
        }

        return $statuses;
    }
}
