<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

/**
 * Minimal msProduct stand-in for scope / controller smoke tests.
 */
class StubMsProduct
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
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
        return true;
    }
}
