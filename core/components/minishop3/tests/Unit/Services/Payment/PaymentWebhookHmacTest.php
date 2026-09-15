<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Payment;

use MiniShop3\Services\Payment\PaymentWebhookHmac;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookHmacTest extends TestCase
{
    public function testVerifyAcceptsMatchingSignature(): void
    {
        $body = '{"id":"evt-1"}';
        $secret = 'webhook-secret';
        $signature = hash_hmac('sha256', $body, $secret);

        self::assertTrue(PaymentWebhookHmac::verify($body, $signature, $secret));
    }

    public function testVerifyRejectsMismatchAndEmptyValues(): void
    {
        $body = '{"id":"evt-1"}';
        $secret = 'webhook-secret';
        $signature = hash_hmac('sha256', $body, $secret);

        self::assertFalse(PaymentWebhookHmac::verify($body, $signature, 'other-secret'));
        self::assertFalse(PaymentWebhookHmac::verify($body, 'deadbeef', $secret));
        self::assertFalse(PaymentWebhookHmac::verify('', $signature, $secret));
        self::assertFalse(PaymentWebhookHmac::verify($body, '', $secret));
        self::assertFalse(PaymentWebhookHmac::verify($body, $signature, ''));
    }
}
