<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Middleware\CorsMiddleware;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Utils\CorsConfig;
use PHPUnit\Framework\TestCase;

/**
 * CORS smoke adjacent to journey suite (#574 / #335, #634).
 */
final class HeadlessStorefrontCorsTest extends TestCase
{
    public function testNormalizeRejectsWildcardWithCredentials(): void
    {
        $cfg = CorsConfig::normalizeCorsConfig([
            'allowed_origins' => '*',
            'allow_credentials' => true,
        ]);

        self::assertSame(['*'], $cfg['allowed_origins']);
        self::assertFalse($cfg['allow_credentials']);
    }

    public function testPreflightOptionsReturnsHttp200Response(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'https://shop.example';

        $mw = new CorsMiddleware([
            'allowed_origins' => 'https://shop.example',
            'allow_credentials' => true,
        ]);

        $result = $mw->handle([]);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(HttpStatus::OK, $result->getStatusCode());
        self::assertTrue($result->getData()['success'] ?? false);
    }

    public function testNormalizeEmptyOriginsDisallowsAll(): void
    {
        $cfg = CorsConfig::normalizeCorsConfig([
            'allowed_origins' => '',
            'allow_credentials' => true,
        ]);

        self::assertSame([], $cfg['allowed_origins']);
    }

    public function testCorsMiddlewareContinuesForNonOptions(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://shop.example';

        $mw = new CorsMiddleware([
            'allowed_origins' => 'https://shop.example',
            'allow_credentials' => true,
        ]);

        self::assertNull($mw->handle([]));
    }
}
