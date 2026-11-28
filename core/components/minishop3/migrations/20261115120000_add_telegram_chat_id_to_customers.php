<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add telegram_chat_id to msCustomer for issue #900.
 *
 * Guest customers (without linked modUser) need a native column
 * since they have no modUserProfile.extended to store the chat ID.
 */
final class AddTelegramChatIdToCustomers extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_customers')) {
            return;
        }

        $table = $this->table('ms3_customers');

        if (!$table->hasColumn('telegram_chat_id')) {
            $table->addColumn('telegram_chat_id', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'privacy_ip',
            ])->save();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_customers')) {
            return;
        }

        $table = $this->table('ms3_customers');

        if ($table->hasColumn('telegram_chat_id')) {
            $table->removeColumn('telegram_chat_id')->save();
        }
    }
}
