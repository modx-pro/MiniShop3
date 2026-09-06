<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msOrder;

final class StubMsOrder extends msOrder
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

    public function set($k, $v = null, $v2 = null)
    {
        $this->fields[$k] = $v;

        return true;
    }

    public function getOne($alias)
    {
        return $this->fields['_related'][$alias] ?? null;
    }
}
