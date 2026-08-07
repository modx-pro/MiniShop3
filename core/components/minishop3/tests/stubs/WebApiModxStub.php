<?php

declare(strict_types=1);

namespace MODX\Revolution;

use MiniShop3\Model\msCustomer;
use MiniShop3\Tests\Stubs\FakeMsCustomer;
use MiniShop3\Tests\Stubs\ProcessorResponseStub;

/**
 * modX stub for Web API dispatch tests (router + auth endpoints, no MySQL).
 */
class WebApiModxStub extends modX
{
    /** @var callable|null */
    public $runProcessorHandler;

    /** @var array<int, msCustomer> */
    public array $customers = [];

    /** @var object|null TokenService-shaped double for TokenMiddleware */
    public $tokenService;

    /** @var object */
    public $lexicon;

    /** @var object */
    public $cacheManager;

    public function __construct()
    {
        parent::__construct();

        $this->tokenService = new class {
            public function resolveApiToken(string $token): array
            {
                return ['token' => null, 'reason' => 'missing'];
            }

            public function sessionTokenBelongsToCustomer(int $customerId): bool
            {
                return $customerId > 0;
            }

            public function generateCustomerToken(): array
            {
                return ['token' => 'anon-test-token'];
            }
        };

        $this->services = new class ($this) {
            public function __construct(private WebApiModxStub $modx)
            {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3' || $key === 'ms3_token_service';
            }

            public function get(string $key): mixed
            {
                return match ($key) {
                    'ms3_token_service' => $this->modx->tokenService,
                    default => null,
                };
            }
        };

        $this->lexicon = new class {
            private array $strings = [];

            public function load(string $topic): void
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

        $this->cacheManager = new class {
            private array $store = [];

            public function get(string $key, array &$options = []): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function set(string $key, mixed $value, int $ttl = 0): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public function runProcessor(string $action, array $data = [], array $options = []): ProcessorResponseStub
    {
        if ($this->runProcessorHandler !== null) {
            return ($this->runProcessorHandler)($action, $data, $options);
        }

        return ProcessorResponseStub::failure('Unhandled processor: ' . $action);
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

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function putCustomer(int $id, array $fields = []): FakeMsCustomer
    {
        $customer = new FakeMsCustomer(array_merge([
            'id' => $id,
            'is_active' => true,
            'is_blocked' => false,
            'blocked_until' => null,
        ], $fields));
        $this->customers[$id] = $customer;

        return $customer;
    }
}
