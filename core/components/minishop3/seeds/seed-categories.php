<?php
/**
 * Seeder for creating test categories
 *
 * Creates 20 categories with nesting level 0-3
 *
 * Usage: php _build/seeders/seed-categories.php
 */

use MiniShop3\Model\msCategory;

// Initialize MODX in CLI mode
define('MODX_API_MODE', true);
require_once dirname(__DIR__, 4) . '/index.php';

$modx = \MODX\Revolution\modX::getInstance(
    'web',
    [
        \xPDO\xPDO::OPT_CACHE_PATH => dirname(__DIR__, 4) . '/core/cache/',
    ]
);

echo "\n";
echo "===========================================\n";
echo "  Seeding Categories\n";
echo "===========================================\n\n";

// Hierarchical category structure
// parent => 0 means root category
$categoriesTree = [
    // Level 0 (root categories)
    ['pagetitle' => 'Electronics', 'parent' => 0, 'alias' => 'electronics', 'description' => 'Electronics and gadgets'],
    ['pagetitle' => 'Clothing & Shoes', 'parent' => 0, 'alias' => 'clothing', 'description' => 'Clothing, shoes and accessories'],
    ['pagetitle' => 'Home & Garden', 'parent' => 0, 'alias' => 'home-garden', 'description' => 'Home and garden products'],
    ['pagetitle' => 'Sports & Recreation', 'parent' => 0, 'alias' => 'sport', 'description' => 'Sports goods'],

    // Level 1 (children of "Electronics")
    ['pagetitle' => 'Smartphones', 'parent' => 'electronics', 'alias' => 'smartphones', 'description' => 'Mobile phones and smartphones'],
    ['pagetitle' => 'Laptops', 'parent' => 'electronics', 'alias' => 'laptops', 'description' => 'Laptops and ultrabooks'],
    ['pagetitle' => 'Tablets', 'parent' => 'electronics', 'alias' => 'tablets', 'description' => 'Tablet computers'],
    ['pagetitle' => 'Audio', 'parent' => 'electronics', 'alias' => 'audio', 'description' => 'Headphones, speakers, audio systems'],

    // Level 2 (children of "Smartphones")
    ['pagetitle' => 'Android', 'parent' => 'smartphones', 'alias' => 'android-phones', 'description' => 'Android smartphones'],
    ['pagetitle' => 'iPhone', 'parent' => 'smartphones', 'alias' => 'iphones', 'description' => 'Apple iPhone'],
    ['pagetitle' => 'Accessories', 'parent' => 'smartphones', 'alias' => 'phone-accessories', 'description' => 'Cases, screen protectors, chargers'],

    // Level 1 (children of "Clothing & Shoes")
    ['pagetitle' => 'Men\'s Clothing', 'parent' => 'clothing', 'alias' => 'mens-clothing', 'description' => 'Clothing for men'],
    ['pagetitle' => 'Women\'s Clothing', 'parent' => 'clothing', 'alias' => 'womens-clothing', 'description' => 'Clothing for women'],
    ['pagetitle' => 'Shoes', 'parent' => 'clothing', 'alias' => 'shoes', 'description' => 'Shoes for the whole family'],

    // Level 2 (children of "Men's Clothing")
    ['pagetitle' => 'Shirts', 'parent' => 'mens-clothing', 'alias' => 'shirts', 'description' => 'Men\'s shirts'],
    ['pagetitle' => 'Pants', 'parent' => 'mens-clothing', 'alias' => 'pants', 'description' => 'Men\'s pants'],

    // Level 1 (children of "Home & Garden")
    ['pagetitle' => 'Furniture', 'parent' => 'home-garden', 'alias' => 'furniture', 'description' => 'Home furniture'],
    ['pagetitle' => 'Lighting', 'parent' => 'home-garden', 'alias' => 'lighting', 'description' => 'Lamps and lights'],

    // Level 1 (children of "Sports & Recreation")
    ['pagetitle' => 'Fitness', 'parent' => 'sport', 'alias' => 'fitness', 'description' => 'Fitness products'],
    ['pagetitle' => 'Tourism', 'parent' => 'sport', 'alias' => 'tourism', 'description' => 'Tourism and camping products'],
];

$created = 0;
$errors = 0;
$categoryMap = []; // alias => id

// Function to get category ID by alias
function getCategoryId($modx, $alias, &$categoryMap) {
    if (isset($categoryMap[$alias])) {
        return $categoryMap[$alias];
    }

    $category = $modx->getObject(msCategory::class, ['alias' => $alias]);
    if ($category) {
        $categoryMap[$alias] = $category->id;
        return $category->id;
    }

    return 0;
}

// Function to determine nesting level
function getCategoryLevel($parent) {
    if ($parent === 0 || $parent === '0') return 0;

    static $levels = [
        'electronics' => 0,
        'clothing' => 0,
        'home-garden' => 0,
        'sport' => 0,
        'smartphones' => 1,
        'laptops' => 1,
        'tablets' => 1,
        'audio' => 1,
        'mens-clothing' => 1,
        'womens-clothing' => 1,
        'shoes' => 1,
        'furniture' => 1,
        'lighting' => 1,
        'fitness' => 1,
        'tourism' => 1,
        'android-phones' => 2,
        'iphones' => 2,
        'phone-accessories' => 2,
        'shirts' => 2,
        'pants' => 2,
    ];

    return isset($levels[$parent]) ? $levels[$parent] + 1 : 0;
}

foreach ($categoriesTree as $i => $catData) {
    try {
        // Check if category already exists
        $existing = $modx->getObject(msCategory::class, ['alias' => $catData['alias']]);
        if ($existing) {
            $categoryMap[$catData['alias']] = $existing->id;
            echo "  [SKIP] [{$catData['pagetitle']}] already exists (ID: {$existing->id})\n";
            continue;
        }

        // Determine parent_id
        $parentId = 0;
        if ($catData['parent'] !== 0) {
            $parentId = getCategoryId($modx, $catData['parent'], $categoryMap);
            if ($parentId === 0) {
                echo "  [WARN] [{$catData['pagetitle']}] parent category '{$catData['parent']}' not found, using parent=0\n";
            }
        }

        // Create category
        $category = $modx->newObject(msCategory::class);
        $category->fromArray([
            'pagetitle' => $catData['pagetitle'],
            'alias' => $catData['alias'],
            'description' => $catData['description'],
            'parent' => $parentId,
            'published' => 1,
            'hidemenu' => 0,
            'template' => 0,
            'class_key' => msCategory::class,
            'context_key' => 'web',
            'content_type' => 1,
            'richtext' => 0,
            'searchable' => 1,
            'cacheable' => 1,
            'deleted' => 0,
            'menuindex' => $i,
        ]);

        if ($category->save()) {
            $categoryMap[$catData['alias']] = $category->id;
            $created++;

            $level = getCategoryLevel($catData['parent']);
            $indent = str_repeat('  ', $level);

            echo sprintf(
                "  [OK] [%d/%d] %s%s (ID: %d, Level: %d, Parent: %d)\n",
                $i + 1,
                count($categoriesTree),
                $indent,
                $catData['pagetitle'],
                $category->id,
                $level,
                $parentId
            );
        } else {
            $errors++;
            echo "  [ERROR] [{$catData['pagetitle']}] Save error\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "  [ERROR] [{$catData['pagetitle']}] Exception: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "-------------------------------------------\n";
echo "  Results:\n";
echo "-------------------------------------------\n";
echo sprintf("  Created:    %d\n", $created);
echo sprintf("  Skipped:    %d\n", count($categoriesTree) - $created - $errors);
echo sprintf("  Errors:     %d\n", $errors);
echo "-------------------------------------------\n";
echo "\n";

exit($errors > 0 ? 1 : 0);
