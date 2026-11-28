<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\TokenService;
use MiniShop3\Tests\Stubs\CustomerRow;
use MiniShop3\Tests\Stubs\TokenRow;
use MiniShop3\Tests\Stubs\TokenRowStore;
use MiniShop3\Tests\Stubs\TokenTestModx;
use MiniShop3\Utils\CookieHelper;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/TokenTestDoubles.php';

/**
 * Regression #1000: anonymous page views must not write DB rows.
 *
 * Snippets call resolveOrCreateToken() (lazy: session + cookie only).
 * The row is persisted only on the first protected API action
 * (TokenMiddleware / generateCustomerToken via ensureTokenPersisted()).
 */
final class TokenServiceLazyIssueTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $_SERVER['HTTP_MS3TOKEN'] = '';

        // CookieHelper deduplicates Set-Cookie across a PHP request; tests must
        // start with a clean slate (#1000 review).
        $lastTokenSet = new \ReflectionProperty(CookieHelper::class, 'lastTokenSet');
        $lastTokenSet->setValue(null, null);
    }

    public function testResolveOrCreateTokenDoesNotWriteDbRow(): void
    {
        $modx = new TokenTestModx();

        $token = @(new TokenService($modx))->resolveOrCreateToken();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $token);
        self::assertSame($token, $_SESSION['ms3']['customer_token']);
        self::assertTrue($_SESSION['ms3']['customer_token_lazy']);
        self::assertSame(0, $modx->store->count);
    }

    public function testSecondSnippetCallReusesLazyToken(): void
    {
        $modx = new TokenTestModx();
        $service = new TokenService($modx);

        $first = @$service->resolveOrCreateToken();
        $second = @$service->resolveOrCreateToken();

        self::assertSame($first, $second);
        self::assertSame(0, $modx->store->count);
    }

    public function testEnsureTokenPersistedStoresHashOnlyAndClearsLazyMarker(): void
    {
        $modx = new TokenTestModx();
        $service = new TokenService($modx);

        $lazy = @$service->getOrCreateSessionToken();
        $persisted = @$service->ensureTokenPersisted($lazy, 0);

        self::assertNotNull($persisted);
        self::assertSame($lazy, $persisted->get('token'), 'plaintext stays in memory only');
        self::assertSame('', $persisted->raw('token'), 'legacy column must stay empty (#927)');
        self::assertSame(hash('sha256', $lazy), $persisted->raw('token_hash'));
        self::assertSame(msCustomerToken::TYPE_API, $persisted->raw('type'));
        self::assertSame(0, (int) $persisted->raw('customer_id'));
        self::assertFalse($service->isSessionTokenLazy(), 'marker cleared after persistence');
        self::assertSame(1, $modx->store->count);
    }

    public function testGenerateCustomerTokenPersistsLazyTokenInsteadOfMintingNew(): void
    {
        $modx = new TokenTestModx();
        $service = new TokenService($modx);

        $lazy = @$service->getOrCreateSessionToken();
        $result = @$service->generateCustomerToken();

        self::assertSame($lazy, $result['token']);
        self::assertSame(1, $modx->store->count);
        self::assertSame(hash('sha256', $lazy), $modx->store->findByCriteria([
            'token_hash' => hash('sha256', $lazy),
        ])?->raw('token_hash'));
    }

    /**
     * An attacker cannot choose an arbitrary token and have the server persist it.
     * Only tokens minted by the server (marked lazy in the session) are eligible.
     */
    public function testForgedTokenWithoutSessionIsRejectedAndNotPersisted(): void
    {
        $modx = new TokenTestModx();
        $service = new TokenService($modx);

        $forgedToken = bin2hex(random_bytes(32));
        $_SESSION['ms3'] = [
            'customer_token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'customer_token_expires' => time() + 86400,
            'customer_token_lazy' => true,
            'customer_id' => 0,
        ];

        $persisted = @$service->ensureTokenPersisted($forgedToken, 0);

        self::assertNull($persisted, 'Forged token must not be persisted');
        self::assertSame(0, $modx->store->count, 'No DB rows should be created for forged token');
    }

    public function testRevokedPersistedTokenIsNotReinserted(): void
    {
        $modx = new TokenTestModx();
        $revoked = str_repeat('b', 64);
        $row = $this->seedToken($modx, $revoked);
        $row->remove(); // revoke: DB row deleted, session kept its token

        $_SESSION['ms3'] = [
            'customer_token' => $revoked,
            'customer_token_expires' => time() + 86400,
            // marker already cleared when the row was first persisted
            'customer_id' => 0,
        ];

        $result = @(new TokenService($modx))->generateCustomerToken();

        self::assertNotSame($revoked, $result['token'], 'a revoked token must not be reinserted');
        self::assertSame(1, $modx->store->count, 'only the replacement row may be written');
        self::assertNull($modx->store->findByCriteria(['token_hash' => hash('sha256', $revoked)]));
    }

    public function testDeniedPrincipalCannotBePromoted(): void
    {
        $modx = new TokenTestModx();
        $blocked = str_repeat('c', 64);
        $this->seedToken($modx, $blocked, 42);
        $modx->customers[42] = new CustomerRow([
            'id' => 42,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);

        $_SESSION['ms3'] = [
            'customer_token' => $blocked,
            'customer_token_expires' => time() + 86400,
            'customer_token_lazy' => true,
            'customer_id' => 0,
        ];

        $service = new TokenService($modx);
        self::assertSame('denied', $service->resolveApiToken($blocked)['reason']);
        self::assertNull(@$service->ensureTokenPersisted($blocked, 0));
        self::assertSame(0, $modx->store->count);
    }

    public function testAuthenticatedSessionKeepsCustomerIdWhenMintingLazyToken(): void
    {
        $modx = new TokenTestModx();
        $_SESSION['ms3'] = ['customer_id' => 7];

        $token = @(new TokenService($modx))->getOrCreateSessionToken();

        self::assertSame(7, $_SESSION['ms3']['customer_id']);
        self::assertSame($token, $_SESSION['ms3']['customer_token']);
        self::assertTrue($_SESSION['ms3']['customer_token_lazy']);
        self::assertSame(0, $modx->store->count);
    }

    public function testUnvalidatedCookieIsNotAdoptedIntoSession(): void
    {
        $modx = new TokenTestModx();
        $forged = str_repeat('d', 64);
        $_COOKIE['ms3_token'] = $forged;

        $token = @(new TokenService($modx))->resolveOrCreateToken();

        self::assertNotSame($forged, $token, 'forged cookie must never be adopted');
        self::assertSame($token, $_SESSION['ms3']['customer_token']);
        self::assertTrue($_SESSION['ms3']['customer_token_lazy']);
        self::assertNull($modx->store->findByCriteria(['token_hash' => hash('sha256', $forged)]));
        self::assertSame(0, $modx->store->count);
    }

    public function testValidCookieIsStillRestoredFromDb(): void
    {
        $modx = new TokenTestModx();
        $valid = str_repeat('e', 64);
        $this->seedToken($modx, $valid, 9);
        $modx->customers[9] = new CustomerRow(['id' => 9, 'is_active' => 1, 'is_blocked' => 0]);
        $_COOKIE['ms3_token'] = $valid;

        $service = new TokenService($modx);
        $token = @$service->resolveOrCreateToken();

        self::assertSame($valid, $token);
        self::assertSame($valid, $_SESSION['ms3']['customer_token']);
        self::assertSame(9, $_SESSION['ms3']['customer_id']);
        self::assertFalse($service->isSessionTokenLazy());
        self::assertSame(0, $modx->store->count);
    }

    public function testDeniedCookieDoesNotKeepAuthenticatedIdentity(): void
    {
        $modx = new TokenTestModx();
        $denied = str_repeat('f', 64);
        $this->seedToken($modx, $denied, 42);
        $modx->customers[42] = new CustomerRow([
            'id' => 42,
            'is_active' => 0,
            'is_blocked' => 0,
        ]);
        $_COOKIE['ms3_token'] = $denied;
        $_SESSION['ms3'] = ['customer_id' => 42];

        $token = @(new TokenService($modx))->resolveOrCreateToken();

        self::assertNotSame($denied, $token);
        self::assertSame(0, $_SESSION['ms3']['customer_id'] ?? null);
        self::assertSame($token, $_SESSION['ms3']['customer_token']);
        self::assertSame(0, $modx->store->count);
    }

    public function testDuplicateHashInsertFromConcurrentFirstCallIsSuccess(): void
    {
        $modx = new TokenTestModx();
        $service = new TokenService($modx);
        $lazy = @$service->getOrCreateSessionToken();

        // Simulate a racing request: it inserted the row first, but our first
        // lookup cannot see it yet, so UNIQUE(token_hash) rejects our insert.
        $racingRow = new TokenRow($modx->store, [
            'token' => '',
            'token_hash' => hash('sha256', $lazy),
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $modx->store->insert($racingRow);
        $modx->store->throwOnDuplicate = true;
        $modx->store->hideFinds = 1;

        $persisted = @$service->ensureTokenPersisted($lazy, 0);

        self::assertNotNull($persisted, 'duplicate key must be treated as success');
        self::assertSame($lazy, $persisted->get('token'));
        self::assertSame(1, $modx->store->count);
        self::assertFalse($service->isSessionTokenLazy());
    }

    private function seedToken(
        TokenTestModx $modx,
        string $token,
        int $customerId = 0,
        array $extra = []
    ): TokenRow {
        $row = new TokenRow($modx->store, array_merge([
            'token' => '',
            'token_hash' => hash('sha256', $token),
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => $customerId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ], $extra));
        $modx->store->seed($row);

        return $row;
    }
}
