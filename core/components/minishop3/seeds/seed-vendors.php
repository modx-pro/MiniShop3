<?php
/**
 * Seeder для создания тестовых производителей (Vendors)
 *
 * Создает 10 произвольных записей производителей
 *
 * Запуск: php _build/seeders/seed-vendors.php
 */

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

echo "\n";
echo "===========================================\n";
echo "  Seeding Vendors (Производители)\n";
echo "===========================================\n\n";

// Данные для генерации
$vendors = [
    [
        'name' => 'Apple',
        'country' => 'США',
        'description' => 'Американская корпорация, производитель персональных и планшетных компьютеров, аудиоплееров, телефонов, программного обеспечения',
        'position' => 1,
    ],
    [
        'name' => 'Samsung',
        'country' => 'Южная Корея',
        'description' => 'Южнокорейская группа компаний, один из крупнейших чеболей, основанный в 1938 году',
        'position' => 2,
    ],
    [
        'name' => 'Xiaomi',
        'country' => 'Китай',
        'description' => 'Китайская компания, производитель электроники и программного обеспечения',
        'position' => 3,
    ],
    [
        'name' => 'Google',
        'country' => 'США',
        'description' => 'Американская транснациональная публичная корпорация, инвестирующая в интернет-поиск, облачные вычисления и рекламные технологии',
        'position' => 4,
    ],
    [
        'name' => 'Huawei',
        'country' => 'Китай',
        'description' => 'Китайская компания, крупнейший производитель телекоммуникационного оборудования',
        'position' => 5,
    ],
    [
        'name' => 'OnePlus',
        'country' => 'Китай',
        'description' => 'Китайский производитель смартфонов, основанный бывшими сотрудниками Oppo',
        'position' => 6,
    ],
    [
        'name' => 'Sony',
        'country' => 'Япония',
        'description' => 'Японская корпорация, один из крупнейших производителей электроники',
        'position' => 7,
    ],
    [
        'name' => 'LG',
        'country' => 'Южная Корея',
        'description' => 'Южнокорейский конгломерат, один из крупнейших производителей бытовой электроники',
        'position' => 8,
    ],
    [
        'name' => 'Motorola',
        'country' => 'США',
        'description' => 'Американская телекоммуникационная компания, производитель электроники',
        'position' => 9,
    ],
    [
        'name' => 'Nokia',
        'country' => 'Финляндия',
        'description' => 'Финская телекоммуникационная корпорация',
        'position' => 10,
    ],
];

$created = 0;
$errors = 0;

foreach ($vendors as $i => $vendorData) {
    try {
        // Проверяем, существует ли уже производитель
        $existing = $modx->getObject(msVendor::class, ['name' => $vendorData['name']]);
        if ($existing) {
            echo "  [SKIP] [{$vendorData['name']}] уже существует (ID: {$existing->id})\n";
            continue;
        }

        // Создаем нового производителя
        $vendor = $modx->newObject(msVendor::class);
        $vendor->fromArray([
            'name' => $vendorData['name'],
            'country' => $vendorData['country'],
            'description' => $vendorData['description'],
            'position' => $vendorData['position'],
            'resource_id' => 0,
            'logo' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'properties' => null,
        ]);

        if ($vendor->save()) {
            $created++;
            echo sprintf(
                "  [OK] [%d/%d] %s (ID: %d) - %s\n",
                $i + 1,
                count($vendors),
                $vendorData['name'],
                $vendor->id,
                $vendorData['country']
            );
        } else {
            $errors++;
            echo "  [ERROR] [{$vendorData['name']}] Ошибка сохранения\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "  [ERROR] [{$vendorData['name']}] Исключение: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "-------------------------------------------\n";
echo "  Результаты:\n";
echo "-------------------------------------------\n";
echo sprintf("  Создано:    %d\n", $created);
echo sprintf("  Пропущено:  %d\n", count($vendors) - $created - $errors);
echo sprintf("  Ошибок:     %d\n", $errors);
echo "-------------------------------------------\n";
echo "\n";

exit($errors > 0 ? 1 : 0);
