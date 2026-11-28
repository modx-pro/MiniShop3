<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\ValuePresence;
use PHPUnit\Framework\TestCase;

final class ValuePresenceTest extends TestCase
{
    public function testZeroValuesArePresent(): void
    {
        self::assertFalse(ValuePresence::isBlank(0));
        self::assertFalse(ValuePresence::isBlank('0'));
        // false/[] were cleared by the old empty() checks; keep that contract.
        self::assertTrue(ValuePresence::isBlank(false));
        self::assertTrue(ValuePresence::isBlank([]));
    }

    public function testNullAndBlankStringsAreBlank(): void
    {
        self::assertTrue(ValuePresence::isBlank(null));
        self::assertTrue(ValuePresence::isBlank(''));
        self::assertTrue(ValuePresence::isBlank("  \t\n"));
    }

    public function testOtherValuesArePresent(): void
    {
        self::assertFalse(ValuePresence::isBlank('floor'));
        self::assertFalse(ValuePresence::isBlank([0]));
        self::assertFalse(ValuePresence::isBlank(1));
    }

    public function testMergeKeepsZeroAndClearsOnlyBlankStrings(): void
    {
        self::assertSame(
            ['floor' => 0, 'keep' => 'yes', 'room' => '0'],
            ValuePresence::merge(
                ['floor' => 1, 'keep' => 'yes', 'drop' => 'x'],
                ['floor' => 0, 'room' => '0', 'drop' => null, 'drop2' => '  ']
            )
        );
    }

    public function testMergeDoesNotMutateTheCurrentArray(): void
    {
        $current = ['floor' => 1];
        $merged = ValuePresence::merge($current, ['floor' => 0]);

        self::assertSame(['floor' => 1], $current);
        self::assertSame(['floor' => 0], $merged);
    }
}
