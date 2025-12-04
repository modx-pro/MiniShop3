<?php

namespace MiniShop3\Processors\Settings;

use MODX\Revolution\modX;
use MODX\Revolution\Processors\Processor;

/**
 * Processor for retrieving available classes for Delivery/Payment
 *
 * Automatically scans directories and finds all classes
 * inheriting from base MiniShop3 interfaces/classes.
 *
 * Supported types:
 * - delivery: Delivery classes (inherit DeliveryProviderInterface)
 * - payment: Payment classes (inherit PaymentProviderInterface)
 *
 * Scanned directories:
 * - core/components/minishop3/src/Controllers/{Type}/ (built-in classes)
 * - Can be extended via ms3_custom_classes_paths setting
 */
class GetClass extends Processor
{
    /**
     * Get list of available classes for specified type
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
     * Auto-discover delivery/payment classes
     *
     * @param string $type Type (delivery or payment)
     * @return array Array of classes [['name' => 'ClassName', 'class' => 'Full\Namespace\ClassName'], ...]
     */
    protected function discoverClasses(string $type): array
    {
        $result = [];

        $baseClass = $this->getBaseClass($type);
        if (!$baseClass) {
            return $result;
        }

        $builtInClasses = $this->scanDirectory($type);
        $result = array_merge($result, $builtInClasses);

        $result = array_values(array_unique($result, SORT_REGULAR));

        return $result;
    }

    /**
     * Get base class/interface for validation
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
     * Scan directory with MiniShop3 classes
     *
     * @param string $type
     * @return array
     */
    protected function scanDirectory(string $type): array
    {
        $result = [];

        $typeCapitalized = ucfirst(strtolower($type));
        $directory = MODX_CORE_PATH . "components/minishop3/src/Controllers/{$typeCapitalized}/";

        if (!is_dir($directory)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[GetClass] Directory not found: {$directory}"
            );
            return $result;
        }

        $files = glob($directory . '*.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');

            if (str_ends_with($className, 'Interface') || $className === ucfirst($type)) {
                continue;
            }

            $fullClassName = "\\MiniShop3\\Controllers\\{$typeCapitalized}\\{$className}";

            if (!class_exists($fullClassName)) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[GetClass] Class not found: {$fullClassName}"
                );
                continue;
            }

            $reflection = new \ReflectionClass($fullClassName);
            if ($reflection->isAbstract()) {
                continue;
            }

            $baseClass = $this->getBaseClass($type);
            if (!is_subclass_of($fullClassName, $baseClass) && !in_array($baseClass, class_implements($fullClassName) ?: [])) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[GetClass] Class {$fullClassName} does not implement {$baseClass}"
                );
                continue;
            }

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

