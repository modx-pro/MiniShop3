<?php
/**
 * Seeder для создания тестовых товаров (Products)
 *
 * Создает 100 товаров с случайными категориями и производителями
 *
 * Запуск: php _build/seeders/seed-products.php
 * С параметром количества: php _build/seeders/seed-products.php 200
 */

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msCategory;
use MiniShop3\Model\msVendor;

// Подключаем MODX в CLI режиме
define('MODX_API_MODE', true);
require_once dirname(__DIR__, 4) . '/index.php';

$modx = \MODX\Revolution\modX::getInstance(
    'web',
    [
        \xPDO\xPDO::OPT_CACHE_PATH => dirname(__DIR__, 4) . '/core/cache/',
    ]
);

// Количество товаров (по умолчанию 100)
$count = isset($argv[1]) ? (int)$argv[1] : 100;

echo "\n";
echo "===========================================\n";
echo sprintf("  Seeding Products (Товары): %d\n", $count);
echo "===========================================\n\n";

// Получаем все категории
$categories = $modx->getCollection(msCategory::class);
if (empty($categories)) {
    echo "  [ERROR] Категории не найдены! Сначала запустите seed-categories.php\n\n";
    exit(1);
}

$categoryIds = [];
foreach ($categories as $category) {
    $categoryIds[] = $category->id;
}
echo "  [INFO] Найдено категорий: " . count($categoryIds) . "\n";

// Получаем всех производителей
$vendors = $modx->getCollection(msVendor::class);
if (empty($vendors)) {
    echo "  [ERROR] Производители не найдены! Сначала запустите seed-vendors.php\n\n";
    exit(1);
}

$vendorIds = [];
$vendorNames = [];
foreach ($vendors as $vendor) {
    $vendorIds[] = $vendor->id;
    $vendorNames[$vendor->id] = $vendor->name;
}
echo "  [INFO] Найдено производителей: " . count($vendorIds) . "\n\n";

// Шаблоны названий товаров
$productTemplates = [
    'Смартфон %s %s %dGB',
    'Ноутбук %s %s %d"',
    'Планшет %s %s %dGB',
    'Наушники %s %s',
    'Часы %s %s',
    'Клавиатура %s %s',
    'Мышь %s %s',
    'Монитор %s %s %d"',
    'Колонка %s %s',
    'Зарядка %s %s',
];

$models = ['Pro', 'Max', 'Ultra', 'Lite', 'Plus', 'Mini', 'Air', 'Edge', 'Prime', 'Neo'];
$colors = ['Черный', 'Белый', 'Серый', 'Синий', 'Красный', 'Золотой', 'Серебристый', 'Зеленый'];
$sizes = ['S', 'M', 'L', 'XL', '42', '44', '46', '48'];
$countries = ['Китай', 'США', 'Южная Корея', 'Япония', 'Тайвань', 'Вьетнам'];

$created = 0;
$errors = 0;

for ($i = 0; $i < $count; $i++) {
    try {
        // Генерируем случайные данные
        $vendorId = $vendorIds[array_rand($vendorIds)];
        $vendorName = $vendorNames[$vendorId];
        $categoryId = $categoryIds[array_rand($categoryIds)];

        $template = $productTemplates[array_rand($productTemplates)];
        $model = $models[array_rand($models)];

        // Генерируем название в зависимости от шаблона
        if (strpos($template, '%d"') !== false) {
            // Для мониторов и ноутбуков с диагональю
            $pagetitle = sprintf($template, $vendorName, $model, rand(13, 32));
        } elseif (strpos($template, '%dGB') !== false) {
            // Для устройств с памятью
            $pagetitle = sprintf($template, $vendorName, $model, rand(64, 512));
        } else {
            // Для остальных товаров
            $pagetitle = sprintf($template, $vendorName, $model);
        }

        // Генерируем alias
        $alias = strtolower(str_replace(' ', '-', transliterate($pagetitle)));

        // Проверяем уникальность alias
        $existing = $modx->getObject(msProduct::class, ['alias' => $alias]);
        if ($existing) {
            $alias .= '-' . time() . '-' . rand(100, 999);
        }

        // Случайные цены и флаги
        $price = rand(5000, 150000);
        $hasDiscount = rand(0, 100) < 30; // 30% товаров со скидкой
        $oldPrice = $hasDiscount ? $price + rand(1000, 20000) : 0;
        $isNew = rand(0, 100) < 20 ? 1 : 0;
        $isPopular = rand(0, 100) < 15 ? 1 : 0;
        $isFavorite = rand(0, 100) < 10 ? 1 : 0;

        // Создаем товар (msProduct extends modResource)
        // xPDO автоматически сохранит данные в обе таблицы по class_key
        $product = $modx->newObject(msProduct::class);
        $product->fromArray([
            // Поля modResource (modx_site_content)
            'pagetitle' => $pagetitle,
            'alias' => $alias,
            'description' => 'Описание товара ' . $pagetitle,
            'introtext' => 'Краткое описание товара ' . $pagetitle,
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

            // Поля msProductData (ms3_products)
            'article' => 'ART-' . rand(100000, 999999), // Временный, обновим после сохранения
            'price' => $price,
            'old_price' => $oldPrice,
            'stock' => rand(0, 100),
            'weight' => round(rand(100, 5000) / 1000, 3),
            'vendor_id' => $vendorId,
            'made_in' => $countries[array_rand($countries)],
            'new' => $isNew,
            'popular' => $isPopular,
            'favorite' => $isFavorite,
            'tags' => ['товар', 'новинка', $vendorName], // Массив → JSON → ms3_product_options
            'color' => array_slice($colors, 0, rand(2, 5)), // Массив → JSON → ms3_product_options
            'size' => array_slice($sizes, 0, rand(2, 4)), // Массив → JSON → ms3_product_options
            'source_id' => 1,
            'image' => '',
            'thumb' => '',
        ]);

        if ($product->save()) {
            // Обновляем артикул с правильным ID
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
            echo "  [ERROR] [$pagetitle] Ошибка сохранения Product\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "  [ERROR] Исключение: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "-------------------------------------------\n";
echo "  Результаты:\n";
echo "-------------------------------------------\n";
echo sprintf("  Создано:    %d\n", $created);
echo sprintf("  Ошибок:     %d\n", $errors);
echo "-------------------------------------------\n";
echo "\n";

// Вспомогательная функция транслитерации
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
