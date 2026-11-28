<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add FK ms3_orders.customer_id → ms3_customers.id (#991).
 *
 * Self-contained: no MiniShop3 imports (transport Phinx may run early).
 *
 * Legacy writes stored customer_id as 0 for guests; 0 is not a valid autoincrement
 * id and is converted to NULL. Real orphaned ids are NOT silently detached: they
 * are reported and the migration aborts so a human can decide what to do with
 * the historical attribution. DELETE uses RESTRICT (not SET NULL) so deleting a
 * customer can never silently strip attribution from their orders.
 *
 * The down() method only drops the constraint; it cannot restore NULLed sentinel
 * values or the original NOT NULL column definition. Treat this migration as
 * effectively irreversible for data.
 */
final class AddFkOrderCustomer extends AbstractMigration
{
    private const CONSTRAINT = 'fk_orders_customer';

    public function up(): void
    {
        if (!$this->hasTable('ms3_orders') || !$this->hasTable('ms3_customers')) {
            throw new RuntimeException(
                'Cannot add ' . self::CONSTRAINT . ': ms3_orders and ms3_customers must both exist'
            );
        }

        $table = $this->table('ms3_orders');
        if ($table->hasForeignKey('customer_id', self::CONSTRAINT)) {
            return;
        }

        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $orders = $prefix . 'ms3_orders';
        $customers = $prefix . 'ms3_customers';

        // Guests must be representable as NULL, so allow NULL before cleaning legacy data.
        $this->execute("ALTER TABLE `{$orders}` MODIFY COLUMN `customer_id` INT(10) UNSIGNED NULL DEFAULT NULL");

        // Legacy sentinel 0 is not a customer row. Report the affected order ids rather
        // than rewriting them silently.
        $zeros = $this->fetchAll(
            "SELECT `id` FROM `{$orders}` WHERE `customer_id` = 0 ORDER BY `id`"
        );
        if ($zeros !== []) {
            $ids = array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $zeros);
            $this->output->writeln(sprintf(
                '<comment>NULLing legacy ms3_orders.customer_id=0 for %d order(s): %s</comment>',
                count($zeros),
                implode(', ', $ids)
            ));
            $this->execute("UPDATE `{$orders}` SET `customer_id` = NULL WHERE `customer_id` = 0");
        }

        // Any other dangling id is real attribution: do not destroy it, abort instead.
        $orphans = $this->fetchAll(
            "SELECT o.`id`, o.`customer_id` FROM `{$orders}` o"
            . " LEFT JOIN `{$customers}` c ON c.`id` = o.`customer_id`"
            . " WHERE o.`customer_id` IS NOT NULL AND c.`id` IS NULL"
            . " ORDER BY o.`id`"
        );

        if ($orphans !== []) {
            $details = [];
            foreach ($orphans as $row) {
                $details[] = sprintf(
                    'order_id=%s customer_id=%s',
                    (string) ($row['id'] ?? ''),
                    (string) ($row['customer_id'] ?? '')
                );
            }

            throw new RuntimeException(
                'Cannot add ' . self::CONSTRAINT . ': orphan ms3_orders.customer_id values must be fixed first: '
                . implode('; ', $details)
            );
        }

        $table->addForeignKey('customer_id', 'ms3_customers', 'id', [
            'delete' => 'RESTRICT',
            'update' => 'CASCADE',
            'constraint' => self::CONSTRAINT,
        ])->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_orders')) {
            return;
        }

        $table = $this->table('ms3_orders');
        if (!$table->hasForeignKey('customer_id', self::CONSTRAINT)) {
            return;
        }

        $table->dropForeignKey('customer_id', self::CONSTRAINT)->update();

        // Irreversible: NULLed legacy 0 values and the NOT NULL definition are not restored.
        $this->output->writeln(
            '<comment>' . self::CONSTRAINT . ' dropped; NULLed customer_id values are not restored.</comment>'
        );
    }
}
