<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Api;

use MiniShop3\Services\Api\WebApiModxBootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebApiModxBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION);
        parent::tearDown();
    }

    public function testNonOptionsDoesNotDefineSessionSuperglobal(): void
    {
        unset($_SESSION);

        WebApiModxBootstrap::prepareForInitialize('GET');

        self::assertFalse(isset($_SESSION));
    }

    #[DataProvider('optionsMethods')]
    public function testOptionsDefinesEmptySessionForExternalState(string $method): void
    {
        unset($_SESSION);

        WebApiModxBootstrap::prepareForInitialize($method);

        self::assertTrue(isset($_SESSION));
        self::assertSame([], $_SESSION);
    }

    public function testBootstrapSourceNeverPassesSessionEnabledOption(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 4) . '/src/Services/Api/WebApiModxBootstrap.php'
        );

        self::assertDoesNotMatchRegularExpression(
            "/['\"]session_enabled['\"]\\s*=>/",
            $src
        );
        self::assertStringNotContainsString('initializeOptions', $src);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function optionsMethods(): iterable
    {
        yield 'OPTIONS' => ['OPTIONS'];
        yield 'options lower' => ['options'];
    }
}
