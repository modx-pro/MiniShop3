<?php

declare(strict_types=1);

namespace MODX\Revolution;

use MiniShop3\Model\msCustomer;
use MiniShop3\Tests\Stubs\ProcessorResponseStub;

/**
 * modX stub for Web API dispatch tests (router + auth endpoints, no MySQL).
 */
class WebApiModxStub extends modX
{
    /** @var callable|null */
    public $runProcessorHandler;

    /** @var array<int, object> */
    public array $customers = [];

    /** @var object */
    public $lexicon;

    /** @var object */
    public $cacheManager;

    public function __construct()
    {
        parent::__construct();

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
}
