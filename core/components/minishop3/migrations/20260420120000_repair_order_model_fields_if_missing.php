<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Self-heal: re-seed msOrder / msOrderAddress model fields and sections when the initial
 * seed migrations did not run fully or the schema was partially emptied (manager shows ms3_model_fields_empty).
 */
final class RepairOrderModelFieldsIfMissing extends AbstractMigration
{
    private string $prefix = '';

    public function up(): void
    {
        if (!$this->hasTable('ms3_model_fields') || !$this->hasTable('ms3_model_field_sections')) {
            return;
        }

        $this->prefix = (string) ($this->adapter->getOption('table_prefix') ?? '');

        $needOrder = $this->needsOrderRepair();
        $needAddress = $this->needsAddressRepair();

        if (!$needOrder && !$needAddress) {
            return;
        }

        if ($needOrder) {
            $this->insertOrderSectionsIfMissing();
            $this->insertOrderFieldsIfMissing();
            $this->updateOrderFieldSections();
        }

        if ($needAddress) {
            $this->insertAddressSectionsIfMissing();
            $this->insertAddressFieldsIfMissing();
            $this->updateAddressFieldSections();
        }
    }

    public function down(): void
    {
        // Intentionally empty: repair is data correction; rolling back would remove restored defaults.
    }

    private function needsOrderRepair(): bool
    {
        foreach ($this->orderSeedFieldNames() as $name) {
            if (!$this->fieldExists('msOrder', $name)) {
                return true;
            }
        }

        return false;
    }

    private function needsAddressRepair(): bool
    {
        foreach ($this->addressSeedFieldNames() as $name) {
            if (!$this->fieldExists('msOrderAddress', $name)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function orderSeedFieldNames(): array
    {
        return ['status_id', 'delivery_id', 'payment_id', 'customer_id', 'order_comment'];
    }

    /** @return list<string> */
    private function addressSeedFieldNames(): array
    {
        return [
            'first_name', 'last_name', 'phone', 'email', 'country', 'index', 'region', 'city',
            'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment', 'text_address',
        ];
    }

    private function fieldExists(string $model, string $name): bool
    {
        $rows = $this->fetchAll(
            "SELECT id FROM {$this->prefix}ms3_model_fields WHERE model = '{$model}' AND name = '{$name}' LIMIT 1"
        );

        return $rows !== [];
    }

    private function insertOrderSectionsIfMissing(): void
    {
        $rows = [
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
        foreach ($rows as $row) {
            $key = $row['section_key'];
            $exists = $this->fetchAll(
                "SELECT id FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrder' AND section_key = '{$key}' LIMIT 1"
            );
            if ($exists !== []) {
                continue;
            }
            $this->table('ms3_model_field_sections')->insert([$row])->saveData();
        }
    }

    private function insertAddressSectionsIfMissing(): void
    {
        $rows = [
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
        foreach ($rows as $row) {
            $key = $row['section_key'];
            $exists = $this->fetchAll(
                "SELECT id FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrderAddress' AND section_key = '{$key}' LIMIT 1"
            );
            if ($exists !== []) {
                continue;
            }
            $this->table('ms3_model_field_sections')->insert([$row])->saveData();
        }
    }

    private function insertOrderFieldsIfMissing(): void
    {
        $orderFields = [
            ['model' => 'msOrder', 'name' => 'status_id', 'label' => 'ms3_order_status', 'xtype' => 'combo', 'visible' => true, 'required' => true, 'sort_order' => 30],
            ['model' => 'msOrder', 'name' => 'delivery_id', 'label' => 'ms3_order_delivery', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 40],
            ['model' => 'msOrder', 'name' => 'payment_id', 'label' => 'ms3_order_payment', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 50],
            ['model' => 'msOrder', 'name' => 'customer_id', 'label' => 'ms3_order_customer', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 60],
            ['model' => 'msOrder', 'name' => 'order_comment', 'label' => 'ms3_order_comment', 'xtype' => 'textarea', 'visible' => true, 'required' => false, 'sort_order' => 100],
        ];
        foreach ($orderFields as $row) {
            $n = $row['name'];
            $exists = $this->fetchAll(
                "SELECT id FROM {$this->prefix}ms3_model_fields WHERE model = 'msOrder' AND name = '{$n}' LIMIT 1"
            );
            if ($exists !== []) {
                continue;
            }
            $this->table('ms3_model_fields')->insert([$row])->saveData();
        }
    }

    private function insertAddressFieldsIfMissing(): void
    {
        $addressFields = [
            ['model' => 'msOrderAddress', 'name' => 'first_name', 'label' => 'ms3_address_first_name', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 10],
            ['model' => 'msOrderAddress', 'name' => 'last_name', 'label' => 'ms3_address_last_name', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 20],
            ['model' => 'msOrderAddress', 'name' => 'phone', 'label' => 'ms3_address_phone', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 30],
            ['model' => 'msOrderAddress', 'name' => 'email', 'label' => 'ms3_address_email', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 40],
            ['model' => 'msOrderAddress', 'name' => 'country', 'label' => 'ms3_address_country', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 50],
            ['model' => 'msOrderAddress', 'name' => 'index', 'label' => 'ms3_address_index', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 60],
            ['model' => 'msOrderAddress', 'name' => 'region', 'label' => 'ms3_address_region', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 70],
            ['model' => 'msOrderAddress', 'name' => 'city', 'label' => 'ms3_address_city', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 80],
            ['model' => 'msOrderAddress', 'name' => 'metro', 'label' => 'ms3_address_metro', 'xtype' => 'textfield', 'visible' => false, 'required' => false, 'sort_order' => 90],
            ['model' => 'msOrderAddress', 'name' => 'street', 'label' => 'ms3_address_street', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 100],
            ['model' => 'msOrderAddress', 'name' => 'building', 'label' => 'ms3_address_building', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 110],
            ['model' => 'msOrderAddress', 'name' => 'entrance', 'label' => 'ms3_address_entrance', 'xtype' => 'textfield', 'visible' => false, 'required' => false, 'sort_order' => 120],
            ['model' => 'msOrderAddress', 'name' => 'floor', 'label' => 'ms3_address_floor', 'xtype' => 'textfield', 'visible' => false, 'required' => false, 'sort_order' => 130],
            ['model' => 'msOrderAddress', 'name' => 'room', 'label' => 'ms3_address_room', 'xtype' => 'textfield', 'visible' => true, 'required' => false, 'sort_order' => 140],
            ['model' => 'msOrderAddress', 'name' => 'comment', 'label' => 'ms3_address_comment', 'xtype' => 'textarea', 'visible' => true, 'required' => false, 'sort_order' => 150],
            ['model' => 'msOrderAddress', 'name' => 'text_address', 'label' => 'ms3_address_text', 'xtype' => 'textarea', 'visible' => false, 'required' => false, 'sort_order' => 160],
        ];
        foreach ($addressFields as $row) {
            $n = $row['name'];
            $exists = $this->fetchAll(
                "SELECT id FROM {$this->prefix}ms3_model_fields WHERE model = 'msOrderAddress' AND name = '{$n}' LIMIT 1"
            );
            if ($exists !== []) {
                continue;
            }
            $this->table('ms3_model_fields')->insert([$row])->saveData();
        }
    }

    private function updateOrderFieldSections(): void
    {
        $sections = $this->fetchAll("SELECT id, section_key FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrder'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        $fieldUpdates = [
            ['name' => 'status_id', 'section' => 'main', 'width' => 6],
            ['name' => 'delivery_id', 'section' => 'main', 'width' => 6],
            ['name' => 'payment_id', 'section' => 'main', 'width' => 6],
            ['name' => 'order_comment', 'section' => 'other', 'width' => 12],
        ];

        foreach ($fieldUpdates as $update) {
            $sectionId = $sectionMap[$update['section']] ?? null;
            if ($sectionId) {
                $n = $update['name'];
                $this->execute(
                    "UPDATE {$this->prefix}ms3_model_fields SET section_id = {$sectionId}, width = {$update['width']} " .
                    "WHERE model = 'msOrder' AND name = '{$n}' AND section_id IS NULL"
                );
            }
        }
    }

    private function updateAddressFieldSections(): void
    {
        $sections = $this->fetchAll("SELECT id, section_key FROM {$this->prefix}ms3_model_field_sections WHERE model = 'msOrderAddress'");
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[$section['section_key']] = $section['id'];
        }

        $fieldUpdates = [
            ['name' => 'first_name', 'section' => 'contact', 'width' => 6],
            ['name' => 'last_name', 'section' => 'contact', 'width' => 6],
            ['name' => 'phone', 'section' => 'contact', 'width' => 6],
            ['name' => 'email', 'section' => 'contact', 'width' => 6],
            ['name' => 'country', 'section' => 'location', 'width' => 4],
            ['name' => 'index', 'section' => 'location', 'width' => 4],
            ['name' => 'region', 'section' => 'location', 'width' => 4],
            ['name' => 'city', 'section' => 'location', 'width' => 6],
            ['name' => 'metro', 'section' => 'location', 'width' => 6],
            ['name' => 'street', 'section' => 'location', 'width' => 12],
            ['name' => 'building', 'section' => 'building', 'width' => 4],
            ['name' => 'entrance', 'section' => 'building', 'width' => 2],
            ['name' => 'floor', 'section' => 'building', 'width' => 2],
            ['name' => 'room', 'section' => 'building', 'width' => 4],
            ['name' => 'comment', 'section' => 'other', 'width' => 12],
            ['name' => 'text_address', 'section' => 'other', 'width' => 12],
        ];

        foreach ($fieldUpdates as $update) {
            $sectionId = $sectionMap[$update['section']] ?? null;
            if ($sectionId) {
                $n = $update['name'];
                $this->execute(
                    "UPDATE {$this->prefix}ms3_model_fields SET section_id = {$sectionId}, width = {$update['width']} " .
                    "WHERE model = 'msOrderAddress' AND name = '{$n}' AND section_id IS NULL"
                );
            }
        }
    }
}
