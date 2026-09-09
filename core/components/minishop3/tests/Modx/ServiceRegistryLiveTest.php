<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\Controllers\Order\Order;
use MiniShop3\MiniShop3;
use MiniShop3\ServiceRegistry;
use MiniShop3\Services\TokenService;
use MiniShop3\Services\Validation\ValidationService;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use Throwable;

final class ServiceRegistryLiveTest extends ExtraTestCase
{
    public function testEveryDefaultServiceKeyIsRegistered(): void
    {
        foreach ($this->defaultServiceKeys() as $key) {
            self::assertTrue($this->modx->services->has($key), 'DI missing ' . $key);
        }

        foreach (ServiceRegistry::SERVICE_DEPENDENCIES as $service => $deps) {
            self::assertTrue($this->modx->services->has($service), 'Dependency parent missing ' . $service);
            foreach ($deps as $dep) {
                self::assertTrue($this->modx->services->has($dep), $service . ' depends on missing ' . $dep);
            }
        }
    }

    public function testDefaultServicesResolve(): void
    {
        $failures = [];
        foreach ($this->defaultServiceKeys() as $key) {
            try {
                $service = $this->modx->services->get($key);
                self::assertNotNull($service, $key . ' resolved to null');
            } catch (Throwable $e) {
                $failures[] = $key . ': ' . $e->getMessage();
            }
        }

        self::assertSame([], $failures, "Service get() failed:\n" . implode("\n", $failures));
        self::assertInstanceOf(MiniShop3::class, $this->modx->services->get('ms3'));
        self::assertInstanceOf(Cart::class, $this->modx->services->get('ms3_cart'));
        self::assertInstanceOf(Order::class, $this->modx->services->get('ms3_order'));
        self::assertInstanceOf(Customer::class, $this->modx->services->get('ms3_customer'));
    }

    public function testValidationAndTokenServicesWork(): void
    {
        /** @var ValidationService $validator */
        $validator = $this->modx->services->get('ms3_validation_service');
        $ok = $validator->validate(['email' => 'a@b.c'], ['email' => 'required|email']);
        self::assertFalse($ok->fails());
        $bad = $validator->validate(['email' => ''], ['email' => 'required|email']);
        self::assertTrue($bad->fails());

        /** @var TokenService $tokens */
        $tokens = $this->modx->services->get('ms3_token_service');
        $payload = ['snippet' => 'msCart', 'tpl' => 'tb'];
        $token = $tokens->generateSnippetToken($payload);
        self::assertNotSame('', $token);
        self::assertTrue($tokens->cacheSnippetData($token, $payload));
        $cached = $tokens->getSnippetData($token);
        self::assertIsArray($cached);
        self::assertSame('msCart', $cached['snippet'] ?? null);
    }
}
