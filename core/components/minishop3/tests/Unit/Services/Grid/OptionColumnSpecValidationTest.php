<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Grid;

use MiniShop3\Services\Grid\OptionColumnSpec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptionColumnSpecValidationTest extends TestCase
{
    #[DataProvider('optionKeyCases')]
    public function testIsValidOptionKey(bool $expected, string $key): void
    {
        self::assertSame($expected, OptionColumnSpec::isValidOptionKey($key));
    }

    /**
     * @return iterable<string, array{0: bool, 1: string}>
     */
    public static function optionKeyCases(): iterable
    {
        yield 'simple' => [true, 'color'];
        yield 'underscores' => [true, 'option_key_1'];
        yield 'sql injection attempt' => [false, "a'; DROP TABLE--"];
        yield 'space' => [false, 'bad key'];
        yield 'empty' => [false, ''];
        yield 'dash' => [false, 'bad-key'];
    }

    #[DataProvider('fieldNameCases')]
    public function testIsValidFieldName(bool $expected, string $name): void
    {
        self::assertSame($expected, OptionColumnSpec::isValidFieldName($name));
    }

    /**
     * @return iterable<string, array{0: bool, 1: string}>
     */
    public static function fieldNameCases(): iterable
    {
        yield 'custom' => [true, 'opt_color'];
        yield 'reserved id' => [false, 'id'];
        yield 'reserved price case' => [false, 'Price'];
        yield 'injection' => [false, 'x`;--'];
        yield 'empty' => [false, ''];
    }
}
