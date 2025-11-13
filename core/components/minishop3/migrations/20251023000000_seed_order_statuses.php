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

        $data = [
            [
                'id' => 1,
                'name' => 'Draft',  // Will be replaced with lexicon in MODX
                'color' => 'C0C0C0',
                'email_user' => 0,
                'email_manager' => 0,
                'subject_user' => '',
                'subject_manager' => '',
                'body_user' => '',
                'body_manager' => '',
                'final' => 0,
                'fixed' => 0,
                'editable' => 0,
                'active' => 1,
                'position' => 0,
            ],
            [
                'id' => 2,
                'name' => 'New',
                'color' => '000000',
                'email_user' => 1,
                'email_manager' => 1,
                'subject_user' => '[[%ms3_email_subject_new_user]]',
                'subject_manager' => '[[%ms3_email_subject_new_manager]]',
                'body_user' => 'tpl.msEmail.new.user',
                'body_manager' => 'tpl.msEmail.new.manager',
                'final' => 0,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Paid',
                'color' => '008000',
                'email_user' => 1,
                'email_manager' => 1,
                'subject_user' => '[[%ms3_email_subject_paid_user]]',
                'subject_manager' => '[[%ms3_email_subject_paid_manager]]',
                'body_user' => 'tpl.msEmail.paid.user',
                'body_manager' => 'tpl.msEmail.paid.manager',
                'final' => 0,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 2,
            ],
            [
                'id' => 4,
                'name' => 'Sent',
                'color' => '003366',
                'email_user' => 1,
                'email_manager' => 0,
                'subject_user' => '[[%ms3_email_subject_sent_user]]',
                'subject_manager' => '',
                'body_user' => 'tpl.msEmail.sent.user',
                'body_manager' => '',
                'final' => 1,
                'fixed' => 1,
                'editable' => 0,
                'active' => 1,
                'position' => 3,
            ],
            [
                'id' => 5,
                'name' => 'Cancelled',
                'color' => '800000',
                'email_user' => 1,
                'email_manager' => 0,
                'subject_user' => '[[%ms3_email_subject_cancelled_user]]',
                'subject_manager' => '',
                'body_user' => 'tpl.msEmail.cancelled.user',
                'body_manager' => '',
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
        $this->output->writeln('<comment>Note: Email template chunks need to be created separately</comment>');

        // Update system settings with status IDs
        $this->updateSystemSettings($prefix);
    }

    /**
     * Update system settings with default status IDs
     *
     * @param string $prefix Table prefix
     */
    protected function updateSystemSettings(string $prefix)
    {
        $this->output->writeln('<info>Updating system settings with status IDs...</info>');

        // Map of setting keys to status IDs
        $settingsMap = [
            'ms3_status_draft' => 1,      // Draft
            'ms3_status_new' => 2,        // New
            'ms3_status_paid' => 3,       // Paid
            'ms3_status_sent' => 4,       // Sent
            'ms3_status_canceled' => 5,   // Cancelled (note: canceled, not cancelled in setting name)
        ];

        foreach ($settingsMap as $settingKey => $statusId) {
            // Check if setting exists
            $setting = $this->fetchRow(
                "SELECT id, value FROM {$prefix}system_settings WHERE `key` = ?",
                [$settingKey]
            );

            if ($setting) {
                // Update existing setting
                $this->execute(
                    "UPDATE {$prefix}system_settings SET value = ? WHERE `key` = ?",
                    [$statusId, $settingKey]
                );
                $this->output->writeln("<info>  ✓ Updated {$settingKey} = {$statusId}</info>");
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

        // Reset system settings to 0 (or you can set to NULL)
        $settingKeys = [
            'ms3_status_draft',
            'ms3_status_new',
            'ms3_status_paid',
            'ms3_status_sent',
            'ms3_status_canceled',
        ];

        foreach ($settingKeys as $settingKey) {
            $this->execute(
                "UPDATE {$prefix}system_settings SET value = '0' WHERE `key` = ?",
                [$settingKey]
            );
        }

        $this->output->writeln('<info>✓ Reset system settings for order statuses</info>');

        // Remove status records
        $this->execute("DELETE FROM {$prefix}ms3_order_statuses WHERE id IN (1,2,3,4,5)");
        $this->output->writeln('<info>✓ Removed default order statuses</info>');
    }
}
