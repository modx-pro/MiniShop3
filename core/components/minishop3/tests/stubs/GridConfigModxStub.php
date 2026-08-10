<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MODX\Revolution\modX;

/**
 * modX stub that counts msGridField collection loads for GridConfigService tests.
 */
final class GridConfigModxStub extends modX
{
    public int $getCollectionCalls = 0;

    /** @var list<object> */
    public array $gridFields = [];

    /** @var object */
    public $lexicon;

    public function __construct()
    {
        parent::__construct();
        $this->lexicon = new class {
            public function load(string $topic): void
            {
            }

            public function __invoke(string $key): string
            {
                return $key;
            }
        };
    }

    public function newQuery($class = ''): GridConfigQueryStub
    {
        return new GridConfigQueryStub();
    }

    public function getCollection($className, $criteria = null): array
    {
        $this->getCollectionCalls++;

        return $this->gridFields;
    }

    public function getObject($className, $criteria = null, $cacheFlag = true): ?object
    {
        return null;
    }
}
