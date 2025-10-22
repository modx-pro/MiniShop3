<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 * Сервис для работы с конфигурацией полей (фасад над FieldConfigManager и ConfigManager)
 */
class ConfigService
{
    /** @var modX */
    protected $modx;

    /** @var FieldConfigManager */
    protected $fieldConfigManager;

    /** @var ConfigManager */
    protected $configManager;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->fieldConfigManager = new FieldConfigManager($modx);
        $this->configManager = new ConfigManager($modx);
    }

    /**
     * Получить конфигурацию полей страницы с примененными переопределениями
     *
     * @param string $pageKey Ключ страницы (product_data, product_gallery и т.д.)
     * @param string $contextKey Ключ контекста (по умолчанию: web)
     * @return array
     * @throws \Exception
     */
    public function getPageFields(string $pageKey, string $contextKey = 'web'): array
    {
        // Загружаем JSON конфиг, чтобы получить model_alias
        $jsonConfigPath = MODX_CORE_PATH . 'components/minishop3/config/pages/' . $pageKey . '.json';
        $modelAlias = null;

        if (file_exists($jsonConfigPath)) {
            $jsonContent = file_get_contents($jsonConfigPath);
            $jsonConfig = json_decode($jsonContent, true);
            $modelAlias = $jsonConfig['model_alias'] ?? null;
        }

        return $this->fieldConfigManager->getPageFieldsConfig($pageKey, $modelAlias);
    }

    /**
     * Получить ВСЕ доступные поля (включая скрытые) из таблицы ms3_product_fields
     *
     * @param string $pageKey Ключ страницы
     * @param string $contextKey Ключ контекста (по умолчанию: web)
     * @return array
     * @throws \Exception
     */
    public function getAllPageFields(string $pageKey, string $contextKey = 'web'): array
    {
        // Загружаем все поля из таблицы ms3_product_fields
        $query = $this->modx->newQuery('MiniShop3\\Model\\msProductField');
        $query->sortby('sort_order', 'ASC');

        $collection = $this->modx->getCollection('MiniShop3\\Model\\msProductField', $query);

        $fields = [];
        foreach ($collection as $field) {
            $config = $field->get('config');

            // xPDO 3 с phptype='json' может вернуть массив или строку
            if (is_string($config)) {
                $config = json_decode($config, true) ?: [];
            } elseif (!is_array($config)) {
                $config = [];
            }

            $fieldData = [
                'name' => $field->get('name'),
                'label' => $field->get('label'),
                'xtype' => $field->get('xtype'),
                'section' => $field->get('section'),
                'hidden' => !(bool)$field->get('visible'), // Преобразуем visible → hidden для фронтенда
                'required' => (bool)$field->get('required'),
                'sort_order' => (int)$field->get('sort_order'),
                'width' => (int)$field->get('width'),
                'description' => $field->get('description'),
                'is_system' => (bool)$field->get('is_system'),
                'is_default' => (bool)$field->get('is_default'),
            ];

            // Мержим дополнительные настройки из config JSON (decimalPrecision, inputValue, etc)
            if (is_array($config) && !empty($config)) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return [
            'fields' => $fields
        ];
    }

    /**
     * Сохранить массовые изменения полей в таблицу ms3_product_fields
     *
     * @param string $pageKey Ключ страницы
     * @param array $fields Массив полей с настройками
     * @param string $contextKey Ключ контекста (по умолчанию: web)
     * @return bool
     */
    public function saveFieldsConfig(string $pageKey, array $fields, string $contextKey = 'web'): bool
    {
        try {
            foreach ($fields as $fieldData) {
                $fieldName = $fieldData['name'] ?? null;

                if (!$fieldName) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                        '[ConfigService] Skipping field without name');
                    continue;
                }

                // Находим поле в таблице
                $field = $this->modx->getObject('MiniShop3\\Model\\msProductField', [
                    'name' => $fieldName
                ]);

                if (!$field) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                        "[ConfigService] Field not found: {$fieldName}");
                    continue;
                }

                // Обновляем основные параметры
                if (isset($fieldData['hidden'])) {
                    // Преобразуем hidden → visible для БД
                    $field->set('visible', !(bool)$fieldData['hidden']);
                }

                if (isset($fieldData['sort_order'])) {
                    $field->set('sort_order', (int)$fieldData['sort_order']);
                }

                if (isset($fieldData['section'])) {
                    $field->set('section', $fieldData['section']);
                }

                if (isset($fieldData['xtype'])) {
                    $field->set('xtype', $fieldData['xtype']);
                }

                if (isset($fieldData['label'])) {
                    $field->set('label', $fieldData['label']);
                }

                if (isset($fieldData['description'])) {
                    $field->set('description', $fieldData['description']);
                }

                if (isset($fieldData['width'])) {
                    $field->set('width', (int)$fieldData['width']);
                }

                if (isset($fieldData['required'])) {
                    $field->set('required', (bool)$fieldData['required']);
                }

                // Обновляем JSON config (дополнительные параметры)
                $config = [];
                $configKeys = ['decimalPrecision', 'inputValue', 'allowNegative', 'placeholder', 'allowBlank'];
                foreach ($configKeys as $key) {
                    if (isset($fieldData[$key])) {
                        $config[$key] = $fieldData[$key];
                    }
                }

                if (!empty($config)) {
                    $field->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));
                }

                if (!$field->save()) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                        "[ConfigService] Failed to save field: {$fieldName}");
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ConfigService] Error saving fields: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить переопределение для конкретного поля
     *
     * @param string $pageKey Ключ страницы
     * @param string $fieldName Имя поля
     * @param string $contextKey Ключ контекста (по умолчанию: web)
     * @return bool
     */
    public function removeFieldOverride(string $pageKey, string $fieldName, string $contextKey = 'web'): bool
    {
        return $this->configManager->removeFieldOverride($pageKey, $fieldName, $contextKey);
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
     * Получить секции страницы из БД с переводами из лексикона
     *
     * @param string $pageKey Ключ страницы (product_data и т.д.)
     * @param string $contextKey Ключ контекста (не используется пока, для будущего расширения)
     * @return array
     */
    public function getSections(string $pageKey, string $contextKey = 'web'): array
    {
        $sections = [];

        $items = $this->modx->getIterator('MiniShop3\\Model\\msPageSection', [
            'page_key' => $pageKey,
        ]);

        foreach ($items as $item) {
            $configRaw = $item->get('config');

            // xPDO 3 с phptype='json' автоматически десериализует JSON в массив
            $config = is_array($configRaw) ? $configRaw : (is_string($configRaw) ? json_decode($configRaw, true) : []);

            $section = [
                'key' => $item->get('section_key'),
                'lexicon_key' => $config['lexicon_key'] ?? '',
                'sort_order' => (int)$item->get('sort_order'),
                'hidden' => (bool)$item->get('hidden'),
                'is_default' => (bool)$item->get('is_default'),
            ];

            // Переводим lexicon_key или используем прямой label
            if (!empty($section['lexicon_key'])) {
                $section['label'] = $this->getLexiconValue($section['lexicon_key'], 'minishop3', 'product');
            } else if (isset($config['label'])) {
                $section['label'] = $config['label'];
            } else {
                $section['label'] = $item->get('section_key');
            }

            $sections[] = $section;
        }

        // Сортируем по sort_order
        usort($sections, function($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });

        return $sections;
    }


    /**
     * Сохранить секции (порядок, видимость)
     *
     * @param string $pageKey
     * @param array $sections Массив секций с настройками
     * @return bool
     */
    public function saveSections(string $pageKey, array $sections): bool
    {
        try {
            foreach ($sections as $index => $section) {
                $sectionKey = $section['key'] ?? $section['section_key'] ?? null;

                if (!$sectionKey) {
                    continue;
                }

                $isDefault = $section['is_default'] ?? false;
                $hidden = isset($section['hidden']) ? (bool)$section['hidden'] : false;
                $sortOrder = $index; // Порядок определяется позицией в массиве

                // Ищем существующую запись
                $override = $this->modx->getObject('MiniShop3\\Model\\msPageSection', [
                    'page_key' => $pageKey,
                    'section_key' => $sectionKey,
                ]);

                if (!$override) {
                    // Создаём новую запись
                    $override = $this->modx->newObject('MiniShop3\\Model\\msPageSection');
                    $override->set('page_key', $pageKey);
                    $override->set('section_key', $sectionKey);
                    $override->set('is_default', $isDefault);
                }

                // Обновляем параметры
                $override->set('hidden', $hidden);
                $override->set('sort_order', $sortOrder);

                // Сохраняем дополнительную конфигурацию в JSON
                $config = [];
                if (isset($section['lexicon_key'])) {
                    $config['lexicon_key'] = $section['lexicon_key'];
                }
                if (isset($section['label'])) {
                    $config['label'] = $section['label'];
                }

                if (!empty($config)) {
                    $override->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));
                }

                if (!$override->save()) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                        "[ConfigService] Failed to save section override: {$sectionKey}");
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ConfigService] Error saving sections: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить секцию из БД
     *
     * @param string $pageKey
     * @param string $sectionKey
     * @return bool
     */
    public function deleteSection(string $pageKey, string $sectionKey): bool
    {
        try {
            $section = $this->modx->getObject('MiniShop3\\Model\\msPageSection', [
                'page_key' => $pageKey,
                'section_key' => $sectionKey,
            ]);

            if (!$section) {
                return true; // Секции не существует
            }

            return $section->remove();
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ConfigService] Error deleting section: " . $e->getMessage());
            return false;
        }
    }
}
