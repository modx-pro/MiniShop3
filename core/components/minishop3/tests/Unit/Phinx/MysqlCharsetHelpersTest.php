<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Phinx;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/phinx_mysql_charset.php';

final class MysqlCharsetHelpersTest extends TestCase
{
    #[DataProvider('dsnCharsetCases')]
    public function testExtractDsnCharset(?string $expected, ?string $dsn): void
    {
        self::assertSame($expected, ms3PhinxExtractDsnCharset($dsn));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function dsnCharsetCases(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty' => [null, ''];
        yield 'no charset' => [null, 'mysql:host=localhost;dbname=ms3'];
        yield 'charset in dsn' => ['utf8', 'mysql:host=localhost;dbname=ms3;charset=utf8'];
        yield 'charset first' => ['utf8mb4', 'mysql:charset=utf8mb4;host=localhost'];
    }

    #[DataProvider('normalizeCases')]
    public function testNormalizeMysqlCharset(string $expected, ?string $charset, bool $preferUtf8mb4): void
    {
        self::assertSame($expected, ms3PhinxNormalizeMysqlCharset($charset, $preferUtf8mb4));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: bool}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'UTF-8 prefer mb4' => ['utf8mb4', 'UTF-8', true];
        yield 'UTF-8 no prefer' => ['utf8', 'UTF-8', false];
        yield 'utf8mb4' => ['utf8mb4', 'utf8mb4', false];
        yield 'empty prefer mb4' => ['utf8mb4', '', true];
    }

    #[DataProvider('collationCases')]
    public function testDefaultMysqlCollation(string $expected, string $charset): void
    {
        self::assertSame($expected, ms3PhinxDefaultMysqlCollation($charset));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function collationCases(): iterable
    {
        yield 'utf8' => ['utf8_general_ci', 'utf8'];
        yield 'utf8mb4' => ['utf8mb4_unicode_ci', 'utf8mb4'];
        yield 'other' => ['latin1_general_ci', 'latin1'];
    }
}
