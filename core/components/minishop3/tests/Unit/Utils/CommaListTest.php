<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\CommaList;
use PHPUnit\Framework\TestCase;

final class CommaListTest extends TestCase
{
    public function testLeadingCommaDoesNotLeaveGapsInKeys(): void
    {
        self::assertSame(['material', 'length'], CommaList::parse(',material,length'));
        self::assertSame(['material'], CommaList::parse(' ,material'));
    }

    public function testEmptyAndWhitespaceEntriesAreDropped(): void
    {
        self::assertSame(
            ['material', 'length'],
            CommaList::parse('material,,  length , ')
        );
        self::assertSame([], CommaList::parse(''));
        self::assertSame([], CommaList::parse(null));
        self::assertSame([], CommaList::parse(' , , '));
    }

    public function testZeroIsAValidValue(): void
    {
        self::assertSame(['0', 'material'], CommaList::parse('0,material'));
    }

    public function testArrayInputIsSupported(): void
    {
        self::assertSame(['material', 'length'], CommaList::parse(['material', '', ' length ']));
    }
}
