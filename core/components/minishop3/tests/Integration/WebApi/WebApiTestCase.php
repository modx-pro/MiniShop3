<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Router\Response;
use MiniShop3\Router\Router;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyWebApiModx;
use MiniShop3\Utils\CookieHelper;
use PHPUnit\Framework\TestCase;

/**
 * Shared harness: Router + web.php + journey MODX stub.
 */
abstract class WebApiTestCase extends TestCase
{
    protected JourneyWebApiModx $modx;

    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\MODX\Revolution\modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 2) . '/stubs/FakeMsCustomer.php';
        require_once dirname(__DIR__, 2) . '/stubs/WebApiModxStub.php';
        require_once dirname(__DIR__, 2) . '/stubs/ProcessorResponseStub.php';

        $this->resetSuperglobals();
        $this->modx = new JourneyWebApiModx();
        $this->modx->installAuthProcessors(42);
        $this->router = $this->buildRouter($this->modx);
    }

    protected function tearDown(): void
    {
        $this->resetSuperglobals();
        parent::tearDown();
    }

    protected function buildRouter(JourneyWebApiModx $modx): Router
    {
        $router = new Router($modx);
        $router->loadRoutes(dirname(__DIR__, 3) . '/config/routes/web.php');
        $router->build();

        return $router;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers Map without HTTP_ prefix style keys, e.g. Authorization
     * @return array{status: int, success: bool|null, message: string, data: mixed, body: array<string, mixed>}
     */
    protected function dispatch(
        string $method,
        string $route,
        array $query = [],
        array $post = [],
        array $headers = [],
        ?string $bearer = null,
        ?string $cookieToken = null,
    ): array {
        $_GET = $query;
        $_POST = $post;
        $_REQUEST = array_merge($query, $post, ['route' => $route]);
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $route;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . ((getmypid() % 200) + 1);

        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_MS3TOKEN'], $_SERVER['HTTP_ORIGIN']);

        if ($bearer !== null && $bearer !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
            $_REQUEST['ms3_token'] = $bearer;
        }

        foreach ($headers as $name => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$serverKey] = $value;
        }

        if ($cookieToken !== null && $cookieToken !== '') {
            $_COOKIE['ms3_token'] = $cookieToken;
        } else {
            unset($_COOKIE['ms3_token']);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $response = $this->router->dispatch($route, strtoupper($method));

        return $this->envelope($response);
    }

    /**
     * @param array{status: int, success: bool|null, message: string, data: mixed, body: array<string, mixed>} $res
     */
    protected function assertApiError(array $res, int $status, string $message): void
    {
        self::assertSame($status, $res['status']);
        self::assertFalse($res['success']);
        self::assertSame($message, $res['message']);
    }

    protected function lastMs3Token(): string
    {
        return (string) ($_REQUEST['ms3_token'] ?? '');
    }

    /**
     * @return array{status: int, success: bool|null, message: string, data: mixed, body: array<string, mixed>}
     */
    protected function envelope(Response $response): array
    {
        $body = $response->getData();
        if (!is_array($body)) {
            $body = [];
        }

        return [
            'status' => $response->getStatusCode(),
            'success' => isset($body['success']) ? (bool) $body['success'] : null,
            'message' => (string) ($body['message'] ?? ''),
            'data' => $body['data'] ?? null,
            'body' => $body,
        ];
    }

    protected function resetSuperglobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_COOKIE = [];
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_MS3TOKEN'],
            $_SERVER['HTTP_ORIGIN'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REQUEST_METHOD'],
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        // Allow CookieHelper to set cookies again in the next request cycle.
        $ref = new \ReflectionClass(CookieHelper::class);
        if ($ref->hasProperty('lastTokenSet')) {
            $prop = $ref->getProperty('lastTokenSet');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }
}
