<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Middleware;

use MiniShop3\Middleware\TokenMiddleware;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TokenMiddlewarePublicPatternTest extends TestCase
{
    private TokenMiddleware $middleware;

    private ReflectionMethod $isPublic;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }

        $this->middleware = new TokenMiddleware(new modX());
        $this->isPublic = new ReflectionMethod(TokenMiddleware::class, 'isPublicRoute');
        $this->isPublic->setAccessible(true);
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['route']);
    }

    #[DataProvider('routes')]
    public function testPublicPatternDoesNotWidenProductGroup(string $route, bool $expected): void
    {
        $_REQUEST['route'] = $route;

        self::assertSame($expected, $this->isPublic->invoke($this->middleware, '/'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function routes(): iterable
    {
        yield 'images' => ['/api/v1/product/42/images', true];
        yield 'images trailing slash' => ['/api/v1/product/42/images/', true];
        yield 'images query' => ['/api/v1/product/42/images?include_thumbs=1', true];
        yield 'filters prefix' => ['/api/v1/product/filters', true];
        yield 'list prefix' => ['/api/v1/product/list', true];
        yield 'product get by id' => ['/api/v1/product/get/5', true];
        yield 'delivery get by id' => ['/api/v1/delivery/get/5', true];
        yield 'payment get by id' => ['/api/v1/payment/get/3', true];
        yield 'getting-started not public' => ['/api/v1/product/getting-started', false];
        yield 'unknown product sibling' => ['/api/v1/product/42/reviews', false];
        yield 'product root' => ['/api/v1/product/42', false];
        yield 'nested after images' => ['/api/v1/product/42/images/raw', false];
    }
}
