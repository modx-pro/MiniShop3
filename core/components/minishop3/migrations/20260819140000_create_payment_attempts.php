<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Payment attempts and idempotent webhook events (#590).
 */
final class CreatePaymentAttempts extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('ms3_payment_attempts')) {
            $this->table('ms3_payment_attempts', [
                'id' => true,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
                ->addColumn('order_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('payment_method_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('provider', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('external_id', 'string', ['limit' => 191, 'null' => true, 'default' => null])
                ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'pending'])
                ->addColumn('amount', 'decimal', ['precision' => 13, 'scale' => 3, 'null' => false, 'default' => '0.000'])
                ->addColumn('currency', 'string', ['limit' => 8, 'null' => false, 'default' => 'RUB'])
                ->addColumn('payload', 'text', ['null' => true])
                ->addColumn('refunded_amount', 'decimal', ['precision' => 13, 'scale' => 3, 'null' => false, 'default' => '0.000'])
                ->addColumn('refund_external_id', 'string', ['limit' => 191, 'null' => true, 'default' => null])
                ->addColumn('refundedon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
                ->addColumn('createdon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
                ->addColumn('updatedon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
                ->addIndex(['order_id'], ['name' => 'idx_payment_attempt_order'])
                ->addIndex(['payment_method_id', 'provider', 'external_id'], [
                    'unique' => true,
                    'name' => 'uniq_payment_attempt_external',
                ])
                ->create();
        }

        if (!$this->hasTable('ms3_payment_attempt_events')) {
            $this->table('ms3_payment_attempt_events', [
                'id' => true,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
                ->addColumn('attempt_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('event_type', 'string', ['limit' => 32, 'null' => false])
                ->addColumn('provider_event_id', 'string', ['limit' => 191, 'null' => false, 'default' => ''])
                ->addColumn('createdon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
                ->addIndex(
                    ['attempt_id', 'event_type', 'provider_event_id'],
                    ['unique' => true, 'name' => 'uniq_payment_attempt_event']
                )
                ->create();
        }
    }
}
