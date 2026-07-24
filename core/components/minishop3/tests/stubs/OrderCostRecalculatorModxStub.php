<?php

declare(strict_types=1);

namespace MODX\Revolution;

/**
 * modX stub with object registry for ManagerOrderCostRecalculator smoke tests.
 */
class OrderCostRecalculatorModxStub extends modX
{
    /** @var array<string, list<object>> */
    private array $objectsByClass = [];

    /** @var array<string, list<object>> */
    private array $iteratorsByClass = [];

    public function lexicon(string $key, array $placeholders = []): string
    {
        return $key;
    }

    public function getOption(string $key, $options = null, $default = null, $skipEvents = false): mixed
    {
        return $default;
    }

    public $services;

    public function __construct()
    {
        parent::__construct();
        $this->services = new class($this) {
            public function __construct(private OrderCostRecalculatorModxStub $modx)
            {
            }

            public function get(string $key): object
            {
                if ($key === 'ms3_order_service') {
                    return new \MiniShop3\Services\Order\OrderService($this->modx);
                }

                throw new \RuntimeException('Unknown service: ' . $key);
            }

            public function has(string $key): bool
            {
                return false;
            }
        };
    }

    public function registerObject(string $class, object $object): void
    {
        $this->objectsByClass[$class][] = $object;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function getObject(string $class, array $criteria = []): ?object
    {
        foreach ($this->objectsByClass[$class] ?? [] as $object) {
            if ($this->matchesCriteria($object, $criteria)) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return iterable<object>
     */
    public function getIterator(string $class, array $criteria = []): iterable
    {
        foreach ($this->iteratorsByClass[$class] ?? [] as $object) {
            if ($this->matchesCriteria($object, $criteria)) {
                yield $object;
            }
        }
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function registerIteratorObject(string $class, object $object): void
    {
        $this->iteratorsByClass[$class][] = $object;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(object $object, array $criteria): bool
    {
        foreach ($criteria as $key => $expected) {
            if (!method_exists($object, 'get')) {
                return false;
            }
            if ($object->get($key) != $expected) {
                return false;
            }
        }

        return true;
    }
}
