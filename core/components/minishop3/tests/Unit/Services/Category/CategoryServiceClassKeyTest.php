<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Category;

require_once __DIR__ . '/../../../stubs/ModxStub.php';

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Category\CategoryService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #855: MODX 3 keeps FQCN in class_key; short names must still be accepted.
 */
final class CategoryServiceClassKeyTest extends TestCase
{
    public function testClassKeyHelpersAcceptBothForms(): void
    {
        self::assertTrue(CategoryService::isCategoryClassKey(msCategory::class));
        self::assertTrue(CategoryService::isCategoryClassKey('msCategory'));
        self::assertTrue(CategoryService::isProductClassKey(msProduct::class));
        self::assertTrue(CategoryService::isProductClassKey('msProduct'));
        self::assertFalse(CategoryService::isCategoryClassKey(msProduct::class));
    }

    public function testShouldResetOnlyOnRealConversion(): void
    {
        self::assertTrue(CategoryService::shouldResetOnSave('modDocument', msCategory::class));
        self::assertTrue(CategoryService::shouldResetOnSave('MiniShop3\\Model\\msProduct', 'msCategory'));
        self::assertFalse(CategoryService::shouldResetOnSave(msCategory::class, msCategory::class));
        self::assertFalse(CategoryService::shouldResetOnSave('msCategory', 'msCategory'));
        self::assertFalse(CategoryService::shouldResetOnSave('modDocument', msCategory::class, true));
        self::assertFalse(CategoryService::shouldResetOnSave(msCategory::class, 'modDocument'));
        // Unknown stored key is not a conversion.
        self::assertFalse(CategoryService::shouldResetOnSave('', msCategory::class));
    }

    public function testConversionMarkerIsConsumedOnce(): void
    {
        CategoryService::rememberConversion(42, 'modDocument');
        self::assertSame('modDocument', CategoryService::consumeConversion(42));
        self::assertSame('', CategoryService::consumeConversion(42));
    }

    public function testHandleCategorySaveOnlyForRealTypeChange(): void
    {
        $modx = $this->modx([]);
        $service = new CategoryService($modx);

        $converted = $this->category(['class_key' => msCategory::class]);
        $service->handleCategorySave($converted, msProduct::class);
        self::assertFalse($converted->get('hide_children_in_tree'));
        self::assertSame(
            ['parent' => 7, 'class_key:IN' => [msProduct::class, 'msProduct']],
            $modx->queries[0]['where'] ?? null
        );

        $sameType = $this->category(['class_key' => msCategory::class]);
        $service->handleCategorySave($sameType, msCategory::class);
        self::assertTrue($sameType->get('hide_children_in_tree'));

        $legacy = $this->category(['class_key' => 'msCategory']);
        $service->handleCategorySave($legacy, 'msCategory');
        self::assertTrue($legacy->get('hide_children_in_tree'));

        // Converting away from a category must not reset the flag either.
        $away = $this->category(['class_key' => 'modDocument']);
        $service->handleCategorySave($away, msCategory::class);
        self::assertTrue($away->get('hide_children_in_tree'));
    }

    public function testNeighborCategoriesAcceptFqcnRows(): void
    {
        $modx = $this->modx([2, 7, 9]);
        $service = new CategoryService($modx);

        $neighbors = $service->getNeighborCategories($this->category(['id' => 7, 'parent' => 3]));

        self::assertSame(['left' => [2], 'right' => [9]], $neighbors);
        self::assertSame(
            ['parent' => 3, 'class_key:IN' => [msCategory::class, 'msCategory']],
            $modx->queries[0]['where'] ?? null
        );
    }

    /** @param array<string, mixed> $overrides */
    private function category(array $overrides): msCategory
    {
        $xpdo = new class extends \xPDO\xPDO {
        };

        return new class ($xpdo, $overrides) extends msCategory {
            /** @var array<string, mixed> */
            private array $values;

            /** @param array<string, mixed> $overrides */
            public function __construct(\xPDO\xPDO &$xpdo, array $overrides)
            {
                parent::__construct($xpdo);
                $this->values = array_merge([
                    'id' => 7,
                    'parent' => 3,
                    'class_key' => msCategory::class,
                    'hide_children_in_tree' => true,
                ], $overrides);
            }

            public function isNew()
            {
                return false;
            }

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $this->values[$k] ?? null;
            }

            public function set($k, $value)
            {
                $this->values[$k] = $value;

                return true;
            }
        };
    }

    /** @param list<int> $results */
    private function modx(array $results): modX
    {
        return new class ($results) extends modX {
            /** @var list<array<string, mixed>> */
            public array $queries = [];

            /** @param list<int> $results */
            public function __construct(private array $results)
            {
                parent::__construct();
            }

            public function newQuery($className, $criteria = null)
            {
                $this->queries[] = ['class' => $className, 'where' => $criteria ?? []];
                $queryKey = array_key_last($this->queries);

                return new class ($this, $queryKey, $this->results) {
                    public object $stmt;

                    public function __construct(
                        private object $modx,
                        private int $queryKey,
                        private array $results,
                    ) {
                        $this->stmt = new class ($results) {
                            public function __construct(private array $results)
                            {
                            }

                            public function execute()
                            {
                                return true;
                            }

                            public function fetchAll($mode = null)
                            {
                                return $this->results;
                            }
                        };
                    }

                    public function command($command)
                    {
                    }

                    public function where(array $conditions)
                    {
                        $this->modx->queries[$this->queryKey]['where'] = array_merge(
                            $this->modx->queries[$this->queryKey]['where'],
                            $conditions
                        );
                    }

                    public function set(array $fields)
                    {
                        $this->modx->queries[$this->queryKey]['set'] = $fields;
                    }

                    public function sortby($field, $direction = 'ASC')
                    {
                    }

                    public function select($fields)
                    {
                    }

                    public function prepare()
                    {
                        return true;
                    }

                };
            }
        };
    }
}
