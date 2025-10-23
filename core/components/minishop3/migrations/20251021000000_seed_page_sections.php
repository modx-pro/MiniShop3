<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed data for ms3_page_sections table
 * Inserts default sections for product_data page
 */
class SeedPageSections extends AbstractMigration
{
    /**
     * Migrate Up - Insert default page sections
     */
    public function up()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Check if table exists (Phinx adds prefix automatically)
        if (!$this->hasTable('ms3_page_sections')) {
            $this->output->writeln("<error>Table {$prefix}ms3_page_sections does not exist. Run initial_schema migration first.</error>");
            return;
        }

        $table = $this->table('ms3_page_sections');

        // Check if data already exists
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_page_sections WHERE page_key = 'product_data'");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Page sections already exist, skipping</comment>');
            return;
        }

        $data = [
            [
                'page_key' => 'product_data',
                'section_key' => 'main',
                'hidden' => 0,
                'sort_order' => 10,
                'config' => '{"lexicon_key":"ms3_section_main"}',
                'is_default' => 1,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'pricing',
                'hidden' => 0,
                'sort_order' => 20,
                'config' => '{"lexicon_key":"ms3_section_pricing"}',
                'is_default' => 0,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'seo',
                'hidden' => 0,
                'sort_order' => 30,
                'config' => '{"lexicon_key":"ms3_section_seo"}',
                'is_default' => 0,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'additional',
                'hidden' => 0,
                'sort_order' => 40,
                'config' => '{"lexicon_key":"ms3_section_additional"}',
                'is_default' => 0,
            ],
        ];

        $table->insert($data)->save();

        $this->output->writeln('<info>✓ Inserted ' . count($data) . ' page sections for product_data</info>');
    }

    /**
     * Migrate Down - Remove seeded data
     */
    public function down()
    {
        $prefix = $this->adapter->getOption('table_prefix');
        $this->execute("DELETE FROM {$prefix}ms3_page_sections WHERE page_key = 'product_data' AND is_default = 1");
        $this->output->writeln('<info>✓ Removed default page sections</info>');
    }
}
