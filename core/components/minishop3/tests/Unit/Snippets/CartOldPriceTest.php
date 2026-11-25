<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Snippets;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #861: catalog price must survive the merge into a cart line so old_price
 * can be shown; a deleted product must still render as a usable cart line.
 */
final class CartOldPriceTest extends TestCase
{
    public function testCatalogPriceBecomesOldPriceWhenLinePriceIsLower(): void
    {
        [$data, $calls] = $this->runSnippet(
            [$this->line(5, 800)],
            [[5, $this->catalogRow(5, '1000.00', '0.00')]]
        );

        self::assertCount(1, $data['products']);
        self::assertSame(1000.0, $data['products'][0]['old_price']);
        self::assertSame(800.0, $data['products'][0]['price']);
        self::assertSame(200.0, $data['products'][0]['discount_price']);
        self::assertNotEmpty($data['products'][0]['old_price_formatted']);
        self::assertSame('loadOptions', $calls[0][0] ?? null);
        self::assertSame(5, $calls[0][1][1] ?? null);
    }

    public function testLineOldPriceFromPropertiesWins(): void
    {
        $line = $this->line(5, 800);
        $line['properties']['old_price'] = 900;

        [$data] = $this->runSnippet(
            [$line],
            [[5, $this->catalogRow(5, '1000.00', '0.00')]]
        );

        self::assertSame(900.0, $data['products'][0]['old_price']);
        self::assertSame(100.0, $data['products'][0]['discount_price']);
    }

    public function testCatalogOldPriceIsKept(): void
    {
        [$data] = $this->runSnippet(
            [$this->line(5, 800)],
            [[5, $this->catalogRow(5, '1000.00', '1500.00')]]
        );

        self::assertSame(1500.0, $data['products'][0]['old_price']);
    }

    public function testNullCatalogOldPriceStillUsesCatalogPrice(): void
    {
        [$data] = $this->runSnippet(
            [$this->line(5, 800)],
            [[5, $this->catalogRow(5, '1000.00', null)]]
        );

        self::assertSame(1000.0, $data['products'][0]['old_price']);
    }

    public function testDeletedProductStillRendersUsableCartLine(): void
    {
        $line = $this->line(99, 800);
        $line['properties']['old_price'] = 1000;
        [$data, $calls] = $this->runSnippet([$line], []);

        self::assertCount(1, $data['products']);
        $product = $data['products'][0];
        self::assertSame('Deleted product', $product['name']);
        self::assertSame(99, $product['product_id']);
        self::assertSame('key-99', $product['product_key']);
        self::assertSame(800.0, $product['cost']);
        self::assertSame(1000.0, $product['old_price']);
        self::assertSame(200.0, $product['discount_price']);
        self::assertSame(1, $data['total']['count']);
        self::assertSame([], $calls, 'deleted products have no catalog options to load');
    }

    /** @return array<string, mixed> */
    private function line(int $productId, float $price): array
    {
        return [
            'id' => $productId + 50,
            'product_id' => $productId,
            'product_key' => 'key-' . $productId,
            'name' => $productId === 99 ? 'Deleted product' : 'Product',
            'price' => $price,
            'weight' => 1,
            'count' => 1,
            'cost' => $price,
            'properties' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function catalogRow(int $id, string $price, ?string $oldPrice): array
    {
        return [
            'id' => $id,
            'pagetitle' => 'Product',
            'price' => $price,
            'old_price' => $oldPrice,
        ];
    }

    /**
     * @param list<array<string, mixed>> $cart
     * @param list<array{0: int, 1: array<string, mixed>}> $rows
     * @return array{0: array<string, mixed>, 1: list<mixed>}
     */
    private function runSnippet(array $cart, array $rows): array
    {
        $cartData = [];
        foreach ($cart as $entry) {
            $cartData[$entry['product_key']] = $entry;
        }
        $status = [
            'total_count' => array_sum(array_column($cart, 'count')),
            'total_cost' => 800.0,
            'total_weight' => 1.0,
            'total_discount' => 0.0,
            'total_positions' => count($cart),
        ];
        $rowMap = [];
        foreach ($rows as [$id, $row]) {
            $rowMap[$id] = $row;
        }
        $calls = new class {
            /** @var list<mixed> */
            public array $items = [];
        };

        $modx = $this->modx($cartData, $status, $rowMap, $calls);
        $path = dirname(__DIR__, 3) . '/elements/snippets/ms3_cart.php';
        $result = (static function (string $path, modX $modx) {
            $scriptProperties = ['return' => 'data'];

            return include $path;
        })($path, $modx);

        self::assertIsArray($result);

        return [$result, $calls->items];
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $status
     * @param array<int, array<string, mixed>> $rows
     * @param object{items: list<mixed>} $calls
     */
    private function modx(array $cart, array $status, array $rows, object $calls): modX
    {
        $tokenService = new class {
            public function resolveOrCreateToken(): string
            {
                return 'token';
            }
        };
        $pdoFetch = new class ($rows) {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private array $rows)
            {
            }

            public function addTime($label): void
            {
            }

            public function setConfig(array $config, $merge = false): void
            {
            }

            /** @return list<array<string, mixed>> */
            public function run(): array
            {
                return array_values($this->rows);
            }

            public function getChunk($tpl, array $data): string
            {
                return '';
            }
        };
        $ms3 = new class ($cart, $status) extends MiniShop3 {
            public object $cart;

            /** @param array<string, mixed> $cart @param array<string, mixed> $status */
            public function __construct(array $cart, array $status)
            {
                // Skip DI construction; the snippet only needs these facades.
                $this->cart = new class ($cart, $status) {
                    /** @param array<string, mixed> $cart @param array<string, mixed> $status */
                    public function __construct(private array $cart, private array $status)
                    {
                    }

                    public function initialize(string $context, string $token): void
                    {
                    }

                    /** @return array{success: bool, data: array<string, mixed>} */
                    public function get(): array
                    {
                        return ['success' => true, 'data' => ['cart' => $this->cart, 'status' => $this->status]];
                    }
                };
                $this->format = new class {
                    public function price($value, $format = false): string
                    {
                        return number_format((float) $value, 2);
                    }

                    public function weightWithUnit($value): string
                    {
                        return (string) $value;
                    }
                };
            }

            public function initialize($ctx = 'web', $scriptProperties = [])
            {
            }

            public function registerSnippet($scriptProperties, string $snippetName = 'msCart')
            {
            }
        };
        $services = new class ($ms3, $tokenService, $pdoFetch) {
            public function __construct(
                private object $ms3,
                private object $tokenService,
                private object $pdoFetch,
            ) {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3';
            }

            public function get(string $key): object
            {
                return match ($key) {
                    'ms3' => $this->ms3,
                    'ms3_token_service' => $this->tokenService,
                    default => $this->pdoFetch,
                };
            }
        };

        return new class ($services, $pdoFetch, $calls) extends modX {
            /** @param object{items: list<mixed>} $calls */
            public function __construct(object $services, private object $pdoFetch, private object $calls)
            {
                parent::__construct();
                $this->services = $services;
                $this->lexicon = new class {
                    public function load(...$topics): void
                    {
                    }
                };
                $this->context = (object) ['key' => 'web'];
                $this->optionOverrides = ['return' => 'data'];
            }

            public function getSelectColumns($class, $alias = '', $prefix = '', $exclude = [], $include = false)
            {
                return 'col';
            }

            public function call($class, $method, array $args = [])
            {
                $this->calls->items[] = [$method, $args];

                return [];
            }
        };
    }
}
