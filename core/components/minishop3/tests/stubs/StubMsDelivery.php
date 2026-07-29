<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msDelivery;

final class StubMsDelivery extends msDelivery
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

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }
}
