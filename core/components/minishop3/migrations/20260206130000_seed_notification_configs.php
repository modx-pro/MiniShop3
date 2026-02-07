<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed default notification configurations for order status changes
 *
 * Creates email notifications for:
 * - New order (customer + manager)
 * - Paid order (customer + manager)
 * - Sent order (customer)
 * - Cancelled order (customer)
 */
final class SeedNotificationConfigs extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $this->table('ms3_notification_configs');

        // Check if data already exists
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_notification_configs");
        if ($count && $count['cnt'] > 0) {
            $this->output->writeln('<comment>Notification configs already exist, skipping</comment>');
            return;
        }

        // Get status IDs from database
        $statuses = $this->getStatusIds($prefix);

        if (empty($statuses)) {
            $this->output->writeln('<error>No order statuses found. Run seed_order_statuses migration first.</error>');
            return;
        }

        $data = [];
        $position = 0;

        // New order - customer
        if (isset($statuses['new'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['new'],
                'recipient_type' => 'customer',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Заказ #{$order.num} оформлен',
                'template' => 'tpl.msEmail.new.customer',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];

            // New order - manager
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['new'],
                'recipient_type' => 'manager',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Новый заказ #{$order.num}',
                'template' => 'tpl.msEmail.new.manager',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        // Paid order - customer
        if (isset($statuses['paid'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['paid'],
                'recipient_type' => 'customer',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Заказ #{$order.num} оплачен',
                'template' => 'tpl.msEmail.paid.customer',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];

            // Paid order - manager
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['paid'],
                'recipient_type' => 'manager',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Заказ #{$order.num} оплачен',
                'template' => 'tpl.msEmail.paid.manager',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        // Sent order - customer
        if (isset($statuses['sent'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['sent'],
                'recipient_type' => 'customer',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Заказ #{$order.num} отправлен',
                'template' => 'tpl.msEmail.sent.customer',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        // Cancelled order - customer
        if (isset($statuses['cancelled'])) {
            $data[] = [
                'event' => 'order_status_changed',
                'status_id' => $statuses['cancelled'],
                'recipient_type' => 'customer',
                'channel' => 'email',
                'enabled' => 1,
                'subject' => 'Заказ #{$order.num} отменён',
                'template' => 'tpl.msEmail.cancelled.customer',
                'delay' => 0,
                'config' => null,
                'position' => $position++,
            ];
        }

        if (!empty($data)) {
            $table->insert($data)->saveData();
            $this->output->writeln('<info>✓ Inserted ' . count($data) . ' default notification configs</info>');
        }
    }

    public function down(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');

        // Remove only seeded records (event = 'order_status_changed')
        $this->execute("DELETE FROM {$prefix}ms3_notification_configs WHERE event = 'order_status_changed'");

        $this->output->writeln('<info>✓ Removed default notification configs</info>');
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
            'sent' => 'ms3_order_status_sent',
            'cancelled' => 'ms3_order_status_cancelled',
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
