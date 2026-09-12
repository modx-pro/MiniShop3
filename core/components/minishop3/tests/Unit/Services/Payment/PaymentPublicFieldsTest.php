<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Payment;

use MiniShop3\Services\Payment\PaymentPublicFields;
use MiniShop3\Tests\Stubs\StubMsPayment;
use PHPUnit\Framework\TestCase;

final class PaymentPublicFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/stubs/StubMsPayment.php';
    }

    public function testOmitsClassAndProperties(): void
    {
        $payment = new StubMsPayment([
            'id' => 4,
            'name' => 'Card',
            'description' => 'Visa',
            'price' => '3%',
            'logo' => '/logo.png',
            'class' => 'Secret\\Gateway',
            'properties' => ['secret' => 'abc'],
        ]);
        $public = PaymentPublicFields::fromEntity($payment);
        self::assertNotNull($public);
        self::assertSame(4, $public['id']);
        self::assertSame('Card', $public['name']);
        self::assertArrayNotHasKey('properties', $public);
        self::assertArrayNotHasKey('class', $public);
    }
}
