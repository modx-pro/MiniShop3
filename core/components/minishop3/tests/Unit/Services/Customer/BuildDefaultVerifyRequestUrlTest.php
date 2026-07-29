<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\EmailVerificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BuildDefaultVerifyRequestUrlTest extends TestCase
{
    private const API_BASE = 'https://example.org/assets/components/minishop3/api.php';
    private const TOKEN = 'tok&x=1';
    private const ROUTE = '/api/v1/customer/email/verify';

    #[DataProvider('htmlFlagCases')]
    public function testBuildsVerifyUrlWithHtmlFlag(bool $html): void
    {
        $expected = self::API_BASE
            . '?route=' . rawurlencode(self::ROUTE)
            . '&token=' . rawurlencode(self::TOKEN)
            . ($html ? '&html=1' : '');

        self::assertSame(
            $expected,
            EmailVerificationService::buildDefaultVerifyRequestUrl(self::API_BASE, self::TOKEN, $html)
        );
    }

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function htmlFlagCases(): iterable
    {
        yield 'with html' => [true];
        yield 'without html' => [false];
    }

    public function testUsesAmpersandWhenBaseUrlAlreadyHasQuery(): void
    {
        $apiWithQuery = 'https://example.org/api.php?debug=0';
        $actual = EmailVerificationService::buildDefaultVerifyRequestUrl($apiWithQuery, 'abc', true);

        self::assertStringStartsWith($apiWithQuery . '&', $actual);
    }
}
