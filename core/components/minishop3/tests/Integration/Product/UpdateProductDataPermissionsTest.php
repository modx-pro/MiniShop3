<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Product\ProductDataService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use xPDO\xPDO;

/**
 * Level-2: ProductDataService::updateProductData permission / whitelist / validation branches.
 */
final class UpdateProductDataPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testForbiddenWithoutSaveDocumentPermission(): void
    {
        $service = $this->makeService(hasSaveDocument: false);
        $result = $service->updateProductData(1, ['price' => 10]);

        self::assertFalse($result['ok']);
        self::assertSame(ProductDataService::ERROR_FORBIDDEN, $result['code']);
    }

    public function testNotFoundWhenProductMissing(): void
    {
        $service = $this->makeService(product: null);
        $result = $service->updateProductData(99, ['price' => 10]);

        self::assertFalse($result['ok']);
        self::assertSame(ProductDataService::ERROR_NOT_FOUND, $result['code']);
    }

    public function testForbiddenWhenResourcePolicyDeniesSave(): void
    {
        $product = new UpdatableProduct(allowSave: false, data: new UpdatableProductData($this->xpdo()));
        $service = $this->makeService(product: $product);
        $result = $service->updateProductData(1, ['price' => 10]);

        self::assertFalse($result['ok']);
        self::assertSame(ProductDataService::ERROR_FORBIDDEN, $result['code']);
        self::assertSame('Save permission denied', $result['message']);
    }

    public function testValidationRejectsNegativePrice(): void
    {
        $data = new UpdatableProductData($this->xpdo(), ['price' => 5]);
        $product = new UpdatableProduct(allowSave: true, data: $data);
        $service = $this->makeService(product: $product);
        $result = $service->updateProductData(1, ['price' => -1]);

        self::assertFalse($result['ok']);
        self::assertSame(ProductDataService::ERROR_VALIDATION, $result['code']);
        self::assertFalse($data->saved);
    }

    public function testDropsUnknownKeysAndSavesAllowlistedFields(): void
    {
        $data = new UpdatableProductData($this->xpdo(), ['price' => 5, 'article' => 'OLD']);
        $product = new UpdatableProduct(allowSave: true, data: $data);
        $service = $this->makeService(product: $product);

        $result = $service->updateProductData(1, [
            'price' => 12.5,
            'article' => 'NEW',
            'hack' => 'drop-me',
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(12.5, $result['data']['price']);
        self::assertSame('NEW', $result['data']['article']);
        self::assertArrayNotHasKey('hack', $result['data']);
        self::assertTrue($data->saved);
    }

    private function makeService(bool $hasSaveDocument = true, ?UpdatableProduct $product = null): TestableProductDataService
    {
        $modx = new class ($hasSaveDocument, $product) extends modX {

            public function __construct(
                private bool $hasSaveDocument,
                private ?UpdatableProduct $product,
            ) {
                parent::__construct();
                $this->services = new class {
                    public function has(string $key): bool
                    {
                        return false;
                    }

                    public function get(string $key): mixed
                    {
                        return null;
                    }
                };
            }

            public function hasPermission(string $permission): bool
            {
                return $permission !== 'save_document' || $this->hasSaveDocument;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msProduct::class) {
                    return $this->product;
                }

                return null;
            }
        };

        $service = new TestableProductDataService($modx);
        $service->repeaterFields = [];
        $service->keyValueFields = [];

        return $service;
    }

    private function xpdo(): xPDO
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

final class TestableProductDataService extends ProductDataService
{
    /** @var array<string, array> */
    public array $repeaterFields = [];

    /** @var array<string, array> */
    public array $keyValueFields = [];

    protected function getProductRepeaterFields(): array
    {
        return $this->repeaterFields;
    }

    protected function getProductKeyValueFields(): array
    {
        return $this->keyValueFields;
    }
}

final class UpdatableProduct extends msProduct
{
    /** @var array<string, mixed> */
    public $_fieldMeta = [];

    /** @var list<string> */
    protected $dataRelated = [];

    public function __construct(
        private bool $allowSave,
        private UpdatableProductData $data,
    ) {
    }

    public function checkPolicy($criteria, $targets = null, $user = null): bool
    {
        return $this->allowSave;
    }

    public function loadData()
    {
        return $this->data;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $k === 'id' ? 1 : ($k === 'published' ? ($this->data->fields['published'] ?? 0) : null);
    }
}

final class UpdatableProductData extends msProductData
{
    /** @var array<string, mixed> */
    public array $fields;

    public bool $saved = false;

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

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false, $rawValues = false, $adhoc = false)
    {
        foreach ($fields as $key => $value) {
            $this->fields[$key] = $value;
        }

        return true;
    }

    public function save($cacheFlag = null)
    {
        $this->saved = true;

        return true;
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        return $this->fields;
    }
}
