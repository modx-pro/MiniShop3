<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

use MiniShop3\Model\msCustomer;
use MiniShop3\Tests\Stubs\ProcessorResponseStub;
use MODX\Revolution\WebApiModxStub;

/**
 * WebApiModxStub extended with journey DI (ms3, catalog, customer orders).
 */
final class JourneyWebApiModx extends WebApiModxStub
{
    public JourneyMs3 $ms3;

    public JourneyTokenService $journeyTokens;

    public JourneyProductCatalog $catalog;

    public JourneyCustomerOrderService $customerOrders;

    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct()
    {
        parent::__construct();

        $this->context = new class {
            public string $key = 'web';

            public function get(string $field): string
            {
                return $field === 'key' ? $this->key : '';
            }
        };

        $this->ms3 = new JourneyMs3();
        $this->journeyTokens = new JourneyTokenService();
        $this->tokenService = $this->journeyTokens;
        $this->catalog = new JourneyProductCatalog($this);
        $this->customerOrders = new JourneyCustomerOrderService($this);

        $rlPath = sys_get_temp_dir() . '/ms3-webapi-rl-' . getmypid();
        if (!is_dir($rlPath)) {
            mkdir($rlPath, 0777, true);
        }

        $this->options = [
            'ms3_cors_allowed_origins' => 'https://shop.example',
            'ms3_rate_limit_max_attempts' => 10000,
            'ms3_rate_limit_decay_seconds' => 60,
            'ms3_rate_limit_storage_path' => $rlPath,
            'ms3_customer_token_ttl' => 604800,
            'session_cookie_path' => '/',
            'session_cookie_domain' => '',
            'session_cookie_secure' => false,
            'session_cookie_samesite' => 'Lax',
        ];

        $this->services = new class ($this) {
            public function __construct(private JourneyWebApiModx $modx)
            {
            }

            public function has(string $key): bool
            {
                return in_array($key, [
                    'ms3',
                    'ms3_token_service',
                    'ms3_product_catalog',
                    'ms3_customer_order',
                ], true);
            }

            public function get(string $key): mixed
            {
                return match ($key) {
                    'ms3' => $this->modx->ms3,
                    'ms3_token_service' => $this->modx->tokenService,
                    'ms3_product_catalog' => $this->modx->catalog,
                    'ms3_customer_order' => $this->modx->customerOrders,
                    default => null,
                };
            }
        };

        $this->lexicon = new class {
            /** @var array<string, string> */
            private array $strings = [];

            public function load(string ...$topics): void
            {
            }

            public function setStrings(array $strings): void
            {
                $this->strings = $strings;
            }

            public function lexicon(string $key, array $options = []): string
            {
                $value = $this->strings[$key] ?? $key;
                foreach ($options as $placeholder => $replacement) {
                    $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
                }

                return $value;
            }
        };
    }

    public function setTokenService(object $tokenService): void
    {
        $this->tokenService = $tokenService;
        if ($tokenService instanceof JourneyTokenService) {
            $this->journeyTokens = $tokenService;
        }
    }

    public function getOption(string $key, $options = null, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $map
     */
    public function setOptions(array $map): void
    {
        $this->options = array_merge($this->options, $map);
    }

    /**
     * @param class-string|array<string, mixed>|int|string $className
     * @param array<string, mixed>|int|string|null $criteria
     */
    public function getObject($className, $criteria = '', bool $cacheFlag = true): ?object
    {
        if ($className === msCustomer::class || $className === 'MiniShop3\\Model\\msCustomer') {
            $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;

            return $this->customers[$id] ?? null;
        }

        return parent::getObject($className, $criteria, $cacheFlag);
    }

    /**
     * Default auth processors for journey login/register (body may be empty — stubs ignore fields).
     * On login/register: mint user token and transfer guest cart bag (#412/#574).
     */
    public function installAuthProcessors(int $customerId = 42): void
    {
        $this->putCustomer($customerId);
        $tokens = $this->journeyTokens;
        $ms3 = $this->ms3;

        $this->runProcessorHandler = static function (string $action, array $data) use ($tokens, $customerId, $ms3): ProcessorResponseStub {
            unset($data);

            if ($action === 'MiniShop3\\Processors\\Api\\Customer\\Login'
                || $action === 'MiniShop3\\Processors\\Api\\Customer\\Register'
            ) {
                $guestToken = (string) ($_REQUEST['ms3_token'] ?? '');
                $token = 'user-journey-token-' . $customerId;
                $tokens->putToken($token, $customerId);

                if ($ms3->cart instanceof JourneyCart && $guestToken !== '' && $guestToken !== $token) {
                    $ms3->cart->transfer($guestToken, $token);
                    $tokens->forget($guestToken);
                }

                return ProcessorResponseStub::success([
                    'token' => $token,
                    'customer_id' => $customerId,
                ], 'authenticated');
            }

            if ($action === 'MiniShop3\\Processors\\Api\\Customer\\Logout') {
                return ProcessorResponseStub::success(null, 'logged out');
            }

            return ProcessorResponseStub::failure('Unhandled processor: ' . $action);
        };
    }
}
