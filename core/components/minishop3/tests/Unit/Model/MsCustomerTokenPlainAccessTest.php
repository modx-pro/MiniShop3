<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Model;

use MiniShop3\Model\msCustomerToken;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * #927 review: the request plaintext is exposed only through get('token') /
 * getPlainToken(); serialization and magic access keep returning the stored
 * (empty) column value.
 */
final class MsCustomerTokenPlainAccessTest extends TestCase
{
    public function testPlainTokenIsAvailableThroughGetAndGetter(): void
    {
        $token = new msCustomerToken();

        self::assertNull($token->getPlainToken());

        $token->setPlainToken('plain-token');

        self::assertSame('plain-token', $token->getPlainToken());
        self::assertSame('plain-token', $token->get('token'));
    }

    public function testSerializationAccessorsAreNotOverridden(): void
    {
        $reflection = new ReflectionClass(msCustomerToken::class);

        self::assertSame(
            msCustomerToken::class,
            $reflection->getMethod('get')->getDeclaringClass()->getName(),
            'get() is the only plaintext-aware accessor'
        );

        foreach (['toArray', 'fromArray', '__get'] as $method) {
            if (!$reflection->hasMethod($method)) {
                continue;
            }

            self::assertNotSame(
                msCustomerToken::class,
                $reflection->getMethod($method)->getDeclaringClass()->getName(),
                "{$method} must not expose the request plaintext"
            );
        }
    }
}
