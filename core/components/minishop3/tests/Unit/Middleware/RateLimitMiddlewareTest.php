<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Middleware;

use MiniShop3\Middleware\RateLimitMiddleware;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\RateLimit\FileRateLimitStore;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/ms3_rl_mw_' . bin2hex(random_bytes(8));
        mkdir($this->storagePath, 0777, true);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_MS3TOKEN']);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storagePath . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);
        $middleware = new RateLimitMiddleware(2, 60, $store);

        self::assertNull($middleware->handle([]));
        self::assertSame(1, $store->read('rate_limit:' . md5('127.0.0.1'))['attempts']);
    }

    public function testReturns429WhenLimitExceeded(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);
        $middleware = new RateLimitMiddleware(1, 60, $store);

        self::assertNull($middleware->handle([]));

        $response = $middleware->handle([]);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testDifferentMs3TokenHeadersShareIpBucket(): void
    {
        $store = new FileRateLimitStore($this->storagePath, 60);
        $middleware = new RateLimitMiddleware(2, 60, $store);

        $_SERVER['HTTP_MS3TOKEN'] = 'token-aaa';
        self::assertNull($middleware->handle([]));

        $_SERVER['HTTP_MS3TOKEN'] = 'token-bbb';
        self::assertNull($middleware->handle([]));

        $key = 'rate_limit:' . md5('127.0.0.1');
        self::assertSame(2, $store->read($key)['attempts']);

        $response = $middleware->handle([]);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::TOO_MANY_REQUESTS, $response->getStatusCode());
    }
}
