<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

/**
 * Minimal msCategoryMember stand-in for scope / menuindex smoke tests.
 */
class StubMsCategoryMember
{
    /** @var array<string, mixed> */
    private array $data;

    private CategoryProductScopeModxStub $modx;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, CategoryProductScopeModxStub $modx)
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
        $productId = (int) $this->data['product_id'];
        $categoryId = (int) $this->data['category_id'];

        foreach ($this->modx->members as $index => $row) {
            if ((int) $row['product_id'] === $productId && (int) $row['category_id'] === $categoryId) {
                $this->modx->members[$index] = $this->data;

                return true;
            }
        }

        return false;
    }
}
