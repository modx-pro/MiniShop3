<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Phinx;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/phinx_vendor.php';

final class VendorIsolationTest extends TestCase
{
    #[DataProvider('normalizePathCases')]
    public function testNormalizePath(string $expected, string $input): void
    {
        self::assertSame($expected, ms3PhinxNormalizePath($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizePathCases(): iterable
    {
        yield 'unix' => [
            '/var/www/core/components/minishop3/vendor/robmorgan/phinx',
            '/var/www/core/components/minishop3/vendor/robmorgan/phinx/',
        ];
        yield 'windows backslash' => [
            'D:/laragon/www/ms3/core/components/minishop3/vendor/robmorgan/phinx',
            'D:\\laragon\\www\\ms3\\core\\components\\minishop3\\vendor\\robmorgan\\phinx\\',
        ];
        yield 'mixed slashes' => [
            'D:/laragon/www/ms3/core/components/minishop3',
            'D:\\laragon/www/ms3\\core/components/minishop3',
        ];
    }

    #[DataProvider('pathIsUnderCases')]
    public function testPathIsUnder(bool $expected, string $file, string $root): void
    {
        self::assertSame($expected, ms3PhinxPathIsUnder($file, $root));
    }

    /**
     * @return iterable<string, array{0: bool, 1: string, 2: string}>
     */
    public static function pathIsUnderCases(): iterable
    {
        $root = '/site/core/components/minishop3/vendor/robmorgan/phinx';
        yield 'owned file' => [
            true,
            $root . '/src/Phinx/Migration/Manager/Environment.php',
            $root,
        ];
        yield 'foreign msearch' => [
            false,
            '/site/core/components/msearch/vendor/robmorgan/phinx/src/Phinx/Migration/Manager/Environment.php',
            $root,
        ];
        yield 'windows foreign' => [
            false,
            'D:\\laragon\\www\\ms3\\core\\components\\msearch\\vendor\\robmorgan\\phinx\\src\\Phinx\\Config\\Config.php',
            'D:\\laragon\\www\\ms3\\core\\components\\minishop3\\vendor\\robmorgan\\phinx',
        ];
        yield 'windows owned' => [
            true,
            'D:\\laragon\\www\\ms3\\core\\components\\minishop3\\vendor\\robmorgan\\phinx\\src\\Phinx\\Config\\Config.php',
            'D:\\laragon\\www\\ms3\\core\\components\\minishop3\\vendor\\robmorgan\\phinx',
        ];
    }

    public function testExpectedPhinxRoot(): void
    {
        self::assertSame(
            '/core/components/minishop3/vendor/robmorgan/phinx',
            ms3PhinxExpectedPhinxRoot('/core/components/minishop3/')
        );
    }
}
