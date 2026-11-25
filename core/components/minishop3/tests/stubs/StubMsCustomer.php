<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msCustomer;

/**
 * Minimal msCustomer stand-in for DTO serialization tests (#424).
 */
final class StubMsCustomer extends msCustomer
{
    /** @var array<string, mixed> */
    private array $fields;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }
}
