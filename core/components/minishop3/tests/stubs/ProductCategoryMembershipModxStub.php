<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * modX stub for ProductCategoryMembershipWriter upsert tests (#625).
 */
class ProductCategoryMembershipModxStub extends modX
{
    /** @var list<array{product_id: int, category_id: int, menuindex: int}> */
    public array $members = [];

    /** @var list<array{parent: int, menuindex: int}> */
    public array $nativeProducts = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param class-string $className
     * @return list<StubMsCategoryMemberForWriter>
     */
    public function getCollection($className, $criteria = null, $cacheFlag = false)
    {
        if ($className !== msCategoryMember::class || !is_array($criteria)) {
            return [];
        }

        $productId = (int) ($criteria['product_id'] ?? 0);

        return array_map(
            fn(array $row): StubMsCategoryMemberForWriter => new StubMsCategoryMemberForWriter($row, $this),
            array_values(array_filter(
                $this->members,
                static fn(array $row): bool => (int) $row['product_id'] === $productId
            ))
        );
    }

    /**
     * @param class-string $className
     */
    public function newObject($className = '', $fields = [])
    {
        if ($className !== msCategoryMember::class) {
            return null;
        }

        return new StubMsCategoryMemberForWriter([
            'product_id' => 0,
            'category_id' => 0,
            'menuindex' => 0,
        ], $this);
    }

    public function newQuery($class = '', $criteria = null)
    {
        return new MembershipWriterQueryStub($this, (string) $class);
    }
}

class StubMsCategoryMemberForWriter
{
    /** @var array<string, mixed> */
    private array $data;

    private ?ProductCategoryMembershipModxStub $modx;

    private bool $removed = false;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, ?ProductCategoryMembershipModxStub $modx = null)
    {
        $this->data = $data;
        $this->modx = $modx;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function save(): bool
    {
        if ($this->removed || $this->modx === null) {
            return false;
        }

        $productId = (int) $this->data['product_id'];
        $categoryId = (int) $this->data['category_id'];
        $found = false;

        foreach ($this->modx->members as $index => $row) {
            if ((int) $row['product_id'] === $productId && (int) $row['category_id'] === $categoryId) {
                $this->modx->members[$index] = [
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                    'menuindex' => (int) ($this->data['menuindex'] ?? 0),
                ];
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->modx->members[] = [
                'product_id' => $productId,
                'category_id' => $categoryId,
                'menuindex' => (int) ($this->data['menuindex'] ?? 0),
            ];
        }

        return true;
    }

    public function remove(): void
    {
        if ($this->modx === null) {
            return;
        }

        $this->removed = true;
        $productId = (int) $this->data['product_id'];
        $categoryId = (int) $this->data['category_id'];
        $this->modx->members = array_values(array_filter(
            $this->modx->members,
            static fn(array $row): bool => !((int) $row['product_id'] === $productId && (int) $row['category_id'] === $categoryId)
        ));
    }
}

class MembershipWriterQueryStub
{
    /** @var array<string, mixed> */
    private array $where = [];

    /** @var MembershipWriterStatementStub|null */
    public $stmt;

    public function __construct(
        private ProductCategoryMembershipModxStub $modxStub,
        private string $class,
    ) {
    }

    public function where($conditions = '', $conj = '', $binding = null)
    {
        if (is_array($conditions)) {
            $this->where = array_merge($this->where, $conditions);
        }

        return $this;
    }

    public function select($columns = '*')
    {
        return $this;
    }

    public function prepare($bindings = null)
    {
        $this->stmt = new MembershipWriterStatementStub($this->modxStub, $this->class, $this->where);

        return true;
    }
}

class MembershipWriterStatementStub
{
    public function __construct(
        private ProductCategoryMembershipModxStub $modxStub,
        private string $class,
        /** @var array<string, mixed> */
        private array $where,
    ) {
    }

    public function execute($params = null)
    {
        return true;
    }

    public function fetchColumn($column = 0)
    {
        if ($this->class === msCategoryMember::class) {
            $categoryId = (int) ($this->where['category_id'] ?? 0);
            $max = -1;
            foreach ($this->modxStub->members as $row) {
                if ((int) $row['category_id'] === $categoryId) {
                    $max = max($max, (int) $row['menuindex']);
                }
            }

            return $max >= 0 ? $max : false;
        }

        if ($this->class === msProduct::class) {
            $parent = (int) ($this->where['parent'] ?? 0);
            $max = -1;
            foreach ($this->modxStub->nativeProducts as $row) {
                if ((int) $row['parent'] === $parent) {
                    $max = max($max, (int) $row['menuindex']);
                }
            }

            return $max >= 0 ? $max : false;
        }

        return false;
    }
}
