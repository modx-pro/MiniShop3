<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * modX stub: getObject(msProduct) с управляемым ACL документа.
 *
 * Товар, которого нет в $products, отдаётся как null — ровно так ведёт себя
 * настоящий getObject() для ресурса, закрытого группой ресурсов (#922).
 */
class ProductDocumentPolicyModxStub extends modX
{
    /** @var array<int, array<string, bool>|null> id => политики (null = разрешено всё) */
    public array $products = [];

    /** @var list<int> id, по которым спрашивали товар */
    public array $lookups = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $className
     * @param mixed $criteria
     * @param mixed $cacheFlag
     */
    public function getObject($className, $criteria = null, $cacheFlag = true)
    {
        if ($className !== msProduct::class) {
            return null;
        }

        $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
        $this->lookups[] = $id;

        if (!array_key_exists($id, $this->products)) {
            return null;
        }

        return new StubMsProduct(['id' => $id], $this->products[$id]);
    }
}
