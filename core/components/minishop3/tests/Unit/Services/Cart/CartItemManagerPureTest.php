<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Cart\CartItemManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Level-2 starter: pure CartItemManager helpers (no draft/DB).
 */
final class CartItemManagerPureTest extends TestCase
{
    private CartItemManager $manager;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }

        $modx = new class extends modX {
            public function getOption($key, $options = null, $default = null, $skipEmpty = false)
            {
                return match ((string) $key) {
                    'ms3_cart_max_count' => 5,
                    'ms3_cart_product_key_fields' => 'id,options',
                    default => $default,
                };
            }
        };

        $ms3 = $this->createStub(MiniShop3::class);
        $this->manager = new CartItemManager($modx, $ms3);
    }

    #[DataProvider('countCases')]
    public function testValidateCount(bool $expected, int $count): void
    {
        self::assertSame($expected, $this->manager->validateCount($count));
    }

    /**
     * @return iterable<string, array{0: bool, 1: int}>
     */
    public static function countCases(): iterable
    {
        yield 'one' => [true, 1];
        yield 'max' => [true, 5];
        yield 'zero' => [false, 0];
        yield 'negative' => [false, -1];
        yield 'above max' => [false, 6];
    }

    public function testNormalizeOptions(): void
    {
self::assertSame([], CartItemManager::normalizeOptions('[]'));
        self::assertSame([], CartItemManager::normalizeOptions('{}'));
        self::assertSame(['color' => 'red'], CartItemManager::normalizeOptions('{"color":"red"}'));
        self::assertSame(['a' => 1], CartItemManager::normalizeOptions(['a' => 1]));
        self::assertSame([], CartItemManager::normalizeOptions('{bad'));
        self::assertSame([], CartItemManager::normalizeOptions(null));
    }

    public function testGenerateProductKeyDiffersByOptions(): void
    {
        $base = ['id' => 42];
        $keyA = $this->manager->generateProductKey($base, ['color' => 'red']);
        $keyB = $this->manager->generateProductKey($base, ['color' => 'blue']);
        $keySame = $this->manager->generateProductKey($base, ['color' => 'red']);

        self::assertNotSame($keyA, $keyB);
        self::assertSame($keyA, $keySame);
        self::assertStringStartsWith('ms', $keyA);
    }

    public function testCalculateStatusAggregatesWeightAndDiscount(): void
    {
        $status = $this->manager->calculateStatus([
            [
                'count' => 2,
                'cost' => 20.0,
                'weight' => 1.5,
                'properties' => ['discount_price' => 1.0],
            ],
            [
                'count' => 1,
                'cost' => 5.0,
                'weight' => 0.5,
                'properties' => [],
            ],
        ]);

        self::assertSame(2, $status['total_positions']);
        self::assertSame(3, $status['total_count']);
        self::assertSame(25.0, $status['total_cost']);
        self::assertSame(3.5, $status['total_weight']);
        self::assertSame(2.0, $status['total_discount']);
    }
}
