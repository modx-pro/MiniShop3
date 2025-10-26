<?php
/**
 * Seeder для создания тестовых категорий (Categories)
 *
 * Создает 20 категорий с уровнем вложенности 0-3
 *
 * Запуск: php _build/seeders/seed-categories.php
 */

use MiniShop3\Model\msCategory;

// Подключаем MODX в CLI режиме
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
echo "  Seeding Categories (Категории)\n";
echo "===========================================\n\n";

// Иерархическая структура категорий
// parent => 0 означает корневую категорию
$categoriesTree = [
    // Уровень 0 (корневые категории)
    ['pagetitle' => 'Электроника', 'parent' => 0, 'alias' => 'electronics', 'description' => 'Электроника и техника'],
    ['pagetitle' => 'Одежда и обувь', 'parent' => 0, 'alias' => 'clothing', 'description' => 'Одежда, обувь и аксессуары'],
    ['pagetitle' => 'Дом и сад', 'parent' => 0, 'alias' => 'home-garden', 'description' => 'Товары для дома и сада'],
    ['pagetitle' => 'Спорт и отдых', 'parent' => 0, 'alias' => 'sport', 'description' => 'Спортивные товары'],

    // Уровень 1 (дочерние для "Электроника")
    ['pagetitle' => 'Смартфоны', 'parent' => 'electronics', 'alias' => 'smartphones', 'description' => 'Мобильные телефоны и смартфоны'],
    ['pagetitle' => 'Ноутбуки', 'parent' => 'electronics', 'alias' => 'laptops', 'description' => 'Ноутбуки и ультрабуки'],
    ['pagetitle' => 'Планшеты', 'parent' => 'electronics', 'alias' => 'tablets', 'description' => 'Планшетные компьютеры'],
    ['pagetitle' => 'Аудио', 'parent' => 'electronics', 'alias' => 'audio', 'description' => 'Наушники, колонки, аудиосистемы'],

    // Уровень 2 (дочерние для "Смартфоны")
    ['pagetitle' => 'Android', 'parent' => 'smartphones', 'alias' => 'android-phones', 'description' => 'Смартфоны на Android'],
    ['pagetitle' => 'iPhone', 'parent' => 'smartphones', 'alias' => 'iphones', 'description' => 'Apple iPhone'],
    ['pagetitle' => 'Аксессуары', 'parent' => 'smartphones', 'alias' => 'phone-accessories', 'description' => 'Чехлы, защитные стекла, зарядки'],

    // Уровень 1 (дочерние для "Одежда и обувь")
    ['pagetitle' => 'Мужская одежда', 'parent' => 'clothing', 'alias' => 'mens-clothing', 'description' => 'Одежда для мужчин'],
    ['pagetitle' => 'Женская одежда', 'parent' => 'clothing', 'alias' => 'womens-clothing', 'description' => 'Одежда для женщин'],
    ['pagetitle' => 'Обувь', 'parent' => 'clothing', 'alias' => 'shoes', 'description' => 'Обувь для всей семьи'],

    // Уровень 2 (дочерние для "Мужская одежда")
    ['pagetitle' => 'Рубашки', 'parent' => 'mens-clothing', 'alias' => 'shirts', 'description' => 'Мужские рубашки'],
    ['pagetitle' => 'Брюки', 'parent' => 'mens-clothing', 'alias' => 'pants', 'description' => 'Мужские брюки'],

    // Уровень 1 (дочерние для "Дом и сад")
    ['pagetitle' => 'Мебель', 'parent' => 'home-garden', 'alias' => 'furniture', 'description' => 'Мебель для дома'],
    ['pagetitle' => 'Освещение', 'parent' => 'home-garden', 'alias' => 'lighting', 'description' => 'Светильники и лампы'],

    // Уровень 1 (дочерние для "Спорт и отдых")
    ['pagetitle' => 'Фитнес', 'parent' => 'sport', 'alias' => 'fitness', 'description' => 'Товары для фитнеса'],
    ['pagetitle' => 'Туризм', 'parent' => 'sport', 'alias' => 'tourism', 'description' => 'Товары для туризма и кемпинга'],
];

$created = 0;
$errors = 0;
$categoryMap = []; // alias => id

// Функция для получения ID категории по alias
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

// Функция для определения уровня вложенности
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
        // Проверяем, существует ли уже категория
        $existing = $modx->getObject(msCategory::class, ['alias' => $catData['alias']]);
        if ($existing) {
            $categoryMap[$catData['alias']] = $existing->id;
            echo "  [SKIP] [{$catData['pagetitle']}] уже существует (ID: {$existing->id})\n";
            continue;
        }

        // Определяем parent_id
        $parentId = 0;
        if ($catData['parent'] !== 0) {
            $parentId = getCategoryId($modx, $catData['parent'], $categoryMap);
            if ($parentId === 0) {
                echo "  [WARN] [{$catData['pagetitle']}] родительская категория '{$catData['parent']}' не найдена, используем parent=0\n";
            }
        }

        // Создаем категорию
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
            echo "  [ERROR] [{$catData['pagetitle']}] Ошибка сохранения\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "  [ERROR] [{$catData['pagetitle']}] Исключение: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "-------------------------------------------\n";
echo "  Результаты:\n";
echo "-------------------------------------------\n";
echo sprintf("  Создано:    %d\n", $created);
echo sprintf("  Пропущено:  %d\n", count($categoriesTree) - $created - $errors);
echo sprintf("  Ошибок:     %d\n", $errors);
echo "-------------------------------------------\n";
echo "\n";

exit($errors > 0 ? 1 : 0);
