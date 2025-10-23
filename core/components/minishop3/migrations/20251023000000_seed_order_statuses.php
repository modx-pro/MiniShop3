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
    }

    /**
     * Migrate Down - Remove seeded data
     */
    public function down()
    {
        $prefix = $this->adapter->getOption('table_prefix');
        $this->execute("DELETE FROM {$prefix}ms3_order_statuses WHERE id IN (1,2,3,4,5)");
        $this->output->writeln('<info>✓ Removed default order statuses</info>');
    }
}
