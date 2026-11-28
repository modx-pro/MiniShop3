<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Middleware;

use MiniShop3\Middleware\TokenMiddleware;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\TokenService;
use MiniShop3\Tests\Stubs\CustomerRow;
use MiniShop3\Tests\Stubs\TokenRow;
use MiniShop3\Tests\Stubs\TokenTestModx;
use MiniShop3\Utils\CookieHelper;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/TokenTestDoubles.php';

/**
 * #1000 review: a valid token must be accepted from any source, while lazy
 * promotion is limited to unpersisted guest session tokens.
 */
final class TokenMiddlewarePromotionTest extends TestCase
{
    private TokenTestModx $modx;

    private TokenService $service;

    private TokenMiddleware $middleware;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        $_REQUEST = ['route' => '/api/v1/cart/get'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/cart/get';
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $_SERVER['HTTP_MS3TOKEN'] = '';

        $lastTokenSet = new \ReflectionProperty(CookieHelper::class, 'lastTokenSet');
        $lastTokenSet->setValue(null, null);

        $this->modx = new TokenTestModx();
        $this->service = $this->modx->bindTokenService();
        $this->middleware = new TokenMiddleware($this->modx);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        $_REQUEST = [];
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_MS3TOKEN'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI']
        );
    }

    public function testValidBearerTokenWithoutSessionIsAccepted(): void
    {
        $bearer = str_repeat('a', 64);
        $this->seedToken($bearer);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;

        $response = @$this->middleware->handle([]);

        self::assertNull($response, 'valid Bearer token must not be rejected without a session');
        self::assertSame($bearer, $_REQUEST['ms3_token']);
        self::assertSame(0, $this->modx->store->count);
    }

    public function testValidLegacyMs3TokenHeaderWithoutSessionIsAccepted(): void
    {
        $token = str_repeat('b', 64);
        $this->seedToken($token);
        $_SERVER['HTTP_MS3TOKEN'] = $token;

        $response = @$this->middleware->handle([]);

        self::assertNull($response);
        self::assertSame($token, $_REQUEST['ms3_token']);
    }

    public function testValidCookieAfterSessionLossIsAccepted(): void
    {
        $token = str_repeat('c', 64);
        $this->seedToken($token);
        $_COOKIE['ms3_token'] = $token;

        $response = @$this->middleware->handle([]);

        self::assertNull($response);
        self::assertSame($token, $_REQUEST['ms3_token']);
    }

    public function testMissingOrStaleCookieIsRejectedAndNotAdopted(): void
    {
        $stale = str_repeat('d', 64);
        $_COOKIE['ms3_token'] = $stale;

        $response = @$this->middleware->handle([]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(ApiErrorCode::TOKEN_INVALID, $response->getData()['error_code'] ?? null);
        self::assertArrayNotHasKey('ms3_token', $_COOKIE);
        self::assertNull($this->modx->store->findByCriteria(['token_hash' => hash('sha256', $stale)]));
        self::assertSame(0, $this->modx->store->count);

        // A fresh snippet call must mint a new server-side token, never adopt
        // the stale cookie value.
        $fresh = @$this->service->resolveOrCreateToken();
        self::assertNotSame($stale, $fresh);
        self::assertSame(0, $this->modx->store->count);
    }

    public function testBlockedCustomerTokenIsDeniedAndSessionNotRehydrated(): void
    {
        $token = str_repeat('e', 64);
        $this->seedToken($token, 42);
        $this->modx->customers[42] = new CustomerRow([
            'id' => 42,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);
        $_SESSION['ms3'] = [
            'customer_token' => $token,
            'customer_token_expires' => time() + 3600,
            'customer_token_lazy' => true,
            'customer_id' => 42,
        ];

        $response = @$this->middleware->handle([]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertNull($_SESSION['ms3']['customer_id'] ?? null, 'blocked customer must not regain access');
        self::assertNull($_SESSION['ms3']['customer_token'] ?? null);
        self::assertSame(0, $this->modx->store->count);
    }

    public function testFirstProtectedActionPromotesLazyGuestToken(): void
    {
        $lazy = @$this->service->getOrCreateSessionToken();
        self::assertTrue($this->service->isSessionTokenLazy());

        $response = @$this->middleware->handle([]);

        self::assertNull($response);
        self::assertSame($lazy, $_REQUEST['ms3_token']);
        self::assertFalse($this->service->isSessionTokenLazy());
        self::assertSame(1, $this->modx->store->count);
        $row = $this->modx->store->findByCriteria(['token_hash' => hash('sha256', $lazy)]);
        self::assertInstanceOf(TokenRow::class, $row);
        self::assertSame('', $row->raw('token'));
        self::assertSame(0, (int) $row->raw('customer_id'));
    }

    public function testLazyTokenIsNotPromotedForAuthenticatedSession(): void
    {
        $_SESSION['ms3'] = ['customer_id' => 7];
        @$this->service->getOrCreateSessionToken();

        $response = @$this->middleware->handle([]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(0, $this->modx->store->count);
    }

    public function testRevokedPersistedTokenIsNotReinsertedByMiddleware(): void
    {
        $revoked = str_repeat('f', 64);
        $row = $this->seedToken($revoked);
        $row->remove();
        $_SESSION['ms3'] = [
            'customer_token' => $revoked,
            'customer_token_expires' => time() + 3600,
            // marker cleared when the row was first persisted
            'customer_id' => 0,
        ];

        $response = @$this->middleware->handle([]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertNull($this->modx->store->findByCriteria(['token_hash' => hash('sha256', $revoked)]));
        self::assertSame(0, $this->modx->store->count);
    }

    private function seedToken(string $token, int $customerId = 0): TokenRow
    {
        $row = new TokenRow($this->modx->store, [
            'token' => '',
            'token_hash' => hash('sha256', $token),
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => $customerId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->modx->store->seed($row);

        return $row;
    }
}
