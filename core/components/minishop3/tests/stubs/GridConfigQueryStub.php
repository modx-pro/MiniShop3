<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

/**
 * Fluent query stub — GridConfigService only chains where/sortby before getCollection.
 */
final class GridConfigQueryStub
{
    public function where(array $criteria): self
    {
        return $this;
    }

    public function sortby(string $column, string $direction = 'ASC'): self
    {
        return $this;
    }

    public function limit(int $limit): self
    {
        return $this;
    }
}
