<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Snippets;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #842 behavioral regression: comma-separated snippet parameters are parsed
 * into a clean, reindexed list — a leading comma must not disable filtering.
 */
final class ProductOptionsOnlyOptionsTest extends TestCase
{
    public function testNormalListFiltersOptions(): void
    {
        self::assertSame(['material', 'length'], $this->runSnippet(['onlyOptions' => 'material,length']));
    }

    public function testLeadingCommaStillFiltersOptions(): void
    {
        self::assertSame(['material', 'length'], $this->runSnippet(['onlyOptions' => ',material,length']));
    }

    public function testSpaceBeforeCommaStillFiltersOptions(): void
    {
        self::assertSame(['material'], $this->runSnippet(['onlyOptions' => ' ,material']));
    }

    public function testEmptyListReturnsAllOptions(): void
    {
        self::assertSame(['material', 'length', 'other'], $this->runSnippet([]));
    }

    public function testIgnoreOptions(): void
    {
        self::assertSame(['material', 'other'], $this->runSnippet(['ignoreOptions' => ' length ']));
    }

    public function testIgnoreGroups(): void
    {
        self::assertSame(['other'], $this->runSnippet(['ignoreGroups' => ',a']));
    }

    public function testGroupsZeroKeepsOldNoFilterSemantics(): void
    {
        self::assertSame(['material', 'length', 'other'], $this->runSnippet(['groups' => '0']));
    }

    public function testGroupsFromSnippetPropertiesAreApplied(): void
    {
        self::assertSame(['other'], $this->runSnippet(['groups' => 'b']));
    }

    public function testSortOptionsOrdersByGivenList(): void
    {
        self::assertSame(
            ['other', 'material', 'length'],
            $this->runSnippet(['sortOptions' => 'other,material'])
        );
    }

    public function testSortGroupsOrdersByGivenList(): void
    {
        self::assertSame(
            ['other', 'material', 'length'],
            $this->runSnippet(['sortGroups' => 'b,a'])
        );
    }

    /** @param array<string, mixed> $props @return list<string> */
    private function runSnippet(array $props): array
    {
        $ms3 = new class {
            public object $options;

            public function __construct()
            {
                $this->options = new class {
                    /** @return array<string, mixed> */
                    public function sortOptionValues(array $options, $raw): array
                    {
                        return $options;
                    }
                };
            }
        };
        $xpdo = new class ($ms3) extends \xPDO\xPDO {
            public object $services;

            public function __construct(object $ms3)
            {
                $this->services = new class ($ms3) {
                    public function __construct(private object $ms3)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3';
                    }

                    public function get(string $key): object
                    {
                        return $this->ms3;
                    }
                };
            }

            public function getAggregates($class): array
            {
                return [];
            }

            public function getComposites($class): array
            {
                return [];
            }
        };

        $product = new class ($xpdo) extends msProduct {
            public int $id = 1;

            protected array $_fieldMeta = [];

            private mixed $fakeData = null;

            public function get($k, $format = null, $formatTemplate = null)
            {
                if ($k === 'id') {
                    return 1;
                }

                return in_array($k, ['material', 'length', 'other'], true) ? 'value-' . $k : null;
            }

            public function &getOne($alias, $criteria = null, $cacheFlag = true)
            {
                $this->fakeData = new class {
                    /** @return list<string> */
                    public function getOptionKeys(): array
                    {
                        return ['material', 'length', 'other'];
                    }
                };

                return $this->fakeData;
            }

            /** @return array<string, string> */
            public function loadOptions(): array
            {
                return [
                    'material.group_name' => 'a',
                    'length.group_name' => 'a',
                    'other.group_name' => 'b',
                ];
            }
        };
        $modx = $this->modx($product, $ms3, $props);

        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_product_options.php';
        $result = (static function (string $path, modX $modx, object $ms3, array $props) {
            $scriptProperties = array_merge(['return' => 'data'], $props);

            return include $path;
        })($path, $modx, $ms3, $props);

        self::assertIsArray($result);

        return array_keys($result);
    }

    /** @param array<string, mixed> $props */
    private function modx(msProduct $product, object $ms3, array $props): modX
    {
        $services = new class ($ms3) {
            public function __construct(private object $ms3)
            {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3';
            }

            public function get(string $key): object
            {
                return $this->ms3;
            }
        };

        return new class ($services, $product, $props) extends modX {
            public msProduct $resource;

            public function __construct(
                object $services,
                msProduct $product,
                array $props,
            ) {
                parent::__construct();
                $this->services = $services;
                $this->resource = $product;
                $this->optionOverrides = $props;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->resource;
            }
        };
    }
}
