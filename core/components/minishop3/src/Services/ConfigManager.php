<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;
use MiniShop3\Model\msFieldConfigOverride;

/**
 * Сервис для работы с конфигурацией полей
 */
class ConfigManager
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получить конфигурацию полей для указанной страницы с учетом переопределений
     *
     * @param string $pageKey Ключ страницы (например: product_data)
     * @param string $contextKey Ключ контекста (по умолчанию: web)
     * @return array
     * @throws \Exception
     */
    public function getPageFieldsConfig(string $pageKey, string $contextKey = 'web'): array
    {
        // 1. Загружаем базовую конфигурацию из JSON
        $baseConfig = $this->loadBaseConfig($pageKey);

        // 2. Загружаем переопределения из БД
        $overrides = $this->loadOverrides($pageKey, $contextKey);

        // 3. Применяем переопределения
        $fields = $this->applyOverrides($baseConfig['fields'] ?? [], $overrides);

        return [
            'fields' => $fields,
        ];
    }

    /**
     * Загрузить базовую конфигурацию из JSON файла
     *
     * @param string $pageKey
     * @return array
     * @throws \Exception
     */
    protected function loadBaseConfig(string $pageKey): array
    {
        $configPath = MODX_CORE_PATH . 'components/minishop3/config/pages/' . $pageKey . '.json';

        if (!file_exists($configPath)) {
            throw new \Exception("Config file not found: {$configPath}");
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            throw new \Exception("Failed to read config file: {$configPath}");
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in config file: {$configPath}. Error: " . json_last_error_msg());
        }

        return $config;
    }

    /**
     * Загрузить переопределения из БД
     *
     * @param string $pageKey
     * @param string $contextKey
     * @return array Массив переопределений, ключ - field_name
     */
    protected function loadOverrides(string $pageKey, string $contextKey): array
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
     * Применить переопределения к базовой конфигурации
     *
     * @param array $fields Базовые поля
     * @param array $overrides Переопределения
     * @return array Поля с примененными переопределениями
     */
    protected function applyOverrides(array $fields, array $overrides): array
    {
        $result = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            // Проверяем, есть ли переопределение для этого поля
            if (isset($overrides[$fieldName])) {
                $override = $overrides[$fieldName];

                // Пропускаем скрытые поля
                if ($override['hidden']) {
                    continue;
                }

                // Применяем переопределения из config (label, xtype, etc)
                if (!empty($override['config'])) {
                    $field = array_merge($field, $override['config']);
                }

                // Сохраняем sort_order для последующей сортировки
                $field['sort_order'] = $override['sort_order'];
            } else {
                // Если нет переопределения, используем порядок из базовой конфигурации
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
     * Получить все доступные поля (включая скрытые) с учетом оверрайдов
     *
     * @param string $pageKey Ключ страницы
     * @param string $contextKey Ключ контекста
     * @return array
     * @throws \Exception
     */
    public function getAllFields(string $pageKey, string $contextKey = 'web'): array
    {
        // Загружаем базовую конфигурацию
        $baseConfig = $this->loadBaseConfig($pageKey);
        $fields = $baseConfig['fields'] ?? [];

        // Загружаем оверрайды из БД
        $overrides = $this->loadOverrides($pageKey, $contextKey);

        // Применяем оверрайды к полям (но НЕ фильтруем скрытые)
        $result = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            // Если есть оверрайд для этого поля
            if (isset($overrides[$fieldName])) {
                $override = $overrides[$fieldName];

                // Устанавливаем значение hidden из оверрайда
                $field['hidden'] = $override['hidden'];
                $field['sort_order'] = $override['sort_order'];

                // Применяем переопределения из config (label, xtype, etc)
                if (!empty($override['config'])) {
                    $field = array_merge($field, $override['config']);
                }
            } else {
                // Если нет оверрайда, поле видимо по умолчанию
                $field['hidden'] = false;
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
     * Сохранить переопределение для поля
     *
     * @param string $pageKey Ключ страницы
     * @param string $fieldName Имя поля
     * @param array $override Данные переопределения
     * @param string $contextKey Ключ контекста
     * @return bool
     */
    public function saveFieldOverride(string $pageKey, string $fieldName, array $override, string $contextKey = 'web'): bool
    {
        // Ищем существующее переопределение
        $existing = $this->modx->getObject(msFieldConfigOverride::class, [
            'page_key' => $pageKey,
            'field_name' => $fieldName,
            'context_key' => $contextKey,
        ]);

        if (!$existing) {
            $existing = $this->modx->newObject(msFieldConfigOverride::class);
            $existing->set('page_key', $pageKey);
            $existing->set('field_name', $fieldName);
            $existing->set('context_key', $contextKey);
        }

        // Устанавливаем значения
        if (isset($override['hidden'])) {
            $existing->set('hidden', (bool)$override['hidden']);
        }

        if (isset($override['sort_order'])) {
            $existing->set('sort_order', (int)$override['sort_order']);
        }

        if (isset($override['config']) && is_array($override['config'])) {
            $existing->setConfig($override['config']);
        }

        return $existing->save();
    }

    /**
     * Удалить переопределение для поля
     *
     * @param string $pageKey Ключ страницы
     * @param string $fieldName Имя поля
     * @param string $contextKey Ключ контекста
     * @return bool
     */
    public function removeFieldOverride(string $pageKey, string $fieldName, string $contextKey = 'web'): bool
    {
        $existing = $this->modx->getObject(msFieldConfigOverride::class, [
            'page_key' => $pageKey,
            'field_name' => $fieldName,
            'context_key' => $contextKey,
        ]);

        if ($existing) {
            return $existing->remove();
        }

        return true;
    }

    /**
     * Сохранить массовые переопределения (например, при сортировке)
     *
     * @param string $pageKey Ключ страницы
     * @param array $fields Массив полей с переопределениями
     * @param string $contextKey Ключ контекста
     * @return bool
     */
    public function saveFieldsConfig(string $pageKey, array $fields, string $contextKey = 'web'): bool
    {
        $success = true;

        foreach ($fields as $index => $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            $override = [
                'sort_order' => $index,
                'hidden' => $field['hidden'] ?? false,
                'config' => [],
            ];

            // Собираем дополнительные переопределения
            $configKeys = ['label', 'xtype', 'description', 'width'];
            foreach ($configKeys as $key) {
                if (isset($field[$key])) {
                    $override['config'][$key] = $field[$key];
                }
            }

            if (!$this->saveFieldOverride($pageKey, $fieldName, $override, $contextKey)) {
                $success = false;
            }
        }

        return $success;
    }
}
