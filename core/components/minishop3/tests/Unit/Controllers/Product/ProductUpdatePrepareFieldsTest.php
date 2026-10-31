<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Product;

use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #884: prepareFields() отбрасывал 0 из JSON-полей color/size/tags.
 *
 * empty() считает 0 и '0' пустыми, поэтому значение не попадало в superboxselect
 * на вкладке свойств, и следующее сохранение карточки писало массив без него.
 */
final class ProductUpdatePrepareFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        if (!class_exists('msResourceUpdateController', false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ResourceUpdateControllerStub.php';
        }
        if (!class_exists('msProductUpdateManagerController', false)) {
            require_once dirname(__DIR__, 4) . '/controllers/product/update.class.php';
        }
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, array{value: mixed}> $expected
     */
    #[DataProvider('zeroValues')]
    public function testZeroValuesSurviveJsonFieldPreparation(string $field, array $values, array $expected): void
    {
        $controller = $this->makeController([$field => $values]);

        $controller->prepareFields();

        self::assertSame($expected, $controller->resourceArray[$field]);
    }

    /**
     * @return array<string, array{string, array<int, mixed>, array<int, array{value: mixed}>}>
     */
    public static function zeroValues(): array
    {
        return [
            'string zero in color' => ['color', ['0', 'red'], [['value' => '0'], ['value' => 'red']]],
            'integer zero in size' => ['size', [0, 'M'], [['value' => 0], ['value' => 'M']]],
            'string zero in tags' => ['tags', ['0'], [['value' => '0']]],
        ];
    }

    public function testEmptyStringAndNullAreDropped(): void
    {
        $controller = $this->makeController([
            'color' => ['', null, 'red', ''],
        ]);

        $controller->prepareFields();

        self::assertSame([['value' => 'red']], $controller->resourceArray['color']);
    }

    public function testNonJsonFieldsAreUntouched(): void
    {
        $controller = $this->makeController([
            'pagetitle' => ['Заголовок'],
            'color' => ['red'],
        ]);

        $controller->prepareFields();

        self::assertSame(['Заголовок'], $controller->resourceArray['pagetitle']);
    }

    public function testVendorFallsBackToEmptyString(): void
    {
        $controller = $this->makeController([]);

        $controller->prepareFields();

        self::assertSame('', $controller->resourceArray['vendor']);
    }

    /**
     * @param array<string, mixed> $resourceArray
     * @return \msProductUpdateManagerController
     */
    private function makeController(array $resourceArray): object
    {
        $modx = new class extends modX {
            public function getFieldMeta($className)
            {
                return [
                    'color' => [],
                    'size' => [],
                    'tags' => [],
                    'vendor' => [],
                ];
            }
        };

        $controller = new \msProductUpdateManagerController($modx);
        $controller->resourceArray = $resourceArray;

        return $controller;
    }
}
