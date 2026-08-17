<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\TokenService;
use MiniShop3\Tests\Support\CustomerAuthPdoStore;
use MiniShop3\Tests\Support\StoredMsCustomer;
use MiniShop3\Tests\Support\StoredMsCustomerToken;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: AuthManager authenticate → establish session → logout lifecycle.
 *
 * Uses PDO store (SQLite by default). Subclass/override createStore() for MySQL.
 */
class AuthManagerLifecycleTest extends TestCase
{
    private CustomerAuthPdoStore $store;

    /** @var list<string> */
    private array $draftCalls = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 2) . '/support/CustomerAuthPdoStore.php';

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_COOKIE = [];
        unset(
            $_REQUEST['ms3_token'],
            $_REQUEST['token'],
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_MS3TOKEN']
        );

        $this->store = $this->createStore();
        $this->store->reset();
        $this->draftCalls = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        unset(
            $_REQUEST['ms3_token'],
            $_REQUEST['token'],
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_MS3TOKEN']
        );
    }

    protected function createStore(): CustomerAuthPdoStore
    {
        return CustomerAuthPdoStore::sqliteMemory();
    }

    public function testAuthenticateRejectsBlockedAndInactive(): void
    {
        $blocked = $this->seedCustomer([
            'email' => 'blocked@example.com',
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $inactive = $this->seedCustomer([
            'email' => 'inactive@example.com',
            'is_active' => 0,
            'is_blocked' => 0,
        ]);

        $modx = $this->makeModx();
        $auth = $this->makeAuthManager($modx);
        self::assertNull($auth->authenticate(['email' => 'blocked@example.com', 'password' => 'secret']));
        self::assertSame('blocked', $auth->getLastAuthFailure());

        $auth = $this->makeAuthManager($modx);
        self::assertNull($auth->authenticate(['email' => 'inactive@example.com', 'password' => 'secret']));
        self::assertSame('inactive', $auth->getLastAuthFailure());
    }

    public function testEstablishSessionRotatesTokenAndLogoutMintsGuest(): void
    {
        $customer = $this->seedCustomer([
            'email' => 'buyer@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);

        $modx = $this->makeModx();
        $tokenService = new TokenService($modx);

        // Plant a guest token in the browser (session + cookie).
        $guest = $tokenService->persistApiToken(0, null, 3600);
        self::assertNotNull($guest);
        $guestToken = (string) $guest->get('token');
        self::assertSame(0, (int) ($_SESSION['ms3']['customer_id'] ?? -1));
        self::assertSame($guestToken, $_SESSION['ms3']['customer_token'] ?? null);

        $auth = $this->makeAuthManager($modx);
        $authed = $auth->authenticate(['email' => 'buyer@example.com', 'password' => 'secret']);
        self::assertNotNull($authed);
        self::assertSame('none', $auth->getLastAuthFailure());
        self::assertNotEmpty($authed->get('last_login_at'));

        $session = $auth->establishCustomerSession($authed);
        self::assertNotNull($session);
        $loginToken = $session['token'];
        self::assertNotSame($guestToken, $loginToken);
        self::assertSame((int) $customer->id, (int) ($_SESSION['ms3']['customer_id'] ?? 0));
        self::assertSame($loginToken, $_SESSION['ms3']['customer_token'] ?? null);
        self::assertSame($loginToken, $_COOKIE['ms3_token'] ?? null);

        // Previous guest token must be gone (anti fixation).
        self::assertNull($this->store->findToken(['token' => $guestToken, 'type' => msCustomerToken::TYPE_API]));
        self::assertSame(1, $this->store->countTokens((int) $customer->id, msCustomerToken::TYPE_API));
        self::assertContains('transfer:' . $guestToken . '=>' . $loginToken, $this->draftCalls);

        self::assertTrue($auth->logoutCurrentCustomer());
        self::assertSame(0, (int) ($_SESSION['ms3']['customer_id'] ?? 0));
        self::assertSame(0, $this->store->countTokens((int) $customer->id, msCustomerToken::TYPE_API));

        $guestAfter = $_SESSION['ms3']['customer_token'] ?? '';
        self::assertNotSame('', $guestAfter);
        self::assertNotSame($loginToken, $guestAfter);
        $guestRow = $this->store->findToken(['token' => $guestAfter, 'type' => msCustomerToken::TYPE_API]);
        self::assertNotNull($guestRow);
        self::assertSame(0, (int) $guestRow['customer_id']);
    }

    public function testForeignTokenDoesNotTransferCartButIsRevoked(): void
    {
        $customer = $this->seedCustomer([
            'email' => 'buyer@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);
        $other = $this->seedCustomer([
            'email' => 'other@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);

        $modx = $this->makeModx();
        $tokenService = new TokenService($modx);
        $planted = $tokenService->persistApiToken((int) $other->id, null, 3600);
        self::assertNotNull($planted);
        $plantedToken = (string) $planted->get('token');

        $auth = $this->makeAuthManager($modx);
        $authed = $auth->authenticate(['email' => 'buyer@example.com', 'password' => 'secret']);
        self::assertNotNull($authed);

        $session = $auth->establishCustomerSession($authed);
        self::assertNotNull($session);
        self::assertNull($this->store->findToken(['token' => $plantedToken, 'type' => msCustomerToken::TYPE_API]));
        self::assertTrue(
            (bool) array_filter(
                $this->draftCalls,
                static fn(string $call): bool => str_starts_with($call, 'bind:')
            )
        );
        self::assertFalse(
            (bool) array_filter(
                $this->draftCalls,
                static fn(string $call): bool => str_starts_with($call, 'transfer:')
            )
        );
    }

    public function testRotateApiTokenRevokesOldAndKeepsCustomer(): void
    {
        $customer = $this->seedCustomer([
            'email' => 'buyer@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);

        $modx = $this->makeModx();
        $tokenService = new TokenService($modx);
        $current = $tokenService->persistApiToken((int) $customer->id, null, 3600);
        self::assertNotNull($current);
        $oldToken = (string) $current->get('token');

        $rotated = $tokenService->rotateApiToken($oldToken);
        self::assertNotNull($rotated);
        self::assertNotSame($oldToken, $rotated['token']);
        self::assertSame((int) $customer->id, $rotated['customer_id']);
        self::assertNull($this->store->findToken(['token' => $oldToken, 'type' => msCustomerToken::TYPE_API]));
        self::assertNotNull($this->store->findToken(['token' => $rotated['token'], 'type' => msCustomerToken::TYPE_API]));
        self::assertContains('transfer:' . $oldToken . '=>' . $rotated['token'], $this->draftCalls);

        self::assertNull($tokenService->rotateApiToken($oldToken));
    }

    public function testGetBindableTokenStringPrefersBearerOverSession(): void
    {
        $modx = $this->makeModx();
        $tokenService = new TokenService($modx);
        $bearer = $tokenService->persistApiToken(0, null, 3600);
        self::assertNotNull($bearer);
        $bearerToken = (string) $bearer->get('token');

        $_SESSION['ms3']['customer_token'] = 'session-only-token';
        $_SESSION['ms3']['customer_token_expires'] = time() + 3600;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearerToken;
        unset($_REQUEST['ms3_token'], $_REQUEST['token'], $_COOKIE['ms3_token']);

        self::assertSame($bearerToken, $tokenService->getBindableTokenString());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testGetBindableTokenStringIgnoresInvalidBearerAndUsesSession(): void
    {
        $modx = $this->makeModx();
        $tokenService = new TokenService($modx);
        $guest = $tokenService->persistApiToken(0, null, 3600);
        self::assertNotNull($guest);
        $guestToken = (string) $guest->get('token');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer dead-or-revoked-token';
        unset($_REQUEST['ms3_token'], $_REQUEST['token'], $_COOKIE['ms3_token']);

        self::assertSame($guestToken, $tokenService->getBindableTokenString());
        self::assertSame($guestToken, $_SESSION['ms3']['customer_token'] ?? null);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testValidateTokenAndRevokeTokens(): void
    {
        $customer = $this->seedCustomer([
            'email' => 'buyer@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);
        $modx = $this->makeModx();
        $auth = $this->makeAuthManager($modx);

        $token = $auth->createToken($customer, msCustomerToken::TYPE_API, 3600);
        self::assertNotNull($token);
        $validated = $auth->validateToken((string) $token->get('token'), msCustomerToken::TYPE_API);
        self::assertNotNull($validated);
        self::assertSame((int) $customer->id, (int) $validated->id);

        $expired = new StoredMsCustomerToken();
        $expired->bindStore($this->store);
        $expired->set('customer_id', (int) $customer->id);
        $expired->set('token', 'expired-' . bin2hex(random_bytes(8)));
        $expired->set('type', msCustomerToken::TYPE_API);
        $expired->set('expires_at', date('Y-m-d H:i:s', time() - 10));
        $expired->set('created_at', date('Y-m-d H:i:s'));
        self::assertTrue($expired->save());
        self::assertNull($auth->validateToken((string) $expired->get('token'), msCustomerToken::TYPE_API));
        self::assertNull($this->store->findToken(['token' => (string) $expired->get('token')]));

        self::assertSame(1, $auth->revokeTokens($customer, msCustomerToken::TYPE_API));
        self::assertSame(0, $this->store->countTokens((int) $customer->id, msCustomerToken::TYPE_API));
    }

    public function testHandleFailedLoginBlocksAfterMaxAttempts(): void
    {
        $customer = $this->seedCustomer([
            'email' => 'buyer@example.com',
            'is_active' => 1,
            'is_blocked' => 0,
            'failed_login_attempts' => 0,
        ]);
        $modx = $this->makeModx(['ms3_customer_max_login_attempts' => 3, 'ms3_customer_block_duration' => 1800]);
        $auth = $this->makeAuthManager($modx);

        $auth->handleFailedLogin($customer);
        $auth->handleFailedLogin($customer);
        self::assertFalse((bool) $customer->get('is_blocked'));
        $auth->handleFailedLogin($customer);
        self::assertTrue((bool) $customer->get('is_blocked'));
        self::assertSame(3, (int) $customer->get('failed_login_attempts'));
        self::assertNotEmpty($customer->get('blocked_until'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function seedCustomer(array $fields): StoredMsCustomer
    {
        if (!isset($fields['password']) || $fields['password'] === '') {
            $fields['password'] = password_hash('secret', PASSWORD_BCRYPT);
        } elseif (!str_starts_with((string) $fields['password'], '$2')) {
            $fields['password'] = password_hash((string) $fields['password'], PASSWORD_BCRYPT);
        }
        $id = $this->store->insertCustomer($fields);
        $row = $this->store->findCustomerById($id);
        self::assertNotNull($row);
        $customer = new StoredMsCustomer();
        $customer->bindStore($this->store);
        $customer->hydrate($row);

        return $customer;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeModx(array $options = []): modX
    {
        $store = $this->store;
        $draftCalls = &$this->draftCalls;

        $orderDraftManager = $this->createStub(OrderDraftManager::class);
        $orderDraftManager->method('transferDraftToToken')->willReturnCallback(
            static function (string $fromToken, string $toToken, int $customerId, string $ctx = 'web') use (&$draftCalls): bool {
                $draftCalls[] = 'transfer:' . $fromToken . '=>' . $toToken;

                return true;
            }
        );
        $orderDraftManager->method('bindDraftToCustomer')->willReturnCallback(
            static function (string $token, int $customerId, string $ctx = 'web') use (&$draftCalls): bool {
                $draftCalls[] = 'bind:' . $token;

                return true;
            }
        );

        $modx = new class ($store, $options, $orderDraftManager) extends modX {
            /**
             * @param array<string, mixed> $options
             */
            public function __construct(
                private CustomerAuthPdoStore $store,
                private array $options,
                OrderDraftManager $orderDraftManager,
            ) {
                parent::__construct();
                $tokenService = new TokenService($this);
                $this->services = new class ($tokenService, $orderDraftManager) {
                    public function __construct(
                        private TokenService $tokenService,
                        private OrderDraftManager $orderDraftManager,
                    ) {
                    }

                    public function has(string $key): bool
                    {
                        return in_array($key, ['ms3_token_service', 'ms3_order_draft_manager'], true);
                    }

                    public function get(string $key): mixed
                    {
                        return match ($key) {
                            'ms3_token_service' => $this->tokenService,
                            'ms3_order_draft_manager' => $this->orderDraftManager,
                            default => null,
                        };
                    }
                };
            }

            public function getOption($key, $options = null, $default = null, $skipEmpty = false)
            {
                $name = (string) $key;
                if (array_key_exists($name, $this->options)) {
                    return $this->options[$name];
                }

                return match ($name) {
                    'ms3_customer_token_ttl' => 604800,
                    'ms3_customer_max_login_attempts' => 5,
                    'ms3_customer_block_duration' => 3600,
                    'session_cookie_domain' => '',
                    'session_cookie_path' => '/',
                    'session_cookie_secure' => false,
                    'session_cookie_samesite' => 'Lax',
                    default => $default,
                };
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomer::class) {
                    $row = null;
                    if (is_numeric($criteria)) {
                        $row = $this->store->findCustomerById((int) $criteria);
                    } elseif (is_array($criteria)) {
                        if (isset($criteria['id'])) {
                            $row = $this->store->findCustomerById((int) $criteria['id']);
                        } elseif (isset($criteria['email'])) {
                            $row = $this->store->findCustomerByEmail((string) $criteria['email']);
                        }
                    }
                    if ($row === null) {
                        return null;
                    }
                    $customer = new StoredMsCustomer();
                    $customer->bindStore($this->store);
                    $customer->hydrate($row);

                    return $customer;
                }

                if ($className === msCustomerToken::class && is_array($criteria)) {
                    $row = $this->store->findToken($criteria);
                    if ($row === null) {
                        return null;
                    }
                    $token = new StoredMsCustomerToken();
                    $token->bindStore($this->store);
                    $token->hydrate($row);

                    return $token;
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                if ($className === msCustomerToken::class) {
                    $token = new StoredMsCustomerToken();
                    $token->bindStore($this->store);
                    if (is_array($fields) && $fields !== []) {
                        foreach ($fields as $key => $value) {
                            $token->set($key, $value);
                        }
                    }

                    return $token;
                }

                if ($className === msCustomer::class) {
                    $customer = new StoredMsCustomer();
                    $customer->bindStore($this->store);

                    return $customer;
                }

                return null;
            }

            public function getCollection($className, $criteria = null, $cacheFlag = true)
            {
                if ($className !== msCustomerToken::class) {
                    return [];
                }
                $rows = $this->store->findTokens(is_array($criteria) ? $criteria : []);
                $items = [];
                foreach ($rows as $row) {
                    $token = new StoredMsCustomerToken();
                    $token->bindStore($this->store);
                    $token->hydrate($row);
                    $items[] = $token;
                }

                return $items;
            }
        };

        return $modx;
    }

    private function makeAuthManager(modX $modx): AuthManager
    {
        return new AuthManager($modx);
    }
}
