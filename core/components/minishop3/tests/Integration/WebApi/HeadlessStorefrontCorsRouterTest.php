<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

/**
 * Router-level CORS preflight (#634).
 */
final class HeadlessStorefrontCorsRouterTest extends WebApiTestCase
{
    public function testOptionsPreflightOnHealthReturns200(): void
    {
        $res = $this->dispatch(
            'OPTIONS',
            '/api/v1/health',
            [],
            [],
            [
                'Origin' => 'https://shop.example',
                'Access-Control-Request-Method' => 'POST',
            ],
        );

        self::assertSame(200, $res['status']);
        self::assertTrue($res['success']);
    }

    public function testOptionsPreflightOnPostOnlyCartAddReturns200(): void
    {
        $res = $this->dispatch(
            'OPTIONS',
            '/api/v1/cart/add',
            [],
            [],
            [
                'Origin' => 'https://shop.example',
                'Access-Control-Request-Method' => 'POST',
            ],
        );

        self::assertSame(200, $res['status']);
        self::assertTrue($res['success']);
    }

    public function testOptionsPreflightOnUnknownStorefrontPathReturns404(): void
    {
        $res = $this->dispatch(
            'OPTIONS',
            '/api/v1/no-such-endpoint',
            [],
            [],
            ['Origin' => 'https://shop.example'],
        );

        self::assertSame(404, $res['status']);
        self::assertFalse($res['success']);
    }

    public function testOptionsPreflightDisallowedOriginStillReturns200Envelope(): void
    {
        $res = $this->dispatch(
            'OPTIONS',
            '/api/v1/health',
            [],
            [],
            ['Origin' => 'https://evil.example'],
        );

        self::assertSame(200, $res['status']);
        self::assertTrue($res['success']);
    }

    public function testOptionsPreflightDoesNotInvokeHandlerWithoutCorsMiddleware(): void
    {
        $handlerCalled = false;
        $router = new \MiniShop3\Router\Router($this->modx);
        $router->group('/api/v1', function ($router) use (&$handlerCalled) {
            $router->post('/probe-mutation', function () use (&$handlerCalled) {
                $handlerCalled = true;

                return \MiniShop3\Router\Response::success(['mutated' => true]);
            });
        }, []);
        $router->build();

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/v1/probe-mutation';
        $_REQUEST = ['route' => '/api/v1/probe-mutation'];

        $response = $router->dispatch('/api/v1/probe-mutation', 'OPTIONS');

        self::assertFalse($handlerCalled);
        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * Addon pattern from ms3.routes.d/web/example-addon.php.dist: TokenMiddleware without
     * CorsMiddleware must not mint an anonymous token on OPTIONS (#634 review).
     */
    public function testOptionsPreflightWithTokenMiddlewareButNoCorsDoesNotMintToken(): void
    {
        unset($_REQUEST['ms3_token'], $_SESSION['ms3']);
        $handlerCalled = false;
        $router = new \MiniShop3\Router\Router($this->modx);
        $router->group('/api/v1', function ($router) use (&$handlerCalled) {
            $router->post('/addon-probe', function () use (&$handlerCalled) {
                $handlerCalled = true;

                return \MiniShop3\Router\Response::success(['ok' => true]);
            });
        }, [new \MiniShop3\Middleware\TokenMiddleware($this->modx)]);
        $router->build();

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/v1/addon-probe';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $_REQUEST = ['route' => '/api/v1/addon-probe'];

        $response = $router->dispatch('/api/v1/addon-probe', 'OPTIONS');

        self::assertFalse($handlerCalled);
        self::assertSame(405, $response->getStatusCode());
        self::assertArrayNotHasKey('ms3_token', $_REQUEST);
    }

    public function testGetHealthStillWorksAfterCorsChanges(): void
    {
        $res = $this->dispatch('GET', '/api/v1/health');

        self::assertSame(200, $res['status']);
        self::assertTrue($res['success']);
    }
}
