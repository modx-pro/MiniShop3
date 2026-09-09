<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msLink;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductLink;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class ProductAndCompositeTest extends ExtraTestCase
{
    public function testCategoryProductResourcesAndCompositeRowsPersist(): void
    {
        $suffix = bin2hex(random_bytes(3));

        $category = $this->modx->newObject(msCategory::class);
        $category->fromArray([
            'pagetitle' => 'TB Category ' . $suffix,
            'alias' => 'tb-cat-' . $suffix,
            'published' => true,
            'class_key' => msCategory::class,
            'context_key' => 'web',
            'parent' => 0,
            'template' => 0,
        ]);
        self::assertTrue($category->save(), 'Failed to save msCategory resource');

        $product = $this->modx->newObject(msProduct::class);
        $product->fromArray([
            'pagetitle' => 'TB Product ' . $suffix,
            'alias' => 'tb-prod-' . $suffix,
            'published' => true,
            'class_key' => msProduct::class,
            'context_key' => 'web',
            'parent' => $category->get('id'),
            'template' => 0,
        ]);
        self::assertTrue($product->save(), 'Failed to save msProduct resource');

        $data = $this->modx->getObject(msProductData::class, ['id' => $product->get('id')]);
        if ($data === null) {
            $data = $this->modx->newObject(msProductData::class);
            $data->fromArray([
                'id' => $product->get('id'),
                'article' => 'TB-RES-' . $suffix,
                'price' => 42.0,
            ]);
            self::assertTrue($data->save(), 'Failed to save msProductData with resource id');
        } else {
            $data->set('article', 'TB-RES-' . $suffix);
            $data->set('price', 42.0);
            self::assertTrue($data->save(), 'Failed to update msProductData');
        }
        $this->assertObjectExists(msProductData::class, ['id' => $product->get('id')]);

        $option = $this->persistObject(msOption::class, [
            'key' => 'tb_size_' . $suffix,
            'caption' => 'Size',
            'type' => 'textfield',
        ]);
        $this->persistObject(msCategoryMember::class, [
            'product_id' => $product->get('id'),
            'category_id' => $category->get('id'),
            'menuindex' => 1,
        ]);
        $this->persistObject(msCategoryOption::class, [
            'option_id' => $option->get('id'),
            'category_id' => $category->get('id'),
            'active' => 1,
        ]);

        $delivery = $this->persistObject(msDelivery::class, ['name' => 'TB Comp Delivery ' . $suffix]);
        $payment = $this->persistObject(msPayment::class, ['name' => 'TB Comp Payment ' . $suffix]);
        $this->persistObject(msDeliveryMember::class, [
            'delivery_id' => $delivery->get('id'),
            'payment_id' => $payment->get('id'),
        ]);

        $slave = $this->modx->newObject(msProduct::class);
        $slave->fromArray([
            'pagetitle' => 'TB Slave ' . $suffix,
            'alias' => 'tb-slave-' . $suffix,
            'published' => true,
            'class_key' => msProduct::class,
            'context_key' => 'web',
            'parent' => $category->get('id'),
            'template' => 0,
        ]);
        self::assertTrue($slave->save());

        $link = $this->persistObject(msLink::class, [
            'name' => 'TB Comp Link ' . $suffix,
            'type' => 'many_to_many',
        ]);
        $this->persistObject(msProductLink::class, [
            'link' => $link->get('id'),
            'master' => $product->get('id'),
            'slave' => $slave->get('id'),
        ]);

        $this->assertObjectExists(msCategoryMember::class, [
            'product_id' => $product->get('id'),
            'category_id' => $category->get('id'),
        ]);
        $this->assertObjectExists(msDeliveryMember::class, [
            'delivery_id' => $delivery->get('id'),
            'payment_id' => $payment->get('id'),
        ]);
        $this->assertObjectExists(msProductLink::class, [
            'link' => $link->get('id'),
            'master' => $product->get('id'),
            'slave' => $slave->get('id'),
        ]);
        $this->assertObjectExists(msCategoryOption::class, [
            'option_id' => $option->get('id'),
            'category_id' => $category->get('id'),
        ]);
    }
}
