<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Unique nullable idempotency_key on ms3_orders for programmatic create (#507).
 */
final class AddOrderIdempotencyKey extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_orders');

        if (!$table->hasColumn('idempotency_key')) {
            $table->addColumn('idempotency_key', 'string', [
                'limit' => 128,
                'null' => true,
                'default' => null,
                'after' => 'uuid',
            ])->update();
        }

        if (!$table->hasIndexByName('idempotency_key')) {
            $table->addIndex(['idempotency_key'], [
                'unique' => true,
                'name' => 'idempotency_key',
            ])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_orders');

        if ($table->hasIndexByName('idempotency_key')) {
            $table->removeIndexByName('idempotency_key')->update();
        }

        if ($table->hasColumn('idempotency_key')) {
            $table->removeColumn('idempotency_key')->update();
        }
    }
}
