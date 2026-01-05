<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed data for ms3_order_statuses table
 * Inserts default order statuses
 * Converted from _build/resolvers/resolver_04_statuses.php
 */
class SeedOrderStatuses extends AbstractMigration
{
    /**
     * Migrate Up - Insert default order statuses
     */
    public function up()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Check if table exists (Phinx adds prefix automatically)
        if (!$this->hasTable('ms3_order_statuses')) {
            $this->output->writeln("<error>Table {$prefix}ms3_order_statuses does not exist. Run initial_schema migration first.</error>");
            return;
        }

        $table = $this->table('ms3_order_statuses');

        // Check if data already exists
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_order_statuses");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Order statuses already exist, skipping</comment>');
            return;
        }

        // Status names are lexicon keys - will be translated in admin and frontend
        // User can override with custom names via admin panel
        // Note: Email notification fields were moved to a separate notification system
        $data = [
            [
                'id' => 1,
                'name' => 'ms3_order_status_draft',
                'color' => 'C0C0C0',
                'final' => 0,
                'fixed' => 0,
                'editable' => 0,
                'active' => 1,
                'position' => 0,
            ],
            [
                'id' => 2,
                'name' => 'ms3_order_status_new',
                'color' => '000000',
                'final' => 0,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 1,
            ],
            [
                'id' => 3,
                'name' => 'ms3_order_status_paid',
                'color' => '008000',
                'final' => 0,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 2,
            ],
            [
                'id' => 4,
                'name' => 'ms3_order_status_sent',
                'color' => '003366',
                'final' => 1,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 3,
            ],
            [
                'id' => 5,
                'name' => 'ms3_order_status_cancelled',
                'color' => '800000',
                'final' => 1,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 4,
            ],
        ];

        $table->insert($data)->save();

        $this->output->writeln('<info>✓ Inserted ' . count($data) . ' default order statuses</info>');
        $this->output->writeln('<comment>Note: Status names will be replaced with lexicon values when accessed through MODX</comment>');

        // Update system settings with status IDs
        $this->updateSystemSettings($prefix);
    }

    /**
     * Update system settings with default status IDs
     * Looks up actual IDs from database by status name
     *
     * @param string $prefix Table prefix
     */
    protected function updateSystemSettings(string $prefix)
    {
        $this->output->writeln('<info>Updating system settings with status IDs...</info>');

        // Map of setting keys to status names in database
        // Setting key => status name in ms3_order_statuses.name
        $settingsMap = [
            'ms3_status_new' => 'ms3_order_status_new',
            'ms3_status_paid' => 'ms3_order_status_paid',
            'ms3_status_canceled' => 'ms3_order_status_cancelled', // Note: setting uses 'canceled', status uses 'cancelled'
        ];

        foreach ($settingsMap as $settingKey => $statusName) {
            // Get actual status ID from database by name
            $sql = "SELECT `id` FROM {$prefix}ms3_order_statuses WHERE `name` = '{$statusName}'";
            $statusRow = $this->fetchRow($sql);

            if (!$statusRow || !isset($statusRow['id'])) {
                $this->output->writeln("<comment>  ⚠ Status '{$statusName}' not found in database, skipping</comment>");
                continue;
            }

            $statusId = (int) $statusRow['id'];

            // Check if setting exists
            $sql = "SELECT `key`, `value` FROM {$prefix}system_settings WHERE `key` = '{$settingKey}'";
            $result = $this->fetchRow($sql);

            if ($result && isset($result['key'])) {
                // Update existing setting with actual ID
                $updateSql = "UPDATE {$prefix}system_settings SET `value` = '{$statusId}' WHERE `key` = '{$settingKey}'";
                $this->execute($updateSql);
                $this->output->writeln("<info>  ✓ Updated {$settingKey} = {$statusId} (from {$statusName})</info>");
            } else {
                $this->output->writeln("<comment>  ⚠ Setting {$settingKey} not found, skipping</comment>");
            }
        }

        $this->output->writeln('<info>✓ System settings updated successfully</info>');
    }

    /**
     * Migrate Down - Remove seeded data
     */
    public function down()
    {
        $prefix = $this->adapter->getOption('table_prefix');
        $pdo = $this->getAdapter()->getConnection();

        // Reset system settings to 0
        $settingKeys = [
            'ms3_status_new',
            'ms3_status_paid',
            'ms3_status_canceled',
        ];

        foreach ($settingKeys as $settingKey) {
            $stmt = $pdo->prepare("UPDATE {$prefix}system_settings SET value = '0' WHERE `key` = :setting_key");
            $stmt->execute(['setting_key' => $settingKey]);
        }

        $this->output->writeln('<info>✓ Reset system settings for order statuses</info>');

        // Remove status records by name (more reliable than by ID)
        $statusNames = [
            'ms3_order_status_draft',
            'ms3_order_status_new',
            'ms3_order_status_paid',
            'ms3_order_status_sent',
            'ms3_order_status_cancelled',
        ];

        $namesStr = "'" . implode("','", $statusNames) . "'";
        $this->execute("DELETE FROM {$prefix}ms3_order_statuses WHERE name IN ({$namesStr})");
        $this->output->writeln('<info>✓ Removed default order statuses</info>');
    }
}
