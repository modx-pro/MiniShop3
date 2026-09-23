<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Events;

use MiniShop3\Services\Events\WebhookHmacSigner;
use PHPUnit\Framework\TestCase;

final class WebhookHmacSignerTest extends TestCase
{
    public function testSignAndVerifyAcceptMatchingPayload(): void
    {
        $body = '{"event_id":"550e8400-e29b-41d4-a716-446655440000"}';
        $secret = 'outbound-secret';
        $timestamp = time();
        $signature = WebhookHmacSigner::sign($body, $secret, $timestamp);

        self::assertTrue(WebhookHmacSigner::verify($body, $signature, $secret, $timestamp));
    }

    public function testVerifyRejectsMismatchEmptyValuesAndSkew(): void
    {
        $body = '{"event_id":"550e8400-e29b-41d4-a716-446655440000"}';
        $secret = 'outbound-secret';
        $timestamp = time();
        $signature = WebhookHmacSigner::sign($body, $secret, $timestamp);

        self::assertFalse(WebhookHmacSigner::verify($body, $signature, 'other-secret', $timestamp));
        self::assertFalse(WebhookHmacSigner::verify($body, 'deadbeef', $secret, $timestamp));
        self::assertFalse(WebhookHmacSigner::verify('', $signature, $secret, $timestamp));
        self::assertFalse(WebhookHmacSigner::verify($body, '', $secret, $timestamp));
        self::assertFalse(WebhookHmacSigner::verify($body, $signature, '', $timestamp));
        self::assertFalse(WebhookHmacSigner::verify($body, $signature, $secret, $timestamp - 301));
    }

    public function testSignUsesTimestampDotBodyFormat(): void
    {
        $body = '{"event_type":"order.status_changed"}';
        $secret = 'secret';
        $timestamp = 42;

        self::assertSame(
            hash_hmac('sha256', '42.' . $body, $secret),
            WebhookHmacSigner::sign($body, $secret, $timestamp)
        );
    }
}
