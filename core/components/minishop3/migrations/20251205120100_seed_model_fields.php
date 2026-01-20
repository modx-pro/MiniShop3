<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedModelFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_model_fields');

        // msOrder fields
        $orderFields = [
            ['model' => 'msOrder', 'name' => 'status_id', 'label' => 'ms3_order_status', 'xtype' => 'combo', 'visible' => true, 'required' => true, 'sort_order' => 30],
            ['model' => 'msOrder', 'name' => 'delivery_id', 'label' => 'ms3_order_delivery', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 40],
            ['model' => 'msOrder', 'name' => 'payment_id', 'label' => 'ms3_order_payment', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 50],
            ['model' => 'msOrder', 'name' => 'customer_id', 'label' => 'ms3_order_customer', 'xtype' => 'combo', 'visible' => true, 'required' => false, 'sort_order' => 60],
            ['model' => 'msOrder', 'name' => 'order_comment', 'label' => 'ms3_order_comment', 'xtype' => 'textarea', 'visible' => true, 'required' => false, 'sort_order' => 100],
        ];

        // msOrderAddress fields
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

        $allFields = array_merge($orderFields, $addressFields);

        $table->insert($allFields)->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `ms3_model_fields` WHERE `model` IN ('msOrder', 'msOrderAddress')");
    }
}
