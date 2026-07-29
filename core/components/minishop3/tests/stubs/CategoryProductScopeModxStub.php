<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * modX stub: scoped msProduct lookups + optional category tree for nested mode.
 */
class CategoryProductScopeModxStub extends modX
{
    /** @var list<array{id: int, parent: int, published?: int, deleted?: int}> */
    public array $products = [];

    /** @var list<array{id: int, parent: int}> */
    public array $categories = [];

    /** @var list<array{class: class-string, criteria: mixed}> */
    public array $getObjectCalls = [];

    public function __construct()
    {
        parent::__construct();
        $this->services = new class {
            public function get(string $key): null
            {
                return null;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };
    }

    public function getObject($className = '', $criteria = null, $cacheFlag = true)
    {
        $this->getObjectCalls[] = ['class' => $className, 'criteria' => $criteria];

        if ($className === msCategory::class) {
            $categoryId = is_array($criteria)
                ? (int) ($criteria['id'] ?? 0)
                : (int) $criteria;

            if ($categoryId <= 0) {
                return null;
            }

            foreach ($this->categories as $row) {
                if ((int) $row['id'] === $categoryId) {
                    return new StubMsCategory($row);
                }
            }

            // Category ids used as product parents / API path params (direct children grid).
            foreach ($this->products as $product) {
                if ((int) ($product['parent'] ?? 0) === $categoryId) {
                    return new StubMsCategory(['id' => $categoryId]);
                }
            }

            return null;
        }

        if ($className !== msProduct::class) {
            return null;
        }

        if (is_array($criteria)) {
            $productId = (int) ($criteria['id'] ?? 0);
            $parentId = (int) ($criteria['parent'] ?? 0);

            foreach ($this->products as $row) {
                if ((int) $row['id'] === $productId && (int) $row['parent'] === $parentId) {
                    return new StubMsProduct($row);
                }
            }

            return null;
        }

        $productId = (int) $criteria;
        foreach ($this->products as $row) {
            if ((int) $row['id'] === $productId) {
                return new StubMsProduct($row);
            }
        }

        return null;
    }

    public function getIterator($className, $criteria = null)
    {
        if ($className !== msCategory::class || !is_array($criteria)) {
            return new \ArrayIterator([]);
        }

        $parentId = (int) ($criteria['parent'] ?? 0);
        $rows = array_values(array_filter(
            $this->categories,
            static fn(array $row): bool => (int) $row['parent'] === $parentId
        ));

        return new \ArrayIterator(array_map(
            static fn(array $row): object => new class ($row) {
                public function __construct(private array $row)
                {
                }

                public function get(string $key): mixed
                {
                    return $this->row[$key] ?? null;
                }
            },
            $rows
        ));
    }
}
