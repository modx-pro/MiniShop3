<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFields\KeyValueFieldService;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MiniShop3\Services\Product\ProductDataService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use xPDO\xPDO;

/**
 * Empty optional date extras must become NULL before save (#623 / biz87 review).
 */
final class ProductDataPrepareObjectDateNullTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testEmptyDateScalarsBecomeNull(): void
    {
        $modx = new modX();
        $service = new DateNullTestableProductDataService($modx);
        $xpdo = new xPDO();
        $xpdo->services = new class {
            public function has(string $key): bool
            {
                return false;
            }
        };
        $productData = new DateNullProductData($xpdo, [
            'optional_date' => '',
            'zero_date' => '0000-00-00',
            'zero_datetime' => '0000-00-00 00:00:00',
            'kept_date' => '2026-04-20',
            'price' => '',
        ]);
        $productData->_fieldMeta = [
            'id' => ['phptype' => 'integer'],
            'optional_date' => ['phptype' => 'datetime'],
            'zero_date' => ['phptype' => 'date'],
            'zero_datetime' => ['phptype' => 'timestamp'],
            'kept_date' => ['phptype' => 'date'],
            'price' => ['phptype' => 'float'],
        ];

        $service->prepareObject($productData);

        self::assertNull($productData->fields['optional_date']);
        self::assertNull($productData->fields['zero_date']);
        self::assertNull($productData->fields['zero_datetime']);
        self::assertSame('2026-04-20', $productData->fields['kept_date']);
        self::assertSame(0.0, $productData->fields['price']);
    }
}

/**
 * @internal
 */
final class DateNullTestableProductDataService extends ProductDataService
{
    protected function getProductRepeaterFields(): array
    {
        return [];
    }

    protected function getProductKeyValueFields(): array
    {
        return [];
    }

    protected function getRepeaterFieldService(): RepeaterFieldService
    {
        return new RepeaterFieldService($this->modx);
    }

    protected function getKeyValueFieldService(): KeyValueFieldService
    {
        return new KeyValueFieldService($this->modx);
    }
}

/**
 * @internal
 */
final class DateNullProductData extends msProductData
{
    /** @var array<string, mixed> */
    public array $fields;

    /** @var array<string, array<string, mixed>> */
    public $_fieldMeta = [];

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(xPDO $xpdo, array $fields = [])
    {
        parent::__construct($xpdo);
        $this->fields = $fields;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($k, $v = null, $vType = '')
    {
        $this->fields[$k] = $v;

        return true;
    }

    public function getArraysValues()
    {
        return [];
    }

    public function isNew($checkDefaults = false)
    {
        return false;
    }
}
