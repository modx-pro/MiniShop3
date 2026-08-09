<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Regression;

use MiniShop3\Controllers\Customer\Customer;
use MiniShop3\Services\Order\OrderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #357: deprecated pass-through wrappers must stay removed from core.
 *
 * The original OrderService::handleOrderSave() / removeOrder() and
 * Customer::getId() wrappers were deleted in favor of calling msOrder::save() /
 * msOrder::remove() and Customer::getOrCreate() directly. These assertions
 * guard against accidental reintroduction via reflection only — they do not
 * scan the source tree, which keeps the test fast and free of false positives
 * from comments, lexicon strings, or unrelated modules.
 */
final class DeprecatedPassThroughRemovedTest extends TestCase
{
    /**
     * @return iterable<string, array{0: class-string, 1: string}>
     */
    public static function removedPassThroughsProvider(): iterable
    {
        yield 'OrderService::handleOrderSave' => [OrderService::class, 'handleOrderSave'];
        yield 'OrderService::removeOrder' => [OrderService::class, 'removeOrder'];
        yield 'Customer::getId' => [Customer::class, 'getId'];
    }

    #[DataProvider('removedPassThroughsProvider')]
    public function testDeprecatedPassThroughIsRemoved(string $class, string $method): void
    {
        self::assertFalse(
            method_exists($class, $method),
            sprintf('%s::%s() must remain removed (deprecated pass-through).', $class, $method)
        );

        $reflection = new \ReflectionClass($class);
        self::assertFalse(
            $reflection->hasMethod($method),
            sprintf('%s::%s() must not be redeclared (checked via reflection).', $class, $method)
        );
    }
}
