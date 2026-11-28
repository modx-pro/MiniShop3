<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Snippets;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #862: a product option with value 0 must be rendered, not dropped by empty().
 */
final class ProductOptionsZeroValueTest extends TestCase
{
    public function testZeroOptionValueIsKeptWhileBlankIsDropped(): void
    {
        self::assertSame(['material'], $this->runSnippet());
    }

    /** @return list<string> */
    private function runSnippet(): array
    {
        $xpdo = new class extends \xPDO\xPDO {
            public object $services;

            public function __construct()
            {
                $this->services = new class {
                    public function has(string $key): bool
                    {
                        return false;
                    }

                    public function get(string $key): object
                    {
                        return new \stdClass();
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
                return match ($k) {
                    'id' => 1,
                    'material' => 0,
                    'length' => '',
                    default => null,
                };
            }

            public function &getOne($alias, $criteria = null, $cacheFlag = true)
            {
                $this->fakeData = new class {
                    /** @return list<string> */
                    public function getOptionKeys(): array
                    {
                        return ['material', 'length'];
                    }
                };

                return $this->fakeData;
            }

            /** @return array<string, string> */
            public function loadOptions(): array
            {
                return [
                    'material.group_name' => 'group',
                    'length.group_name' => 'group',
                ];
            }
        };
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
        $modx = new class ($services, $product) extends modX {
            public msProduct $resource;

            public function __construct(object $services, msProduct $product)
            {
                parent::__construct();
                $this->services = $services;
                $this->resource = $product;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return $this->resource;
            }
        };

        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_product_options.php';
        $result = (static function (string $path, modX $modx): mixed {
            $scriptProperties = ['return' => 'data'];

            return include $path;
        })($path, $modx);

        self::assertIsArray($result);

        return array_keys($result);
    }
}
