<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msGridField;
use MiniShop3\Model\msLink;
use MiniShop3\Model\msModelField;
use MiniShop3\Model\msModelFieldSection;
use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPageSection;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductField;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class ModelPersistTest extends ExtraTestCase
{
    public function testSimpleObjectsPersist(): void
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(3));

        $vendor = $this->persistObject(msVendor::class, ['name' => 'TB Vendor ' . $suffix]);
        $delivery = $this->persistObject(msDelivery::class, ['name' => 'TB Delivery ' . $suffix]);
        $payment = $this->persistObject(msPayment::class, ['name' => 'TB Payment ' . $suffix]);
        $status = $this->persistObject(msOrderStatus::class, ['name' => 'TB Status ' . $suffix]);
        $link = $this->persistObject(msLink::class, [
            'name' => 'TB Link ' . $suffix,
            'type' => 'many_to_many',
        ]);
        $group = $this->persistObject(msOptionGroup::class, [
            'name' => 'TB Group ' . $suffix,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $option = $this->persistObject(msOption::class, [
            'key' => 'tb_color_' . $suffix,
            'caption' => 'Color',
            'type' => 'textfield',
            'option_group_id' => $group->get('id'),
        ]);
        $customer = $this->persistObject(msCustomer::class, [
            'email' => 'tb-' . $suffix . '@example.invalid',
            'token' => bin2hex(random_bytes(16)),
        ]);
        $order = $this->persistObject(msOrder::class, [
            'num' => 'TB-P-' . $suffix,
            'cost' => 25.5,
            'cart_cost' => 25.5,
            'context' => 'web',
            'status_id' => $status->get('id'),
            'delivery_id' => $delivery->get('id'),
            'payment_id' => $payment->get('id'),
            'customer_id' => $customer->get('id'),
        ]);

        $this->persistObject(msOrderProduct::class, [
            'order_id' => $order->get('id'),
            'product_id' => 0,
            'product_key' => substr(md5('tb-line-' . $suffix), 0, 32),
            'name' => 'TB Line',
            'count' => 1,
            'price' => 25.5,
            'cost' => 25.5,
        ]);
        $this->persistObject(msOrderAddress::class, [
            'order_id' => $order->get('id'),
            'email' => 'tb-' . $suffix . '@example.invalid',
            'city' => 'Test City',
        ]);
        $this->persistObject(msOrderLog::class, [
            'user_id' => 0,
            'order_id' => $order->get('id'),
            'action' => 'testbench',
            'entry' => ['ok' => true],
            'ip' => ['ip' => '127.0.0.1'],
            'timestamp' => $now,
        ]);
        $this->persistObject(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'name' => 'Home',
            'city' => 'Test City',
        ]);
        $this->persistObject(msCustomerToken::class, [
            'customer_id' => $customer->get('id'),
            'token' => 'tb-token-' . $suffix,
            'type' => msCustomerToken::TYPE_API,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => $now,
        ]);
        $this->persistObject(msProductData::class, [
            'article' => 'TB-' . $suffix,
            'price' => 99.0,
            'vendor_id' => $vendor->get('id'),
        ]);
        $this->persistObject(msProductFile::class, [
            'product_id' => 1,
            'file' => 'tb-' . $suffix . '.jpg',
            'name' => 'tb.jpg',
            'path' => '/',
        ]);
        $this->persistObject(msProductOption::class, [
            'product_id' => 1,
            'key' => 'tb_color_' . $suffix,
            'value' => 'red',
        ]);
        $this->persistObject(msExtraField::class, [
            'class' => msVendor::class,
            'key' => 'tb_note_' . $suffix,
            'label' => 'Note',
            'dbtype' => 'varchar',
            'phptype' => 'string',
            'precision' => '191',
            'active' => 0,
        ]);
        $this->persistObject(msGridField::class, [
            'grid_key' => 'orders',
            'field_name' => 'tb_' . $suffix,
            'label' => 'TB',
        ]);
        $this->persistObject(msPageSection::class, [
            'page_key' => 'order',
            'section_key' => 'tb_' . $suffix,
        ]);
        $this->persistObject(msNotificationConfig::class, [
            'event' => 'msOnChangeOrderStatus',
            'recipient_type' => 'customer',
            'channel' => 'email',
        ]);
        $this->persistObject(msModelFieldSection::class, [
            'model' => 'msOrder',
            'section_key' => 'tb_' . $suffix,
            'label' => 'TB',
        ]);
        $this->persistObject(msModelField::class, [
            'model' => 'msOrder',
            'name' => 'tb_' . $suffix,
            'label' => 'TB',
        ]);
        // Phinx InitialSchema adds FK ms3_product_fields.section → ms3_page_sections.id (#695).
        $productSection = $this->persistObject(msPageSection::class, [
            'page_key' => 'product',
            'section_key' => 'tb_pf_' . $suffix,
        ]);
        $this->persistObject(msProductField::class, [
            'name' => 'tb_' . $suffix,
            'label' => 'TB',
            'section' => (int) $productSection->get('id'),
        ]);

        $this->assertObjectExists(msVendor::class, ['name' => 'TB Vendor ' . $suffix]);
        $this->assertObjectExists(msDelivery::class, ['name' => 'TB Delivery ' . $suffix]);
        $this->assertObjectExists(msPayment::class, ['name' => 'TB Payment ' . $suffix]);
        $this->assertObjectExists(msOrderStatus::class, ['name' => 'TB Status ' . $suffix]);
        $this->assertObjectExists(msLink::class, ['name' => 'TB Link ' . $suffix]);
        $this->assertObjectExists(msOption::class, ['key' => 'tb_color_' . $suffix]);
        $this->assertObjectExists(msCustomer::class, ['email' => 'tb-' . $suffix . '@example.invalid']);
        $this->assertObjectExists(msOrder::class, ['num' => 'TB-P-' . $suffix]);
        $this->assertObjectExists(msCustomerToken::class, ['token' => 'tb-token-' . $suffix]);
        $this->assertObjectExists(msNotificationConfig::class, [
            'event' => 'msOnChangeOrderStatus',
            'channel' => 'email',
        ]);
    }
}
