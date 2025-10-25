<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msProductField;
use MiniShop3\Utils\ExtraFields;
use MODX\Revolution\modX;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtraFieldsService
{
    private modX $modx;
    private MigrationGenerator $migrationGenerator;
    private ExtraFields $extraFieldsUtil;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->migrationGenerator = new MigrationGenerator($modx);
        $this->extraFieldsUtil = new ExtraFields($modx);
    }

    /**
     * Создать дополнительное поле с миграцией
     */
    public function createField(array $data): array
    {
        // 1. Валидация
        $validation = $this->validateFieldData($data);
        if (!$validation['success']) {
            return $validation;
        }

        // 2. Создаём запись msExtraField
        /** @var msExtraField $field */
        $field = $this->modx->newObject(msExtraField::class);
        $field->fromArray($data);

        if (!$field->save()) {
            return ['success' => false, 'message' => 'Ошибка создания поля в базе данных'];
        }

        // 3. Генерируем миграцию
        try {
            $migrationFile = $this->migrationGenerator->generateAddColumnMigration($field);
        } catch (\Exception $e) {
            $field->remove();
            return ['success' => false, 'message' => 'Ошибка генерации миграции: ' . $e->getMessage()];
        }

        // 4. Запускаем миграцию
        $migrationResult = $this->runMigrations();

        if (!$migrationResult['success']) {
            // Откатываем создание поля
            $field->remove();
            @unlink($migrationFile);
            return $migrationResult;
        }

        // 5. Удаляем файл миграции (она уже применена и записана в phinxlog)
        @unlink($migrationFile);
        $this->modx->log(modX::LOG_LEVEL_INFO, "[ExtraFieldsService] Migration file deleted: " . basename($migrationFile));

        // 6. Обновляем xPDO map
        $this->extraFieldsUtil->loadMap();
        $this->extraFieldsUtil->clearCache();

        // 7. АВТОМАТИЧЕСКИ создаём msProductField
        $this->createProductFieldFromExtra($field);

        return [
            'success' => true,
            'message' => 'Поле успешно создано',
            'data' => $field->toArray(),
            'migration' => basename($migrationFile),
            'output' => $migrationResult['output'] ?? ''
        ];
    }

    /**
     * Удалить дополнительное поле
     */
    public function deleteField(int $id): array
    {
        /** @var msExtraField $field */
        $field = $this->modx->getObject(msExtraField::class, $id);

        if (!$field) {
            return ['success' => false, 'message' => 'Поле не найдено'];
        }

        // 1. Генерируем миграцию для удаления колонки
        try {
            $migrationFile = $this->migrationGenerator->generateDropColumnMigration($field);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка генерации миграции: ' . $e->getMessage()];
        }

        // 2. Запускаем миграцию
        $migrationResult = $this->runMigrations();

        if (!$migrationResult['success']) {
            @unlink($migrationFile);
            return $migrationResult;
        }

        // 3. Удаляем файл миграции (она уже применена и записана в phinxlog)
        @unlink($migrationFile);
        $this->modx->log(modX::LOG_LEVEL_INFO, "[ExtraFieldsService] Migration file deleted: " . basename($migrationFile));

        // 4. Удаляем связанные msProductField (CASCADE)
        $this->deleteProductFieldsByName($field->get('key'));

        // 5. Удаляем msExtraField
        $field->remove();

        // 6. Очищаем кеш
        $this->extraFieldsUtil->clearCache();

        return [
            'success' => true,
            'message' => 'Поле успешно удалено',
            'migration' => basename($migrationFile),
            'output' => $migrationResult['output'] ?? ''
        ];
    }

    /**
     * Получить список всех дополнительных полей
     */
    public function getFields(array $criteria = []): array
    {
        $c = $this->modx->newQuery(msExtraField::class);

        if (!empty($criteria)) {
            $c->where($criteria);
        }

        $c->sortby('id', 'ASC');

        $fields = $this->modx->getCollection(msExtraField::class, $c);

        $result = [];
        foreach ($fields as $field) {
            $data = $field->toArray();

            // Проверяем существование колонки в БД
            $data['column_exists'] = $this->extraFieldsUtil->columnExists(
                $field->get('class'),
                $field->get('key')
            );

            $result[] = $data;
        }

        return $result;
    }

    /**
     * Валидация данных поля
     */
    private function validateFieldData(array $data): array
    {
        $required = ['class', 'key', 'dbtype', 'phptype'];

        foreach ($required as $fieldName) {
            if (empty($data[$fieldName])) {
                return [
                    'success' => false,
                    'message' => "Поле '{$fieldName}' обязательно для заполнения",
                    'field' => $fieldName
                ];
            }
        }

        // Проверка уникальности key в рамках class
        $exists = $this->modx->getObject(msExtraField::class, [
            'class' => $data['class'],
            'key' => $data['key']
        ]);

        if ($exists) {
            return [
                'success' => false,
                'message' => "Поле с именем '{$data['key']}' уже существует для класса '{$data['class']}'",
                'field' => 'key'
            ];
        }

        // Проверка что колонка не существует в БД
        if ($this->extraFieldsUtil->columnExists($data['class'], $data['key'])) {
            return [
                'success' => false,
                'message' => "Колонка '{$data['key']}' уже существует в таблице",
                'field' => 'key'
            ];
        }

        return ['success' => true];
    }

    /**
     * Запускает все pending миграции
     */
    private function runMigrations(): array
    {
        try {
            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $vendorAutoload = $componentPath . 'vendor/autoload.php';
            $phinxConfig = $componentPath . 'phinx.php';

            if (!file_exists($vendorAutoload)) {
                return ['success' => false, 'message' => 'Phinx не установлен. Запустите composer install'];
            }

            if (!file_exists($phinxConfig)) {
                return ['success' => false, 'message' => 'Конфигурация Phinx не найдена'];
            }

            // Загружаем конфиг Phinx
            $configArray = require $phinxConfig;
            $config = new Config($configArray);

            // Создаём input/output (эмуляция CLI)
            $input = new StringInput('');
            $output = new BufferedOutput();

            // Создаём менеджер миграций
            $manager = new Manager($config, $input, $output);

            // Запускаем миграции БЕЗ exec()
            $manager->migrate('production');

            // Получаем вывод
            $outputText = $output->fetch();

            $this->modx->log(modX::LOG_LEVEL_INFO, '[ExtraFieldsService] Миграции выполнены успешно');

            return [
                'success' => true,
                'output' => $outputText
            ];

        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[ExtraFieldsService] Ошибка миграции: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Ошибка выполнения миграции: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * АВТОМАТИЧЕСКИ создаёт msProductField при создании msExtraField
     */
    private function createProductFieldFromExtra(msExtraField $extraField): void
    {
        // Пока создаём без секции (section = NULL)
        // В будущем можно добавить логику определения секции

        /** @var msProductField $productField */
        $productField = $this->modx->newObject(msProductField::class);
        $productField->fromArray([
            'name' => $extraField->get('key'),
            'label' => $extraField->get('label') ?: $extraField->get('key'),
            'description' => $extraField->get('description'),
            'xtype' => $extraField->get('xtype') ?: 'textfield',
            'section' => null, // Без секции по умолчанию
            'visible' => 1,
            'required' => 0,
            'sort_order' => 999, // В конец списка
            'width' => 6,
            'is_system' => 0,
            'is_default' => 0,
            'active' => $extraField->get('active'),
        ]);

        if ($productField->save()) {
            $this->modx->log(modX::LOG_LEVEL_INFO,
                "[ExtraFieldsService] Auto-created msProductField: {$extraField->get('key')}");
        } else {
            $this->modx->log(modX::LOG_LEVEL_WARN,
                "[ExtraFieldsService] Failed to auto-create msProductField: {$extraField->get('key')}");
        }
    }

    /**
     * Обновить дополнительное поле (только метаданные, без изменения структуры БД)
     *
     * @param int $id ID поля
     * @param array $data Данные для обновления
     * @return array
     */
    public function updateField(int $id, array $data): array
    {
        /** @var msExtraField $field */
        $field = $this->modx->getObject(msExtraField::class, $id);

        if (!$field) {
            return ['success' => false, 'message' => 'Поле не найдено'];
        }

        // Разрешаем изменять только метаданные (не требующие миграции БД)
        $allowedFields = ['label', 'description', 'xtype', 'active'];

        foreach ($allowedFields as $fieldName) {
            if (isset($data[$fieldName])) {
                $field->set($fieldName, $data[$fieldName]);
            }
        }

        if (!$field->save()) {
            return ['success' => false, 'message' => 'Ошибка обновления поля в базе данных'];
        }

        // Обновляем связанную запись msProductField (если существует)
        $this->updateProductFieldFromExtra($field);

        // Очищаем кеш
        $this->extraFieldsUtil->clearCache();

        return [
            'success' => true,
            'message' => 'Поле успешно обновлено',
            'data' => $field->toArray()
        ];
    }

    /**
     * Обновляет msProductField на основе msExtraField
     *
     * @param msExtraField $extraField
     * @return void
     */
    private function updateProductFieldFromExtra(msExtraField $extraField): void
    {
        // Находим связанную запись msProductField по имени
        $productField = $this->modx->getObject(msProductField::class, ['name' => $extraField->get('key')]);

        if ($productField) {
            $productField->set('label', $extraField->get('label') ?: $extraField->get('key'));
            $productField->set('description', $extraField->get('description'));
            $productField->set('xtype', $extraField->get('xtype') ?: 'textfield');
            $productField->set('active', $extraField->get('active'));

            if ($productField->save()) {
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[ExtraFieldsService] Updated msProductField: {$extraField->get('key')}");
            } else {
                $this->modx->log(modX::LOG_LEVEL_WARN,
                    "[ExtraFieldsService] Failed to update msProductField: {$extraField->get('key')}");
            }
        }
    }

    /**
     * Удаляет msProductField по имени (CASCADE delete)
     */
    private function deleteProductFieldsByName(string $fieldName): void
    {
        $fields = $this->modx->getCollection(msProductField::class, ['name' => $fieldName]);

        foreach ($fields as $field) {
            $field->remove();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "[ExtraFieldsService] Deleted msProductField: {$fieldName}");
    }
}
