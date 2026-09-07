<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Services\ComboConfigManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression for #618: buildLabel must not corrupt Cyrillic UTF-8 when collapsing whitespace.
 */
final class ComboConfigManagerBuildLabelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }

        if (!defined('MODX_CORE_PATH')) {
            define('MODX_CORE_PATH', dirname(__DIR__, 3) . '/');
        }
    }

    public function testCyrillicErLabelSurvivesWhitespaceCollapse(): void
    {
        $label = $this->invokeBuildLabel(
            ['first_name' => 'Руслан', 'last_name' => 'Иванов'],
            '{first_name} {last_name}',
            'name'
        );

        self::assertSame('Руслан Иванов', $label);
        self::assertTrue(mb_check_encoding($label, 'UTF-8'));
        self::assertNotFalse(json_encode(['label' => $label], JSON_THROW_ON_ERROR));
    }

    public function testUnicodeNbspIsCollapsedToSingleSpace(): void
    {
        $nbsp = "\u{00A0}";
        $label = $this->invokeBuildLabel(
            ['first_name' => 'Анна', 'last_name' => 'Петрова'],
            '{first_name}' . $nbsp . $nbsp . '{last_name}',
            'name'
        );

        self::assertSame('Анна Петрова', $label);
        self::assertTrue(mb_check_encoding($label, 'UTF-8'));
        self::assertNotFalse(json_encode(['label' => $label], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function invokeBuildLabel(array $fields, ?string $template, string $singleField): string
    {
        $item = new class ($fields) {
            /** @param array<string, mixed> $fields */
            public function __construct(private array $fields)
            {
            }

            public function get(string $key): mixed
            {
                return $this->fields[$key] ?? null;
            }
        };

        $manager = new ComboConfigManager(new modX());
        $method = new ReflectionMethod(ComboConfigManager::class, 'buildLabel');
        $method->setAccessible(true);

        return (string) $method->invoke($manager, $item, $template, $singleField);
    }
}
