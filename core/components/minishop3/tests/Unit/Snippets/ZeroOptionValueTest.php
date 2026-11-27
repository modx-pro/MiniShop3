<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Snippets;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #859: 0 and "0" are valid option values; only blank values hide an option.
 */
final class ZeroOptionValueTest extends TestCase
{
    public function testMsOptionsKeepsZeroAndDropsBlank(): void
    {
        $data = $this->runMsOptions([
            'floor' => 0,
            'string_zero' => '0',
            'one' => 1,
            'blank' => '   ',
            'missing' => null,
            'array_zero' => ['0', 'extra'],
            'boolean_false' => false,
            'empty_array' => [],
            'assoc_array' => [1 => 'x'],
        ]);

        self::assertSame(
            ['floor', 'string_zero', 'one', 'array_zero', 'boolean_false', 'empty_array', 'assoc_array'],
            array_keys($data)
        );
        self::assertSame([0], $data['floor']);
        self::assertSame(['0', 'extra'], $data['array_zero']);
    }

    public function testMsProductOptionsKeepsZeroAndDropsBlank(): void
    {
        $data = $this->runMsProductOptions([
            'floor' => 0,
            'string_zero' => '0',
            'one' => 1,
            'blank' => '   ',
            'missing' => null,
            'boolean_false' => false,
            'empty_array' => [],
            'assoc_array' => [1 => 'x'],
        ]);

        self::assertSame(
            ['floor', 'string_zero', 'one', 'boolean_false', 'empty_array', 'assoc_array'],
            array_keys($data)
        );
        self::assertSame(0, $data['floor']['value']);
        self::assertSame('0', $data['string_zero']['value']);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function runMsOptions(array $values): array
    {
        $product = $this->product($values);
        $modx = $this->modx($product, 'msOptions');
        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_options.php';

        $output = (static function (string $path, modX $modx, msProduct $product) {
            $scriptProperties = ['sortOptionValues' => ''];
            $options = implode(',', [
                'floor', 'string_zero', 'one', 'blank', 'missing',
                'array_zero', 'boolean_false', 'empty_array', 'assoc_array',
            ]);

            return include $path;
        })($path, $modx, $product);

        self::assertIsString($output);
        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function runMsProductOptions(array $values): array
    {
        $product = $this->product($values);
        $modx = $this->modx($product, 'msProductOptions');
        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_product_options.php';

        $result = (static function (string $path, modX $modx) {
            $scriptProperties = ['return' => 'data'];

            return include $path;
        })($path, $modx);

        self::assertIsArray($result);

        return $result;
    }

    /** @param array<string, mixed> $values */
    private function product(array $values): msProduct
    {
        return new class ($values) extends msProduct {
            public int $id = 1;

            protected array $_fieldMeta = [];

            /** @param array<string, mixed> $values */
            public function __construct(private array $values)
            {
            }

            public function get($k, $format = null, $formatTemplate = null)
            {
                return $k === 'id' ? 1 : ($this->values[$k] ?? null);
            }

            public function &getOne($alias, $criteria = null, $cacheFlag = true)
            {
                static $data = null;
                $keys = array_keys($this->values);
                $data = new class ($keys) {
                    /** @param list<string> $keys */
                    public function __construct(private array $keys)
                    {
                    }

                    /** @return list<string> */
                    public function getOptionKeys(): array
                    {
                        return $this->keys;
                    }
                };

                return $data;
            }

            /** @return array<string, mixed> */
            public function loadOptions(): array
            {
                $loaded = [];
                foreach ($this->values as $key => $value) {
                    $loaded[$key . '.group_name'] = 'group';
                }

                return $loaded;
            }
        };
    }

    private function modx(msProduct $product, string $snippet): modX
    {
        $ms3 = new class ($snippet) {
            public object $options;

            public function __construct(string $snippet)
            {
                $this->options = new class ($snippet) {
                    public function __construct(private string $snippet)
                    {
                    }

                    /** @return array<string, mixed> */
                    public function sortOptionValues(array $options, $raw = ''): array
                    {
                        return $this->snippet === 'msOptions' ? $options : $options;
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
                if ($key === 'ms3') {
                    return $this->ms3;
                }

                return new class {
                    public function getChunk($tpl, array $data): string
                    {
                        return json_encode($data['options'] ?? [], JSON_THROW_ON_ERROR);
                    }
                };
            }
        };

        return new class ($services, $product) extends modX {
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

            public function call($class, $method, array $args = [])
            {
                return [];
            }
        };
    }
}
