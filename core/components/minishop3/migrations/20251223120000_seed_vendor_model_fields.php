<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration to seed msVendor model fields and sections
 */
final class SeedVendorModelFields extends AbstractMigration
{
    private string $prefix;

    public function up(): void
    {
        $this->prefix = $this->adapter->getOption('table_prefix') ?? '';

        // First, create sections for msVendor
        $this->createVendorSections();

        // Then, create fields for msVendor
        $this->createVendorFields();

        // Finally, assign fields to sections
        $this->assignFieldsToSections();
    }

    private function createVendorSections(): void
    {
        $sectionsTable = $this->table('ms3_model_field_sections');

        $vendorSections = [
            [
                'model' => 'msVendor',
                'section_key' => 'info',
                'label' => null,
                'lexicon_key' => 'ms3_section_vendor_info',
                'hidden' => false,
                'rank' => 10,
                'is_default' => true,
            ],
            [
                'model' => 'msVendor',
                'section_key' => 'address',
                'label' => null,
                'lexicon_key' => 'ms3_section_vendor_address',
                'hidden' => false,
                'rank' => 20,
                'is_default' => true,
            ],
        ];

        $sectionsTable->insert($vendorSections)->saveData();
    }

    private function createVendorFields(): void
    {
        $fieldsTable = $this->table('ms3_model_fields');

        $vendorFields = [
            // Info section fields
            ['model' => 'msVendor', 'name' => 'name', 'label' => 'ms3_vendor_name', 'xtype' => 'textfield', 'visible' => true, 'required' => true, 'rank' => 10],
            ['model' => 'msVendor', 'name' => 'description', 'label' => 'ms3_vendor_description', 'xtype' => 'textarea', 'visible' => true, 'required' => false, 'rank' => 20],
            ['model' => 'msVendor', 'name' => 'logo', 'label' => 'ms3_vendor_logo', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'rank' => 30],
            ['model' => 'msVendor', 'name' => 'country', 'label' => 'ms3_vendor_country', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'rank' => 40],
            ['model' => 'msVendor', 'name' => 'resource_id', 'label' => 'ms3_vendor_resource_id', 'xtype' => 'numberfield', 'visible' => true, 'required' => false, 'rank' => 50],
                // Address section fields
            ['model' => 'msVendor', 'name' => 'address', 'label' => 'ms3_vendor_address', 'xtype' => 'textarea', 'visible' => true, 'required' => false, 'rank' => 70],
            ['model' => 'msVendor', 'name' => 'phone', 'label' => 'ms3_vendor_phone', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'rank' => 80],
            ['model' => 'msVendor', 'name' => 'email', 'label' => 'ms3_vendor_email', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'rank' => 90],
        ];

        $fieldsTable->insert($vendorFields)->saveData();
    }

    private function assignFieldsToSections(): void
    {
        // Get section IDs for msVendor
        $sections = $this->fetchAll("SELECT id, section_key FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msVendor'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        // Update fields with section assignments and widths
        $fieldUpdates = [
            // Info section
            ['name' => 'name', 'section' => 'info', 'width' => 6],
            ['name' => 'country', 'section' => 'info', 'width' => 6],
            ['name' => 'description', 'section' => 'info', 'width' => 12],
            ['name' => 'logo', 'section' => 'info', 'width' => 6],
            ['name' => 'resource_id', 'section' => 'info', 'width' => 3],
            ['name' => 'position', 'section' => 'info', 'width' => 3],
            // Address section
            ['name' => 'address', 'section' => 'address', 'width' => 12],
            ['name' => 'phone', 'section' => 'address', 'width' => 6],
            ['name' => 'email', 'section' => 'address', 'width' => 6],
            // Properties - no section
            ['name' => 'properties', 'section' => null, 'width' => 12],
        ];

        foreach ($fieldUpdates as $update) {
            $sectionId = $update['section'] ? ($sectionMap[$update['section']] ?? null) : null;
            $sectionIdSql = $sectionId ? $sectionId : 'NULL';
            $this->execute(
                "UPDATE {$this->prefix}ms3_model_fields SET section_id = {$sectionIdSql}, width = {$update['width']} " .
                "WHERE model = 'msVendor' AND name = '{$update['name']}'"
            );
        }
    }

    public function down(): void
    {
        $this->prefix = $this->adapter->getOption('table_prefix') ?? '';

        // Delete fields for msVendor
        $this->execute("DELETE FROM {$this->prefix}ms3_model_fields WHERE model = 'msVendor'");

        // Delete sections for msVendor
        $this->execute("DELETE FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msVendor'");
    }
}
