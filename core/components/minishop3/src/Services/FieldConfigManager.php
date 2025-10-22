<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;
use MiniShop3\Model\msFieldConfigOverride;

/**
 * Универсальный сервис для работы с полями моделей
 *
 * Обеспечивает автоматическое чтение полей из xPDO моделей и их конфигурацию
 */
class FieldConfigManager
{
    /** @var modX */
    protected $modx;

    /** @var ConfigManager */
    protected $configManager;

    /** @var array Кеш загруженных алиасов моделей */
    protected $modelAliases = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->configManager = new ConfigManager($modx);
        $this->loadModelAliases();
    }

    /**
     * Загрузить маппинг alias → model class
     */
    protected function loadModelAliases(): void
    {
        $aliasesPath = MODX_CORE_PATH . 'components/minishop3/config/model_aliases.php';

        if (file_exists($aliasesPath)) {
            $this->modelAliases = include $aliasesPath;
        }
    }

    /**
     * Получить полный класс модели по alias
     *
     * @param string $alias
     * @return string|null
     */
    public function getModelClassByAlias(string $alias): ?string
    {
        return $this->modelAliases[$alias] ?? null;
    }

    /**
     * Загрузить поля из таблицы ms3_product_fields
     *
     * @return array
     */
    public function loadFieldsFromDatabase(): array
    {
        $query = $this->modx->newQuery('MiniShop3\\Model\\msProductField');
        $query->where(['visible' => true]);
        $query->sortby('sort_order', 'ASC');

        $collection = $this->modx->getCollection('MiniShop3\\Model\\msProductField', $query);

        $fields = [];
        foreach ($collection as $field) {
            $configRaw = $field->get('config');

            // xPDO 3 с phptype='json' автоматически десериализует JSON в массив
            if (is_array($configRaw)) {
                $config = $configRaw;
            } elseif (is_string($configRaw) && !empty($configRaw)) {
                $config = json_decode($configRaw, true) ?: [];
            } else {
                $config = [];
            }

            $fieldData = [
                'name' => $field->get('name'),
                'label' => $field->get('label'),
                'xtype' => $field->get('xtype'),
                'section' => $field->get('section'),
                'visible' => (bool)$field->get('visible'),
                'required' => (bool)$field->get('required'),
                'sort_order' => (int)$field->get('sort_order'),
                'width' => (int)$field->get('width'),
                'description' => $field->get('description'),
                'is_system' => (bool)$field->get('is_system'),
                'is_default' => (bool)$field->get('is_default'),
            ];

            // Мерджим дополнительные настройки из config JSON
            if (is_array($config)) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    /**
     * Получить все поля модели с автоматическим определением типов
     *
     * @param string $modelClass Полное имя класса модели
     * @return array Массив полей из модели
     * @throws \Exception
     */
    public function getModelFields(string $modelClass): array
    {
        // Получаем метамап модели
        if (!class_exists($modelClass)) {
            throw new \Exception("Model class not found: {$modelClass}");
        }

        // Для xPDO 3 используем MySQL generated класс
        $mysqlClass = str_replace('\\Model\\', '\\Model\\mysql\\', $modelClass);

        if (!class_exists($mysqlClass)) {
            throw new \Exception("MySQL model class not found: {$mysqlClass}");
        }

        // Для MySQL моделей используем статический $metaMap
        $metaMap = $mysqlClass::$metaMap ?? null;

        if (!$metaMap || !isset($metaMap['fields'])) {
            throw new \Exception("Model {$mysqlClass} does not have metaMap with fields");
        }

        $fields = [];
        $fieldMeta = $metaMap['fieldMeta'] ?? [];

        foreach ($metaMap['fields'] as $fieldName => $defaultValue) {
            // Пропускаем служебные поля id
            if ($fieldName === 'id') {
                continue;
            }

            $meta = $fieldMeta[$fieldName] ?? [];

            $fields[] = [
                'name' => $fieldName,
                'label_key' => 'ms3_product_' . $fieldName, // Ключ лексикона
                'description_key' => 'ms3_product_' . $fieldName . '_help', // Ключ описания
                'xtype' => $this->guessXtype($meta),
                'width' => 4, // По умолчанию 1/3 экрана (4 из 12 колонок)
                'visible' => true,
                'editable' => true,
                'required' => !($meta['null'] ?? true),
                'default' => $defaultValue,
                'phptype' => $meta['phptype'] ?? 'string',
                'dbtype' => $meta['dbtype'] ?? 'varchar',
            ];
        }

        return $fields;
    }

    /**
     * Определить xtype виджета по метаданным поля
     *
     * @param array $fieldMeta Метаданные поля из $metaMap['fieldMeta']
     * @return string
     */
    protected function guessXtype(array $fieldMeta): string
    {
        $phptype = $fieldMeta['phptype'] ?? 'string';
        $dbtype = $fieldMeta['dbtype'] ?? '';
        $precision = $fieldMeta['precision'] ?? '';

        // Boolean: tinyint(1) или phptype=boolean
        if ($phptype === 'boolean' || ($dbtype === 'tinyint' && $precision === '1')) {
            return 'checkbox';
        }

        // Numeric: float, decimal, int с длиной > 1
        if (in_array($phptype, ['float', 'integer']) || in_array($dbtype, ['decimal', 'int'])) {
            return 'numberfield';
        }

        // JSON: text + phptype=json
        if ($phptype === 'json') {
            return 'textarea';
        }

        // Text fields
        if ($dbtype === 'text') {
            return 'textarea';
        }

        // Default
        return 'textfield';
    }

    /**
     * Сгенерировать читаемый label из имени поля
     *
     * @param string $fieldName
     * @return string
     */
    protected function generateLabel(string $fieldName): string
    {
        // Преобразуем snake_case в Title Case
        $words = explode('_', $fieldName);
        $words = array_map('ucfirst', $words);
        return implode(' ', $words);
    }

    /**
     * Получить полную конфигурацию полей для страницы с учетом модели, JSON и БД
     *
     * @param string $pageKey Ключ страницы (product_data, order, etc)
     * @param string|null $modelAlias Alias модели (если null, берется из JSON конфига)
     * @param string $contextKey Контекст MODX
     * @return array
     * @throws \Exception
     */
    public function getPageFieldsConfig(string $pageKey, ?string $modelAlias = null, string $contextKey = 'web'): array
    {
        // 1. Если modelAlias указан, читаем поля напрямую из модели
        if ($modelAlias) {
            $modelClass = $this->getModelClassByAlias($modelAlias);
            if (!$modelClass) {
                throw new \Exception("Model alias not found: {$modelAlias}");
            }

            $modelFields = $this->getModelFields($modelClass);
        } else {
            // Пока модель не указана, используем старую логику (только JSON)
            $modelFields = [];
        }

        // 2. Загружаем JSON конфиг (если существует)
        $jsonConfig = $this->loadJsonConfig($pageKey);
        $jsonFields = $jsonConfig['fields'] ?? [];

        // 3. Мерджим: поля модели + переопределения из JSON
        $baseFields = $this->mergeModelWithJson($modelFields, $jsonFields);

        // 4. Применяем переопределения из БД
        $overrides = $this->loadDatabaseOverrides($pageKey, $contextKey);
        $finalFields = $this->applyDatabaseOverrides($baseFields, $overrides);

        // 5. Применяем лексикон к label и description
        $finalFields = $this->applyLexicon($finalFields);

        // 6. Загружаем и обрабатываем секции
        $sections = $this->processSections($jsonConfig['sections'] ?? []);

        return [
            'model_alias' => $modelAlias,
            'model_class' => $modelClass ?? null,
            'sections' => $sections,
            'fields' => $finalFields,
        ];
    }

    /**
     * Загрузить JSON конфиг (опционально, может не существовать)
     *
     * @param string $pageKey
     * @return array
     */
    protected function loadJsonConfig(string $pageKey): array
    {
        $configPath = MODX_CORE_PATH . 'components/minishop3/config/pages/' . $pageKey . '.json';

        if (!file_exists($configPath)) {
            return ['fields' => []];
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return ['fields' => []];
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Invalid JSON in {$configPath}: " . json_last_error_msg());
            return ['fields' => []];
        }

        return $config;
    }

    /**
     * Загрузить переопределения из БД
     *
     * @param string $pageKey
     * @param string $contextKey
     * @return array
     */
    protected function loadDatabaseOverrides(string $pageKey, string $contextKey): array
    {
        $overrides = [];

        $items = $this->modx->getIterator(msFieldConfigOverride::class, [
            'page_key' => $pageKey,
            'context_key' => $contextKey,
        ]);

        foreach ($items as $item) {
            $overrides[$item->get('field_name')] = [
                'hidden' => (bool)$item->get('hidden'),
                'sort_order' => (int)$item->get('sort_order'),
                'config' => $item->getConfig(),
            ];
        }

        return $overrides;
    }

    /**
     * Мерджить поля модели с JSON конфигом
     *
     * @param array $modelFields Поля из модели
     * @param array|object $jsonFields Поля из JSON (может быть объектом или массивом)
     * @return array
     */
    protected function mergeModelWithJson(array $modelFields, $jsonFields): array
    {
        // Преобразуем массив полей в ассоциативный массив по имени
        $fieldsMap = [];
        foreach ($modelFields as $field) {
            $fieldsMap[$field['name']] = $field;
        }

        // Если JSON содержит объект (новый формат), применяем переопределения
        if (is_array($jsonFields) && !isset($jsonFields[0])) {
            // Новый формат: { "price": { "xtype": "numberfield", "label": "Цена" } }
            foreach ($jsonFields as $fieldName => $override) {
                if (isset($fieldsMap[$fieldName])) {
                    $fieldsMap[$fieldName] = array_merge($fieldsMap[$fieldName], $override);
                }
            }
        } else {
            // Старый формат: массив полей
            // В этом случае просто добавляем поля из JSON, которых нет в модели
            foreach ($jsonFields as $jsonField) {
                $name = $jsonField['name'] ?? null;
                if ($name && !isset($fieldsMap[$name])) {
                    $fieldsMap[$name] = $jsonField;
                }
            }
        }

        return array_values($fieldsMap);
    }

    /**
     * Применить переопределения из БД
     *
     * @param array $fields
     * @param array $overrides
     * @return array
     */
    protected function applyDatabaseOverrides(array $fields, array $overrides): array
    {
        $result = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            // Применяем override из БД, если существует
            if (isset($overrides[$fieldName])) {
                $override = $overrides[$fieldName];

                // Пропускаем скрытые поля
                if ($override['hidden']) {
                    continue;
                }

                // Применяем config из БД
                if (!empty($override['config'])) {
                    $field = array_merge($field, $override['config']);
                }

                $field['sort_order'] = $override['sort_order'];
            } else {
                $field['sort_order'] = $field['sort_order'] ?? 0;
            }

            $result[] = $field;
        }

        // Сортируем по sort_order
        usort($result, function($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });

        return $result;
    }

    /**
     * Сохранить конфигурацию полей в БД
     *
     * @param string $pageKey
     * @param array $fields
     * @param string $contextKey
     * @return bool
     */
    public function saveFieldsConfig(string $pageKey, array $fields, string $contextKey = 'web'): bool
    {
        return $this->configManager->saveFieldsConfig($pageKey, $fields, $contextKey);
    }

    /**
     * Получить значение из лексикона с fallback логикой
     *
     * Логика:
     * 1. Попробовать загрузить для текущего языка админки
     * 2. Если не найдено - попробовать английский (en)
     * 3. Если не найдено - вернуть сам ключ
     *
     * @param string $key Ключ лексикона
     * @param string $namespace Namespace лексикона (по умолчанию: minishop3)
     * @param string $topic Topic лексикона (по умолчанию: default)
     * @return string
     */
    protected function getLexiconValue(string $key, string $namespace = 'minishop3', string $topic = 'default'): string
    {
        // Определяем текущий язык админки
        $currentLanguage = $this->modx->getOption('cultureKey', null, 'en');
        if (!empty($this->modx->cultureKey)) {
            $currentLanguage = $this->modx->cultureKey;
        }

        // Прямая загрузка лексикона из файла (обход кеша MODX)
        $lexiconPath = MODX_CORE_PATH . "components/{$namespace}/lexicon/{$currentLanguage}/{$topic}.inc.php";

        if (file_exists($lexiconPath)) {
            $_lang = [];
            include $lexiconPath;

            if (isset($_lang[$key])) {
                return $_lang[$key];
            }
        }

        // Если не найдено в текущем языке и это не английский - пробуем английский
        if ($currentLanguage !== 'en') {
            $lexiconPathEn = MODX_CORE_PATH . "components/{$namespace}/lexicon/en/{$topic}.inc.php";

            if (file_exists($lexiconPathEn)) {
                $_lang = [];
                include $lexiconPathEn;

                if (isset($_lang[$key])) {
                    return $_lang[$key];
                }
            }
        }

        // Если нигде не найдено - вернём сам ключ
        return $key;
    }

    /**
     * Применить лексикон к полям (преобразовать label_key и description_key в label и description)
     *
     * @param array $fields
     * @return array
     */
    public function applyLexicon(array $fields): array
    {
        foreach ($fields as &$field) {
            // Преобразуем label_key в label с fallback логикой
            if (isset($field['label_key'])) {
                $field['label'] = $this->getLexiconValue($field['label_key'], 'minishop3', 'product');
                // Если лексикон вернул ключ (не найден перевод), используем auto-generated label
                if ($field['label'] === $field['label_key']) {
                    $field['label'] = $this->generateLabel($field['name'] ?? '');
                }
                unset($field['label_key']);
            }

            // Преобразуем description_key в description с fallback логикой
            if (isset($field['description_key'])) {
                $field['description'] = $this->getLexiconValue($field['description_key'], 'minishop3', 'product');
                // Если лексикон вернул ключ (не найден перевод), оставляем пустым
                if ($field['description'] === $field['description_key']) {
                    $field['description'] = '';
                }
                unset($field['description_key']);
            }

            // Если нет ни label_key, ни label - генерируем
            if (!isset($field['label'])) {
                $field['label'] = $this->generateLabel($field['name'] ?? '');
            }

            // Если нет description - пустая строка
            if (!isset($field['description'])) {
                $field['description'] = '';
            }
        }

        return $fields;
    }

    /**
     * Обработать секции: применить лексикон к label
     *
     * @param array $sections
     * @return array
     */
    protected function processSections(array $sections): array
    {
        $this->modx->lexicon->load('minishop3:product');

        $result = [];

        foreach ($sections as $key => $section) {
            // Применяем лексикон к label секции
            if (isset($section['label_key'])) {
                $section['label'] = $this->modx->lexicon($section['label_key']);
                // Если лексикон не найден, используем ключ как есть
                if ($section['label'] === $section['label_key']) {
                    $section['label'] = ucfirst(str_replace('_', ' ', $key));
                }
                unset($section['label_key']);
            }

            // Добавляем ключ секции
            $section['key'] = $key;

            // Устанавливаем defaults
            $section['collapsed'] = $section['collapsed'] ?? false;
            $section['order'] = $section['order'] ?? 0;

            $result[$key] = $section;
        }

        // Сортируем по order
        uasort($result, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $result;
    }
}
