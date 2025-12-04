<?php
/**
 * Seeder for creating test products
 *
 * Creates 100 products with random categories and vendors
 *
 * Usage: php _build/seeders/seed-products.php
 * With count parameter: php _build/seeders/seed-products.php 200
 */

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msCategory;
use MiniShop3\Model\msVendor;

// Initialize MODX in CLI mode
define('MODX_API_MODE', true);
require_once dirname(__DIR__, 4) . '/index.php';

$modx = \MODX\Revolution\modX::getInstance(
    'web',
    [
        \xPDO\xPDO::OPT_CACHE_PATH => dirname(__DIR__, 4) . '/core/cache/',
    ]
);

// Initialize MiniShop3 to register services
$modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);

// Product count (default 100)
$count = isset($argv[1]) ? (int)$argv[1] : 100;

echo "\n";
echo "===========================================\n";
echo sprintf("  Seeding Products: %d\n", $count);
echo "===========================================\n\n";

// Get all categories
$categories = $modx->getCollection(msCategory::class);
if (empty($categories)) {
    echo "  [ERROR] Categories not found! Run seed-categories.php first\n\n";
    exit(1);
}

$categoryIds = [];
foreach ($categories as $category) {
    $categoryIds[] = $category->id;
}
echo "  [INFO] Found categories: " . count($categoryIds) . "\n";

// Get all vendors
$vendors = $modx->getCollection(msVendor::class);
if (empty($vendors)) {
    echo "  [ERROR] Vendors not found! Run seed-vendors.php first\n\n";
    exit(1);
}

$vendorIds = [];
$vendorNames = [];
foreach ($vendors as $vendor) {
    $vendorIds[] = $vendor->id;
    $vendorNames[$vendor->id] = $vendor->name;
}
echo "  [INFO] Found vendors: " . count($vendorIds) . "\n\n";

// Product name templates
$productTemplates = [
    'Smartphone %s %s %dGB',
    'Laptop %s %s %d"',
    'Tablet %s %s %dGB',
    'Headphones %s %s',
    'Watch %s %s',
    'Keyboard %s %s',
    'Mouse %s %s',
    'Monitor %s %s %d"',
    'Speaker %s %s',
    'Charger %s %s',
];

$models = ['Pro', 'Max', 'Ultra', 'Lite', 'Plus', 'Mini', 'Air', 'Edge', 'Prime', 'Neo'];
$colors = ['Black', 'White', 'Gray', 'Blue', 'Red', 'Gold', 'Silver', 'Green'];
$sizes = ['S', 'M', 'L', 'XL', '42', '44', '46', '48'];
$countries = ['China', 'USA', 'South Korea', 'Japan', 'Taiwan', 'Vietnam'];

$created = 0;
$errors = 0;

for ($i = 0; $i < $count; $i++) {
    try {
        // Generate random data
        $vendorId = $vendorIds[array_rand($vendorIds)];
        $vendorName = $vendorNames[$vendorId];
        $categoryId = $categoryIds[array_rand($categoryIds)];

        $template = $productTemplates[array_rand($productTemplates)];
        $model = $models[array_rand($models)];

        // Generate title based on template
        if (strpos($template, '%d"') !== false) {
            // For monitors and laptops with screen size
            $pagetitle = sprintf($template, $vendorName, $model, rand(13, 32));
        } elseif (strpos($template, '%dGB') !== false) {
            // For devices with memory
            $pagetitle = sprintf($template, $vendorName, $model, rand(64, 512));
        } else {
            // For other products
            $pagetitle = sprintf($template, $vendorName, $model);
        }

        // Generate alias
        $alias = strtolower(str_replace(' ', '-', transliterate($pagetitle)));

        // Check alias uniqueness
        $existing = $modx->getObject(msProduct::class, ['alias' => $alias]);
        if ($existing) {
            $alias .= '-' . time() . '-' . rand(100, 999);
        }

        // Random prices and flags
        $price = rand(5000, 150000);
        $hasDiscount = rand(0, 100) < 30; // 30% of products with discount
        $oldPrice = $hasDiscount ? $price + rand(1000, 20000) : 0;
        $isNew = rand(0, 100) < 20 ? 1 : 0;
        $isPopular = rand(0, 100) < 15 ? 1 : 0;
        $isFavorite = rand(0, 100) < 10 ? 1 : 0;

        // Create product (msProduct extends modResource)
        // xPDO will automatically save data to both tables by class_key
        $product = $modx->newObject(msProduct::class);
        $product->fromArray([
            // modResource fields (modx_site_content)
            'pagetitle' => $pagetitle,
            'alias' => $alias,
            'description' => 'Product description ' . $pagetitle,
            'introtext' => 'Short product description ' . $pagetitle,
            'parent' => $categoryId,
            'published' => 1,
            'hidemenu' => 0,
            'template' => 0,
            'class_key' => msProduct::class,
            'context_key' => 'web',
            'content_type' => 1,
            'richtext' => 0,
            'searchable' => 1,
            'cacheable' => 1,
            'deleted' => 0,
            'menuindex' => $i,

            // msProductData fields (ms3_products)
            'article' => 'ART-' . rand(100000, 999999), // Temporary, will update after save
            'price' => $price,
            'old_price' => $oldPrice,
            'stock' => rand(0, 100),
            'weight' => round(rand(100, 5000) / 1000, 3),
            'vendor_id' => $vendorId,
            'made_in' => $countries[array_rand($countries)],
            'new' => $isNew,
            'popular' => $isPopular,
            'favorite' => $isFavorite,
            'tags' => ['product', 'new', $vendorName], // Array → JSON → ms3_product_options
            'color' => array_slice($colors, 0, rand(2, 5)), // Array → JSON → ms3_product_options
            'size' => array_slice($sizes, 0, rand(2, 4)), // Array → JSON → ms3_product_options
            'source_id' => 1,
            'image' => '',
            'thumb' => '',
        ]);

        if ($product->save()) {
            // Update article with correct ID
            $product->set('article', 'ART-' . str_pad($product->id, 6, '0', STR_PAD_LEFT));
            $product->save();

            $created++;

            $badges = [];
            if ($isNew) $badges[] = 'NEW';
            if ($isPopular) $badges[] = 'HIT';
            if ($isFavorite) $badges[] = 'FAV';
            if ($hasDiscount) $badges[] = '-' . round((($oldPrice - $price) / $oldPrice) * 100) . '%';

            echo sprintf(
                "  [OK] [%d/%d] %s (ID: %d, Cat: %d, Vendor: %s, Price: %s) %s\n",
                $i + 1,
                $count,
                $pagetitle,
                $product->id,
                $categoryId,
                $vendorName,
                number_format($price, 0, '.', ' ') . ' RUB',
                !empty($badges) ? '[' . implode(', ', $badges) . ']' : ''
            );
        } else {
            $errors++;
            echo "  [ERROR] [$pagetitle] Product save error\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "  [ERROR] Exception: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "-------------------------------------------\n";
echo "  Results:\n";
echo "-------------------------------------------\n";
echo sprintf("  Created:    %d\n", $created);
echo sprintf("  Errors:     %d\n", $errors);
echo "-------------------------------------------\n";
echo "\n";

// Helper function for transliteration
function transliterate($string) {
    $converter = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
        'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch', 'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ];

    return strtr($string, $converter);
}

exit($errors > 0 ? 1 : 0);
