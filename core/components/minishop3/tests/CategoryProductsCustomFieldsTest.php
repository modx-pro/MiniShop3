<?php

/**
 * Test for custom and extra fields in category products grid (#957).
 *
 * Verifies that:
 * 1. CategoryProductsListService includes msProductData fields in SELECT and formatProductRow.
 * 2. CategoryProductsListService::mapSortField maps custom data fields to Data.field.
 * 3. ProductDataService::updateProductData allows custom msProductData fields from schema and persists them.
 * 4. ProductDataService::updateProductData still drops unknown/arbitrary keys.
 *
 * Run: php tests/CategoryProductsCustomFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/XpdoStub.php';

if (!class_exists('MODX\\Revolution\\modResource')) {
    class_alias(\xPDO\Om\xPDOSimpleObject::class, 'MODX\\Revolution\\modResource');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MiniShop3\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Category\CategoryProductsListService;
use MiniShop3\Services\Product\ProductDataService;
use MODX\Revolution\modX;
use xPDO\xPDO;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// ------------------------------------------------------------------
// 1. Test CategoryProductsListService: formatProductRow preserves custom fields
// ------------------------------------------------------------------

$modx = new class extends modX {
    public function getFieldMeta($class)
    {
        return [
            'id' => [],
            'article' => [],
            'price' => [],
            'stock' => [],
            'barcode' => [],
            'custom_attr' => [],
        ];
    }

    public function makeUrl($id, $context = '', $args = '', $scheme = -1, array $options = [])
    {
        return '/product-url';
    }
};

$listService = new CategoryProductsListService($modx);

$ref = new ReflectionClass(CategoryProductsListService::class);
$formatMethod = $ref->getMethod('formatProductRow');
$formatMethod->setAccessible(true);

$row = [
    'id' => 10,
    'pagetitle' => 'Test Product',
    'article' => 'ART-10',
    'price' => 100.0,
    'stock' => 15,
    'barcode' => '4601234567890',
    'custom_attr' => 'custom-val',
    'unwanted_key' => 'drop-this',
];

$extraDataFields = ['stock', 'barcode', 'custom_attr'];
$formatted = $formatMethod->invoke($listService, $row, false, [], [], $extraDataFields);

if (!isset($formatted['barcode']) || $formatted['barcode'] !== '4601234567890') {
    $fail('formatProductRow did not include custom field barcode: ' . var_export($formatted, true));
}
if (!isset($formatted['custom_attr']) || $formatted['custom_attr'] !== 'custom-val') {
    $fail('formatProductRow did not include custom_attr');
}
if (isset($formatted['unwanted_key'])) {
    $fail('formatProductRow should not include unwanted_key');
}

// ------------------------------------------------------------------
// 2. Test CategoryProductsListService: mapSortField routes custom fields to Data.field
// ------------------------------------------------------------------

$mapSortMethod = $ref->getMethod('mapSortField');
$mapSortMethod->setAccessible(true);

$sortBarcode = $mapSortMethod->invoke($listService, 'barcode', [], [], 'msProduct.menuindex');
if ($sortBarcode !== 'Data.barcode') {
    $fail("mapSortField for barcode expected Data.barcode, got: {$sortBarcode}");
}

$sortPagetitle = $mapSortMethod->invoke($listService, 'pagetitle', [], [], 'msProduct.menuindex');
if ($sortPagetitle !== 'msProduct.pagetitle') {
    $fail("mapSortField for pagetitle expected msProduct.pagetitle, got: {$sortPagetitle}");
}

// ------------------------------------------------------------------
// 3. Test ProductDataService: updateProductData saves custom msProductData fields
// ------------------------------------------------------------------

class CustomFieldsTestProductData extends msProductData
{
    public array $fields = [];
    public bool $saved = false;
    public array $_fieldMeta = [];

    public function __construct(xPDO $xpdo, array $fields = [])
    {
        parent::__construct($xpdo);
        $this->fields = $fields;
        $this->_fieldMeta = [
            'id' => [],
            'article' => [],
            'price' => [],
            'barcode' => [],
            'custom_attr' => [],
        ];
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false, $rawValues = false, $adhoc = false)
    {
        foreach ($fields as $k => $v) {
            $this->fields[$k] = $v;
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

class CustomFieldsTestProduct extends msProduct
{
    public function __construct(public CustomFieldsTestProductData $data)
    {
    }

    public function checkPolicy($criteria, $targets = null, $user = null): bool
    {
        return true;
    }

    public function loadData()
    {
        return $this->data;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $k === 'id' ? 1 : null;
    }
}

$testProductData = new CustomFieldsTestProductData(new xPDO());
$testProduct = new CustomFieldsTestProduct($testProductData);

$testModx = new class ($testProduct) extends modX {
    public function __construct(private $product)
    {
        parent::__construct();
        $this->services = new class {
            public function has(string $key): bool { return false; }
            public function get(string $key): mixed { return null; }
        };
    }

    public function hasPermission(string $permission): bool
    {
        return true;
    }

    public function getObject($className, $criteria = null, $cacheFlag = true)
    {
        if ($className === msProduct::class) {
            return $this->product;
        }
        return null;
    }

    public function getFieldMeta($class)
    {
        return [
            'id' => [],
            'article' => [],
            'price' => [],
            'barcode' => [],
            'custom_attr' => [],
        ];
    }
};

$dataService = new class ($testModx) extends ProductDataService {
    protected function getProductRepeaterFields(): array { return []; }
    protected function getProductKeyValueFields(): array { return []; }
};

$updateResult = $dataService->updateProductData(1, [
    'price' => 250.0,
    'barcode' => '4609999999999',
    'custom_attr' => 'new-custom-value',
    'drop_this' => 'malicious_input',
]);

if (!$updateResult['ok']) {
    $fail('updateProductData failed: ' . var_export($updateResult, true));
}

if ($updateResult['data']['barcode'] !== '4609999999999') {
    $fail('updateProductData did not save barcode: ' . var_export($updateResult, true));
}

if ($updateResult['data']['custom_attr'] !== 'new-custom-value') {
    $fail('updateProductData did not save custom_attr: ' . var_export($updateResult, true));
}

if (isset($updateResult['data']['drop_this'])) {
    $fail('updateProductData should have dropped unknown key drop_this');
}

if (!$testProductData->saved) {
    $fail('testProductData->save() was not called');
}

echo "OK CategoryProductsCustomFieldsTest (All assertions passed)\n";
