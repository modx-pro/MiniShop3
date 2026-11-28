<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seed global notification configs for the `order_created` event (#811).
 *
 * The event is dispatched when an order leaves draft status, so the config is
 * global (status_id = NULL): it must apply to whatever status the order enters.
 * Without these rows NotificationConfigService::getConfigs() returns nothing
 * and OrderCreatedNotification is silently never queued.
 */
final class SeedOrderCreatedNotificationConfigs extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_notification_configs')) {
            return;
        }

        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'ms3_notification_configs';

        foreach (['customer', 'manager'] as $recipientType) {
            $exists = $this->fetchRow(
                "SELECT `id` FROM `{$table}` WHERE `event` = 'order_created'"
                . " AND `recipient_type` = '{$recipientType}' AND `status_id` IS NULL LIMIT 1"
            );
            if (is_array($exists) && $exists !== []) {
                continue;
            }

            // Single-quoted PHP string keeps the Fenom placeholder literal.
            $subject = addslashes('Заказ #{$order.num} оформлен');
            $template = 'tpl.msEmail.new.' . $recipientType;
            $this->execute(
                "INSERT INTO `{$table}`"
                . " (`event`, `status_id`, `recipient_type`, `channel`, `enabled`, `subject`, `template`, `delay`, `config`, `position`)"
                . " VALUES ('order_created', NULL, '{$recipientType}', 'email', 1,"
                . " '{$subject}', '{$template}', 0, NULL, 0)"
            );
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_notification_configs')) {
            return;
        }

        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $this->execute(
            "DELETE FROM `{$prefix}ms3_notification_configs` WHERE `event` = 'order_created'"
        );
    }
}
