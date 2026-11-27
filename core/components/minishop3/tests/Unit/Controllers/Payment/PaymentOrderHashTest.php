<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Payment;

use MiniShop3\Controllers\Payment\Payment;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #930: order hashes must never be signed with site_id when the configured
 * secret is empty.
 */
final class PaymentOrderHashTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testHashUsesConfiguredSecret(): void
    {
        $provider = $this->provider('unit-secret');
        $data = '42|ORD-1|100.50|2026-01-01 00:00:00';

        self::assertSame(
            hash_hmac('sha256', $data, 'unit-secret'),
            $provider->getOrderHash($this->order())
        );
    }

    public function testEmptySecretFailsClosedInsteadOfUsingSiteId(): void
    {
        $provider = $this->provider('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ms3_payment_secret is empty');

        $provider->getOrderHash($this->order());
    }

    public function testWhitespaceOnlySecretFailsClosed(): void
    {
        $provider = $this->provider('   ');

        $this->expectException(\RuntimeException::class);

        $provider->getOrderHash($this->order());
    }

    private function provider(string $secret): Payment
    {
        $modx = new modX();
        $modx->optionOverrides = [
            'ms3_payment_secret' => $secret,
            'site_id' => 'site-id-must-never-sign-orders',
        ];

        return new class ($modx) extends Payment {
            public function __construct(modX $modx)
            {
                $this->modx = $modx;
            }

            public function send(msOrder $order): array
            {
                return [];
            }

            public function receive(msOrder $order): array
            {
                return [];
            }
        };
    }

    private function order(): msOrder
    {
        $order = $this->createMock(msOrder::class);
        $order->method('get')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'id' => 42,
                'num' => 'ORD-1',
                'cost' => '100.50',
                'createdon' => '2026-01-01 00:00:00',
                default => null,
            }
        );

        return $order;
    }
}
