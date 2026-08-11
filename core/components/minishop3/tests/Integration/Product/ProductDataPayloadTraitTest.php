<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Processors\Product\ProductDataPayloadTrait;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use xPDO\xPDO;

/**
 * Level-2: ProductDataPayloadTrait whitelist + nested Data capture (null/malformed).
 */
final class ProductDataPayloadTraitTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testCaptureIgnoresMalformedJsonData(): void
    {
        $harness = new PayloadHarness(new modX(), ['Data' => '{not-json']);
        $harness->captureProductDataPayloadPublic();

        self::assertNull($harness->payload());
        self::assertArrayNotHasKey('Data', $harness->getProperties());
    }

    public function testApplyWhitelistsFlatAndNestedFieldsAndKeepsNull(): void
    {
        $productData = new RecordingProductData($this->xpdoWithoutMs3());
        $product = new LightweightProduct($productData);

        $harness = new PayloadHarness(new modX(), [
            'price' => 10.5,
            'hack' => 'drop-me',
            'Data' => [
                'article' => 'A-1',
                'weight' => null,
                'unknown' => 'nope',
            ],
        ], $product);

        $harness->captureProductDataPayloadPublic();
        self::assertTrue($harness->applyProductDataPayloadPublic());

        self::assertSame(
            [
                'price' => 10.5,
                'article' => 'A-1',
                'weight' => null,
            ],
            $productData->assigned
        );
        self::assertFalse($productData->saved);
    }

    private function xpdoWithoutMs3(): xPDO
    {
        return new class extends xPDO {
            /** @var object|null */
            public $services;

            public function __construct()
            {
                $this->services = new class {
                    public function has(string $key): bool
                    {
                        return false;
                    }
                };
            }
        };
    }
}

final class LightweightProduct extends msProduct
{
    /** @var array<string, mixed> */
    public $_fieldMeta = [];

    /** @var list<string> */
    protected $dataRelated = [];

    public function __construct(private RecordingProductData $data)
    {
        // Skip real msProduct/modResource construction (needs full xPDO map).
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $k === 'id' ? 15 : null;
    }

    public function getDataFieldsNames(): array
    {
        return ['id', 'price', 'article', 'weight'];
    }

    public function loadData()
    {
        return $this->data;
    }
}

final class PayloadHarness
{
    use ProductDataPayloadTrait;

    /** @var array<string, mixed> */
    private array $properties;

    public modX $modx;
    public object $object;

    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(modX $modx, array $properties, ?object $object = null)
    {
        $this->properties = $properties;
        $this->object = $object ?? new LightweightProduct(
            new RecordingProductData(new class extends xPDO {
                /** @var object|null */
                public $services;

                public function __construct()
                {
                    $this->services = new class {
                        public function has(string $key): bool
                        {
                            return false;
                        }
                    };
                }
            })
        );

        $this->modx = new class extends modX {

            public function __construct()
            {
                parent::__construct();
                $this->services = new class {
                    public function has(string $key): bool
                    {
                        return false;
                    }
                };
            }

            public function log($level, $message): void
            {
            }
        };
    }

    public function getProperty($key, $default = null)
    {
        return array_key_exists($key, $this->properties) ? $this->properties[$key] : $default;
    }

    public function unsetProperty($key): void
    {
        unset($this->properties[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function captureProductDataPayloadPublic(): void
    {
        $this->captureProductDataPayload();
    }

    public function applyProductDataPayloadPublic(): bool
    {
        $this->applyProductDataPayload();

        return true;
    }

    public function payload(): ?array
    {
        return $this->ms3ProductDataPayload;
    }
}

final class RecordingProductData extends msProductData
{
    /** @var array<string, mixed> */
    public array $assigned = [];

    public bool $saved = false;

    public function __construct(xPDO $xpdo)
    {
        parent::__construct($xpdo);
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false, $rawValues = false, $adhoc = false)
    {
        foreach ($fields as $key => $value) {
            $this->assigned[$key] = $value;
        }

        return true;
    }

    public function set($key, $value)
    {
        $this->assigned[$key] = $value;

        return true;
    }

    public function save($cacheFlag = null)
    {
        $this->saved = true;

        return true;
    }
}
