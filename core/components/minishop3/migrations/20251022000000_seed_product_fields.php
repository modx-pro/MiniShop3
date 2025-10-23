<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed data for ms3_product_fields table
 * Inserts default product fields with section IDs
 */
class SeedProductFields extends AbstractMigration
{
    /**
     * Migrate Up - Insert default product fields
     */
    public function up()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Check if tables exist (Phinx adds prefix automatically)
        if (!$this->hasTable('ms3_product_fields')) {
            $this->output->writeln("<error>Table {$prefix}ms3_product_fields does not exist. Run initial_schema migration first.</error>");
            return;
        }
        if (!$this->hasTable('ms3_page_sections')) {
            $this->output->writeln("<error>Table {$prefix}ms3_page_sections does not exist. Run seed_page_sections first.</error>");
            return;
        }

        $table = $this->table('ms3_product_fields');

        // Check if data already exists
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_product_fields");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Product fields already exist, skipping</comment>');
            return;
        }

        // Get section IDs
        $sections = $this->fetchAll("SELECT id, section_key FROM {$prefix}ms3_page_sections WHERE page_key = 'product_data'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        if (empty($sectionMap)) {
            $this->output->writeln('<error>No page sections found! Run seed_page_sections first.</error>');
            return;
        }

        $data = [
            // Pricing section
            [
                'name' => 'price',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => $sectionMap['pricing'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 10,
                'width' => 6,
                'config' => '{"decimalPrecision":2}',
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'old_price',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => $sectionMap['pricing'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 20,
                'width' => 6,
                'config' => '{"decimalPrecision":2}',
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'stock',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => $sectionMap['pricing'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 30,
                'width' => 6,
                'config' => '{"decimalPrecision":0,"allowNegative":false}',
                'is_system' => 0,
                'is_default' => 1,
            ],

            // Main section
            [
                'name' => 'article',
                'label' => null,
                'xtype' => 'textfield',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 40,
                'width' => 6,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'weight',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 50,
                'width' => 6,
                'config' => '{"decimalPrecision":3}',
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'color',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 60,
                'width' => 6,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'size',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 70,
                'width' => 6,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'vendor_id',
                'label' => null,
                'xtype' => 'ms3-combo-vendor',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 80,
                'width' => 6,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'made_in',
                'label' => null,
                'xtype' => 'ms3-combo-autocomplete',
                'section' => $sectionMap['main'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 90,
                'width' => 6,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],

            // Additional section
            [
                'name' => 'tags',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => $sectionMap['additional'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 100,
                'width' => 12,
                'config' => null,
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'new',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => $sectionMap['additional'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 110,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'favorite',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => $sectionMap['additional'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 120,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => 0,
                'is_default' => 1,
            ],
            [
                'name' => 'popular',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => $sectionMap['additional'],
                'visible' => 1,
                'required' => 0,
                'sort_order' => 130,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => 0,
                'is_default' => 1,
            ],
        ];

        $table->insert($data)->save();

        $this->output->writeln('<info>✓ Inserted ' . count($data) . ' default product fields</info>');
    }

    /**
     * Migrate Down - Remove seeded data
     */
    public function down()
    {
        $prefix = $this->adapter->getOption('table_prefix');
        $this->execute("DELETE FROM {$prefix}ms3_product_fields WHERE is_default = 1");
        $this->output->writeln('<info>✓ Removed default product fields</info>');
    }
}
