<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedModelFieldSections extends AbstractMigration
{
    private string $prefix;

    public function up(): void
    {
        $this->prefix = $this->adapter->getOption('table_prefix') ?? '';

        $sectionsTable = $this->table('ms3_model_field_sections');

        // msOrder sections
        $orderSections = [
            [
                'model' => 'msOrder',
                'section_key' => 'main',
                'label' => null,
                'lexicon_key' => 'ms3_section_order_main',
                'hidden' => false,
                'sort_order' => 10,
                'is_default' => true,
            ],
            [
                'model' => 'msOrder',
                'section_key' => 'other',
                'label' => null,
                'lexicon_key' => 'ms3_section_order_other',
                'hidden' => false,
                'sort_order' => 100,
                'is_default' => true,
            ],
        ];

        // msOrderAddress sections
        $addressSections = [
            [
                'model' => 'msOrderAddress',
                'section_key' => 'contact',
                'label' => null,
                'lexicon_key' => 'ms3_section_address_contact',
                'hidden' => false,
                'sort_order' => 10,
                'is_default' => true,
            ],
            [
                'model' => 'msOrderAddress',
                'section_key' => 'location',
                'label' => null,
                'lexicon_key' => 'ms3_section_address_location',
                'hidden' => false,
                'sort_order' => 20,
                'is_default' => true,
            ],
            [
                'model' => 'msOrderAddress',
                'section_key' => 'building',
                'label' => null,
                'lexicon_key' => 'ms3_section_address_building',
                'hidden' => false,
                'sort_order' => 30,
                'is_default' => true,
            ],
            [
                'model' => 'msOrderAddress',
                'section_key' => 'other',
                'label' => null,
                'lexicon_key' => 'ms3_section_address_other',
                'hidden' => false,
                'sort_order' => 100,
                'is_default' => true,
            ],
        ];

        $allSections = array_merge($orderSections, $addressSections);
        $sectionsTable->insert($allSections)->saveData();

        // Now update msOrder fields with section_id and width
        $this->updateOrderFieldSections();

        // Update msOrderAddress fields with section_id and width
        $this->updateAddressFieldSections();
    }

    private function updateOrderFieldSections(): void
    {
        // Get section IDs
        $sections = $this->fetchAll("SELECT id, section_key FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrder'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        // Update fields with section assignments and widths
        $fieldUpdates = [
            // Main section - 50% width each
            ['name' => 'status_id', 'section' => 'main', 'width' => 6],
            ['name' => 'delivery_id', 'section' => 'main', 'width' => 6],
            ['name' => 'payment_id', 'section' => 'main', 'width' => 6],
            // Other section - full width
            ['name' => 'order_comment', 'section' => 'other', 'width' => 12],
        ];

        foreach ($fieldUpdates as $update) {
            $sectionId = $sectionMap[$update['section']] ?? null;
            if ($sectionId) {
                $this->execute(
                    "UPDATE {$this->prefix}ms3_model_fields SET section_id = {$sectionId}, width = {$update['width']} " .
                    "WHERE model = 'msOrder' AND name = '{$update['name']}'"
                );
            }
        }
    }

    private function updateAddressFieldSections(): void
    {
        // Get section IDs
        $sections = $this->fetchAll("SELECT id, section_key FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrderAddress'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        // Update fields with section assignments and widths
        $fieldUpdates = [
            // Contact section
            ['name' => 'first_name', 'section' => 'contact', 'width' => 6],
            ['name' => 'last_name', 'section' => 'contact', 'width' => 6],
            ['name' => 'phone', 'section' => 'contact', 'width' => 6],
            ['name' => 'email', 'section' => 'contact', 'width' => 6],
            // Location section
            ['name' => 'country', 'section' => 'location', 'width' => 4],
            ['name' => 'index', 'section' => 'location', 'width' => 4],
            ['name' => 'region', 'section' => 'location', 'width' => 4],
            ['name' => 'city', 'section' => 'location', 'width' => 6],
            ['name' => 'metro', 'section' => 'location', 'width' => 6],
            ['name' => 'street', 'section' => 'location', 'width' => 12],
            // Building section
            ['name' => 'building', 'section' => 'building', 'width' => 4],
            ['name' => 'entrance', 'section' => 'building', 'width' => 2],
            ['name' => 'floor', 'section' => 'building', 'width' => 2],
            ['name' => 'room', 'section' => 'building', 'width' => 4],
            // Other section
            ['name' => 'comment', 'section' => 'other', 'width' => 12],
            ['name' => 'text_address', 'section' => 'other', 'width' => 12],
        ];

        foreach ($fieldUpdates as $update) {
            $sectionId = $sectionMap[$update['section']] ?? null;
            if ($sectionId) {
                $this->execute(
                    "UPDATE {$this->prefix}ms3_model_fields SET section_id = {$sectionId}, width = {$update['width']} " .
                    "WHERE model = 'msOrderAddress' AND name = '{$update['name']}'"
                );
            }
        }
    }

    public function down(): void
    {
        $prefix = $this->adapter->getOption('table_prefix') ?? '';

        // Reset section_id and width in fields
        $this->execute("UPDATE {$prefix}ms3_model_fields SET section_id = NULL, width = 6 WHERE model IN ('msOrder', 'msOrderAddress')");

        // Delete sections
        $this->execute("DELETE FROM {$prefix}ms3_model_field_sections WHERE model IN ('msOrder', 'msOrderAddress')");
    }
}
