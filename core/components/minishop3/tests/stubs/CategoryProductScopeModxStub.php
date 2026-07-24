<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * modX stub: getObject(msProduct) resolves by id + parent (category scope).
 */
class CategoryProductScopeModxStub extends modX
{
    /** @var list<array{id: int, parent: int, published?: int, deleted?: int}> */
    public array $products = [];

    /** @var list<array{class: class-string, criteria: mixed}> */
    public array $getObjectCalls = [];

    public function getObject($className = '', $criteria = null, $cacheFlag = true)
    {
        $this->getObjectCalls[] = ['class' => $className, 'criteria' => $criteria];

        if ($className !== msProduct::class || !is_array($criteria)) {
            return null;
        }

        $productId = (int) ($criteria['id'] ?? 0);
        $parentId = (int) ($criteria['parent'] ?? 0);

        foreach ($this->products as $row) {
            if ((int) $row['id'] === $productId && (int) $row['parent'] === $parentId) {
                return new StubMsProduct($row);
            }
        }

        return null;
    }
}
