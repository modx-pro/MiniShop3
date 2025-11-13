<?php

namespace MiniShop3\Processors\Settings;

use MODX\Revolution\modX;
use MODX\Revolution\Processors\Processor;

/**
 * Процессор получения списка доступных классов для Delivery/Payment
 *
 * Автоматически сканирует директории и находит все классы,
 * наследующие базовые интерфейсы/классы MiniShop3.
 *
 * Поддерживаемые типы:
 * - delivery: Классы доставки (наследуют DeliveryProviderInterface)
 * - payment: Классы оплаты (наследуют PaymentProviderInterface)
 *
 * Сканируемые директории:
 * - core/components/minishop3/src/Controllers/{Type}/ (встроенные классы)
 * - Можно расширить через настройку ms3_custom_classes_paths
 */
class GetClass extends Processor
{
    /**
     * Получить список доступных классов для указанного типа
     *
     * @return string
     */
    public function process()
    {
        $type = $this->getProperty('type');

        if (empty($type)) {
            return $this->failure('Type parameter is required');
        }

        $classes = $this->discoverClasses($type);

        return $this->outputArray($classes);
    }

    /**
     * Автоматическое обнаружение классов доставки/оплаты
     *
     * @param string $type Тип (delivery или payment)
     * @return array Массив классов [['name' => 'ClassName', 'class' => 'Full\Namespace\ClassName'], ...]
     */
    protected function discoverClasses(string $type): array
    {
        $result = [];

        // Определяем базовый класс/интерфейс для валидации
        $baseClass = $this->getBaseClass($type);
        if (!$baseClass) {
            return $result;
        }

        // Сканируем встроенные классы MiniShop3
        $builtInClasses = $this->scanDirectory($type);
        $result = array_merge($result, $builtInClasses);

        // TODO: Можно добавить сканирование кастомных директорий через настройку
        // $customPaths = $this->modx->getOption('ms3_custom_classes_paths', null, []);
        // foreach ($customPaths as $path) {
        //     $customClasses = $this->scanCustomDirectory($path, $type, $baseClass);
        //     $result = array_merge($result, $customClasses);
        // }

        // Убираем дубликаты по полному имени класса
        $result = array_values(array_unique($result, SORT_REGULAR));

        return $result;
    }

    /**
     * Получить базовый класс/интерфейс для валидации
     *
     * @param string $type
     * @return string|null
     */
    protected function getBaseClass(string $type): ?string
    {
        $map = [
            'delivery' => \MiniShop3\Controllers\Delivery\DeliveryProviderInterface::class,
            'payment' => \MiniShop3\Controllers\Payment\PaymentProviderInterface::class,
        ];

        return $map[$type] ?? null;
    }

    /**
     * Сканирование директории с классами MiniShop3
     *
     * @param string $type
     * @return array
     */
    protected function scanDirectory(string $type): array
    {
        $result = [];

        // Определяем директорию для сканирования
        $typeCapitalized = ucfirst(strtolower($type));
        $directory = MODX_CORE_PATH . "components/minishop3/src/Controllers/{$typeCapitalized}/";

        if (!is_dir($directory)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[GetClass] Directory not found: {$directory}"
            );
            return $result;
        }

        // Сканируем PHP файлы
        $files = glob($directory . '*.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');

            // Пропускаем интерфейсы и абстрактные классы
            if (str_ends_with($className, 'Interface') || $className === ucfirst($type)) {
                continue;
            }

            $fullClassName = "\\MiniShop3\\Controllers\\{$typeCapitalized}\\{$className}";

            // Проверяем существование класса
            if (!class_exists($fullClassName)) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[GetClass] Class not found: {$fullClassName}"
                );
                continue;
            }

            // Проверяем наследование (для не-абстрактных классов)
            $reflection = new \ReflectionClass($fullClassName);
            if ($reflection->isAbstract()) {
                continue;
            }

            // Проверяем реализацию интерфейса
            $baseClass = $this->getBaseClass($type);
            if (!is_subclass_of($fullClassName, $baseClass) && !in_array($baseClass, class_implements($fullClassName) ?: [])) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[GetClass] Class {$fullClassName} does not implement {$baseClass}"
                );
                continue;
            }

            // Добавляем в результат
            $result[] = [
                'name' => $className,
                'class' => $fullClassName,
            ];

            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[GetClass] Discovered {$type} class: {$fullClassName}"
            );
        }

        return $result;
    }
}

