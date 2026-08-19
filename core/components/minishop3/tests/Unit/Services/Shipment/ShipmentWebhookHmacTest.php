<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Shipment;

use MiniShop3\Services\Shipment\ShipmentWebhookHmac;
use PHPUnit\Framework\TestCase;

final class ShipmentWebhookHmacTest extends TestCase
{
    public function testVerifyAcceptsMatchingSignature(): void
    {
        $body = '{"id":"evt-1"}';
        $secret = 'webhook-secret';
        $signature = hash_hmac('sha256', $body, $secret);

        self::assertTrue(ShipmentWebhookHmac::verify($body, $signature, $secret));
    }

    public function testVerifyRejectsMismatchAndEmptyValues(): void
    {
        $body = '{"id":"evt-1"}';
        $secret = 'webhook-secret';
        $signature = hash_hmac('sha256', $body, $secret);

        self::assertFalse(ShipmentWebhookHmac::verify($body, $signature, 'other-secret'));
        self::assertFalse(ShipmentWebhookHmac::verify('', $signature, $secret));
        self::assertFalse(ShipmentWebhookHmac::verify($body, '', $secret));
        self::assertFalse(ShipmentWebhookHmac::verify($body, $signature, ''));
    }

    public function testSecretFromReadsDeliveryProperties(): void
    {
        require_once dirname(__DIR__, 3) . '/stubs/StubMsDelivery.php';
        $method = new \MiniShop3\Tests\Stubs\StubMsDelivery([
            'properties' => ['webhook_secret' => 'from-properties'],
        ]);
        self::assertSame('from-properties', ShipmentWebhookHmac::secretFrom($method));
        self::assertSame('', ShipmentWebhookHmac::secretFrom(new \MiniShop3\Tests\Stubs\StubMsDelivery()));
    }
}
