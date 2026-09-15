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

    /**
     * Writing middleware before CorsMiddleware must not run on OPTIONS preflight (#706).
     */
    public function testOptionsPreflightSkipsMiddlewareStackBeforeCors(): void
    {
        unset($_REQUEST['__preflight_writing_middleware_ran']);
        $handlerCalled = false;
        $writingMiddleware = new class implements \MiniShop3\Router\Middleware\MiddlewareInterface {
            public function handle(array $params)
            {
                $_REQUEST['__preflight_writing_middleware_ran'] = '1';

                return null;
            }
        };
        $router = new \MiniShop3\Router\Router($this->modx);
        $router->group('/api/v1', function ($router) use (&$handlerCalled) {
            $router->post('/cors-preflight-probe', function () use (&$handlerCalled) {
                $handlerCalled = true;

                return \MiniShop3\Router\Response::success(['ok' => true]);
            });
        }, [
            $writingMiddleware,
            new \MiniShop3\Middleware\CorsMiddleware([
                'allowed_origins' => ['https://trusted.example'],
            ]),
        ]);
        $router->build();

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/v1/cors-preflight-probe';
        $_SERVER['HTTP_ORIGIN'] = 'https://trusted.example';
        $_REQUEST = ['route' => '/api/v1/cors-preflight-probe'];

        $response = $router->dispatch('/api/v1/cors-preflight-probe', 'OPTIONS');

        self::assertFalse($handlerCalled);
        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('__preflight_writing_middleware_ran', $_REQUEST);
    }

    /**
     * Cors present but after TokenMiddleware: preflight must still not mint a token (#634 review).
     */
    public function testOptionsPreflightWithTokenBeforeCorsDoesNotMintToken(): void
    {
        unset($_REQUEST['ms3_token'], $_SESSION['ms3']);
        $handlerCalled = false;
        $router = new \MiniShop3\Router\Router($this->modx);
        $router->group('/api/v1', function ($router) use (&$handlerCalled) {
            $router->post('/addon-probe-reversed', function () use (&$handlerCalled) {
                $handlerCalled = true;

                return \MiniShop3\Router\Response::success(['ok' => true]);
            });
        }, [
            new \MiniShop3\Middleware\TokenMiddleware($this->modx),
            new \MiniShop3\Middleware\CorsMiddleware([
                'allowed_origins' => ['https://trusted.example'],
            ]),
        ]);
        $router->build();

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/v1/addon-probe-reversed';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $_REQUEST = ['route' => '/api/v1/addon-probe-reversed'];

        $response = $router->dispatch('/api/v1/addon-probe-reversed', 'OPTIONS');

        self::assertFalse($handlerCalled);
        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('ms3_token', $_REQUEST);
    }

    /**
     * Nested CorsMiddleware layers: preflight must accumulate headers like GET (#718).
     * Origins: only-outer, only-inner, both, neither — OPTIONS headers === GET headers.
     */
    public function testNestedCorsPreflightHeadersMatchGetForEachOrigin(): void
    {
        $handlerCalled = false;
        $bag = (object) ['lines' => []];
        $outerCors = self::recordingCors([
            'allowed_origins' => ['https://outer.example', 'https://both.example'],
            'allow_credentials' => true,
            'max_age' => 100,
            'allowed_methods' => ['GET', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'X-Outer'],
        ], $bag);
        $innerCors = self::recordingCors([
            'allowed_origins' => ['https://inner.example', 'https://both.example'],
            'allow_credentials' => false,
            'max_age' => 600,
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'X-Inner'],
        ], $bag);

        $writingMiddleware = new class implements \MiniShop3\Router\Middleware\MiddlewareInterface {
            public function handle(array $params)
            {
                $_REQUEST['__nested_writing_middleware_ran'] = '1';

                return null;
            }
        };

        $router = new \MiniShop3\Router\Router($this->modx);
        $router->group('/api/v1', function ($router) use (&$handlerCalled, $innerCors, $writingMiddleware) {
            $router->group('/nested', function ($router) use (&$handlerCalled) {
                $router->get('/probe', function () use (&$handlerCalled) {
                    $handlerCalled = true;

                    return \MiniShop3\Router\Response::success(['ok' => true]);
                });
            }, [$writingMiddleware, $innerCors]);
        }, [$outerCors]);
        $router->build();

        $uri = '/api/v1/nested/probe';
        foreach (
            [
                'https://outer.example',
                'https://inner.example',
                'https://both.example',
                'https://nobody.example',
            ] as $origin
        ) {
            unset($_REQUEST['__nested_writing_middleware_ran']);
            $handlerCalled = false;
            $bag->lines = [];
            $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
            $_SERVER['REQUEST_URI'] = $uri;
            $_SERVER['HTTP_ORIGIN'] = $origin;
            $_REQUEST = ['route' => $uri];
            $optionsResponse = $router->dispatch($uri, 'OPTIONS');
            $optionsHeaders = $bag->lines;
            self::assertSame(200, $optionsResponse->getStatusCode(), $origin);
            self::assertFalse($handlerCalled, $origin);
            self::assertArrayNotHasKey('__nested_writing_middleware_ran', $_REQUEST, $origin);

            $handlerCalled = false;
            $bag->lines = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = $uri;
            $_SERVER['HTTP_ORIGIN'] = $origin;
            $_REQUEST = ['route' => $uri];
            $getResponse = $router->dispatch($uri, 'GET');
            $getHeaders = $bag->lines;
            self::assertTrue($handlerCalled, $origin);
            self::assertSame(200, $getResponse->getStatusCode(), $origin);
            self::assertSame(
                self::corsHeaderMap($getHeaders),
                self::corsHeaderMap($optionsHeaders),
                "OPTIONS CORS headers must match GET for origin {$origin}"
            );
        }
    }

    public function testGetHealthStillWorksAfterCorsChanges(): void
    {
        $res = $this->dispatch('GET', '/api/v1/health');

        self::assertSame(200, $res['status']);
        self::assertTrue($res['success']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function recordingCors(array $config, object $bag): \MiniShop3\Middleware\CorsMiddleware
    {
        return new class ($config, $bag) extends \MiniShop3\Middleware\CorsMiddleware {
            public function __construct(array $config, private object $bag)
            {
                parent::__construct($config);
            }

            protected function emitHeader(string $header): void
            {
                $this->bag->lines[] = $header;
            }
        };
    }

    /**
     * @param list<string> $headers
     * @return array<string, string>
     */
    private static function corsHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $map[$name] = trim(substr($line, $pos + 1));
        }

        return $map;
    }
}
