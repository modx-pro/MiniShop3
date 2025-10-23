<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed data for ms3_deliveries, ms3_payments and ms3_delivery_members
 * Creates default delivery and payment methods
 * Converted from _build/resolvers/resolver_03_settings.php (partial)
 */
class SeedDeliveryAndPayment extends AbstractMigration
{
    /**
     * Migrate Up - Insert default delivery and payment
     */
    public function up()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Check if tables exist (Phinx adds prefix automatically)
        if (!$this->hasTable('ms3_deliveries')) {
            $this->output->writeln("<error>Table {$prefix}ms3_deliveries does not exist. Run initial_schema migration first.</error>");
            return;
        }
        if (!$this->hasTable('ms3_payments')) {
            $this->output->writeln("<error>Table {$prefix}ms3_payments does not exist. Run initial_schema migration first.</error>");
            return;
        }
        if (!$this->hasTable('ms3_delivery_payments')) {
            $this->output->writeln("<error>Table {$prefix}ms3_delivery_payments does not exist. Run initial_schema migration first.</error>");
            return;
        }

        // Check if delivery already exists
        $deliveryCount = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_deliveries WHERE id = 1");

        if ($deliveryCount['cnt'] == 0) {
            // Create default delivery: Self-pickup
            $this->execute("
                INSERT INTO {$prefix}ms3_deliveries
                (id, name, price, weight_price, distance_price, active, validation_rules, position)
                VALUES
                (1, 'Самовывоз', 0, 0, 0, 1, '{\"first_name\":\"required\",\"last_name\":\"required\", \"email\":\"required|email\"}', 0)
            ");
            $this->output->writeln('<info>✓ Created default delivery: Самовывоз</info>');
        } else {
            $this->output->writeln('<comment>Default delivery already exists, skipping</comment>');
        }

        // Check if payment already exists
        $paymentCount = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_payments WHERE id = 1");

        if ($paymentCount['cnt'] == 0) {
            // Create default payment: Cash
            $this->execute("
                INSERT INTO {$prefix}ms3_payments
                (id, name, active, position)
                VALUES
                (1, 'Наличные', 1, 0)
            ");
            $this->output->writeln('<info>✓ Created default payment: Наличные</info>');
        } else {
            $this->output->writeln('<comment>Default payment already exists, skipping</comment>');
        }

        // Check if delivery-payment link exists
        $memberCount = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_delivery_payments WHERE payment_id = 1 AND delivery_id = 1");

        if ($memberCount['cnt'] == 0) {
            // Create link between delivery and payment
            $this->execute("
                INSERT INTO {$prefix}ms3_delivery_payments
                (payment_id, delivery_id)
                VALUES
                (1, 1)
            ");
            $this->output->writeln('<info>✓ Linked delivery and payment</info>');
        } else {
            $this->output->writeln('<comment>Delivery-payment link already exists, skipping</comment>');
        }

        $this->output->writeln('<info>Default delivery and payment setup completed!</info>');
        $this->output->writeln('<comment>Note: Names are in Russian. For multi-language support, use lexicon in MODX.</comment>');
    }

    /**
     * Migrate Down - Remove seeded data
     */
    public function down()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Remove delivery-payment link
        $this->execute("DELETE FROM {$prefix}ms3_delivery_payments WHERE payment_id = 1 AND delivery_id = 1");

        // Remove payment
        $this->execute("DELETE FROM {$prefix}ms3_payments WHERE id = 1");

        // Remove delivery
        $this->execute("DELETE FROM {$prefix}ms3_deliveries WHERE id = 1");

        $this->output->writeln('<info>✓ Removed default delivery and payment</info>');
    }
}
