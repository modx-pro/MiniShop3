<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msLink;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msVendor;
use MiniShop3\Processors\Customer\Address\GetList as CustomerAddressGetList;
use MiniShop3\Processors\Customer\GetList as CustomerGetList;
use MiniShop3\Processors\Gallery\GetList as GalleryGetList;
use MiniShop3\Processors\Product\GetList as ProductGetList;
use MiniShop3\Processors\Settings\Delivery\Create as DeliveryCreate;
use MiniShop3\Processors\Settings\Delivery\GetList as DeliveryGetList;
use MiniShop3\Processors\Settings\Link\Create as LinkCreate;
use MiniShop3\Processors\Settings\Link\GetList as LinkGetList;
use MiniShop3\Processors\Settings\Payment\Create as PaymentCreate;
use MiniShop3\Processors\Settings\Payment\Disable as PaymentDisable;
use MiniShop3\Processors\Settings\Payment\Enable as PaymentEnable;
use MiniShop3\Processors\Settings\Payment\GetList as PaymentGetList;
use MiniShop3\Processors\Settings\Status\Create as StatusCreate;
use MiniShop3\Processors\Settings\Status\Disable as StatusDisable;
use MiniShop3\Processors\Settings\Status\Enable as StatusEnable;
use MiniShop3\Processors\Settings\Status\GetList as StatusGetList;
use MiniShop3\Processors\Settings\Vendor\Create as VendorCreate;
use MiniShop3\Processors\Settings\Vendor\GetList as VendorGetList;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class ProcessorCoverageTest extends ExtraTestCase
{
    public function testGetListProcessorsSucceedForSudo(): void
    {
        $this->actingAsSudo();

        foreach ($this->getListProcessors() as [$class, $properties]) {
            $this->modx->error->reset();
            $response = $this->runExtraProcessor($class, $properties);
            $this->assertProcessorSuccess($response);
        }

        $this->modx->error->reset();
        $combo = $this->runExtraProcessor(ProductGetList::class, [
            'combo' => true,
            'limit' => 10,
        ]);
        $this->assertProcessorSuccess($combo);
    }

    public function testGetListProcessorsDenyAnonymousWhenPoliciesAreEnforced(): void
    {
        $this->skipUnlessProcessorPoliciesAreEnforced();

        $this->actingAsPlain();

        foreach ($this->getListProcessors() as [$class, $properties]) {
            $this->modx->error->reset();
            $response = $this->runExtraProcessor($class, $properties);
            $this->assertProcessorFailure($response);
            self::assertStringContainsStringIgnoringCase('access denied', $response->getMessage());
        }
    }

    public function testSettingsCreateEnableDisableRoundTrip(): void
    {
        $this->actingAsSudo();
        $suffix = bin2hex(random_bytes(3));

        $creates = [
            [VendorCreate::class, ['name' => 'TB Proc Vendor ' . $suffix], msVendor::class],
            [PaymentCreate::class, ['name' => 'TB Proc Payment ' . $suffix], msPayment::class],
            [DeliveryCreate::class, ['name' => 'TB Proc Delivery ' . $suffix], msDelivery::class],
            [LinkCreate::class, ['name' => 'TB Proc Link ' . $suffix, 'type' => 'many_to_many'], msLink::class],
            [StatusCreate::class, ['name' => 'TB Proc Status ' . $suffix], msOrderStatus::class],
        ];

        foreach ($creates as [$class, $properties, $model]) {
            $this->modx->error->reset();
            $response = $this->runExtraProcessor($class, $properties);
            $this->assertProcessorSuccess($response);
            $this->assertObjectExists($model, ['name' => $properties['name']]);
        }

        $payment = $this->modx->getObject(msPayment::class, ['name' => 'TB Proc Payment ' . $suffix]);
        self::assertNotNull($payment);
        $this->modx->error->reset();
        $this->assertProcessorSuccess($this->runExtraProcessor(PaymentDisable::class, [
            'id' => $payment->get('id'),
        ]));
        $payment = $this->modx->getObject(msPayment::class, ['id' => $payment->get('id')]);
        self::assertNotNull($payment);
        self::assertFalse((bool) $payment->get('active'));

        $this->modx->error->reset();
        $this->assertProcessorSuccess($this->runExtraProcessor(PaymentEnable::class, [
            'id' => $payment->get('id'),
        ]));
        $payment = $this->modx->getObject(msPayment::class, ['id' => $payment->get('id')]);
        self::assertNotNull($payment);
        self::assertTrue((bool) $payment->get('active'));

        $status = $this->modx->getObject(msOrderStatus::class, ['name' => 'TB Proc Status ' . $suffix]);
        self::assertNotNull($status);
        $this->modx->error->reset();
        $this->assertProcessorSuccess($this->runExtraProcessor(StatusDisable::class, [
            'id' => $status->get('id'),
        ]));
        $this->modx->error->reset();
        $this->assertProcessorSuccess($this->runExtraProcessor(StatusEnable::class, [
            'id' => $status->get('id'),
        ]));
    }

    /**
     * @return list<array{0: class-string, 1: array<string, mixed>}>
     */
    private function getListProcessors(): array
    {
        return [
            [VendorGetList::class, ['limit' => 10]],
            [PaymentGetList::class, ['limit' => 10]],
            [DeliveryGetList::class, ['limit' => 10]],
            [LinkGetList::class, ['limit' => 10]],
            [StatusGetList::class, ['limit' => 10]],
            [CustomerGetList::class, ['limit' => 10]],
            [CustomerAddressGetList::class, ['limit' => 10]],
            [GalleryGetList::class, ['product_id' => 0, 'limit' => 10]],
        ];
    }
}
