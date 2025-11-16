<?php

namespace MiniShop3;

use MODX\Revolution\modX;

/**
 * Service Registry - централизованная регистрация сервисов MiniShop3
 *
 * Управляет регистрацией всех сервисов компонента в DI контейнере MODX.
 * Поддерживает переопределение сервисов пользователем через конфигурационные файлы.
 *
 * Приоритеты загрузки:
 * 1. Дефолтные классы компонента (встроенные)
 * 2. Пользовательский конфиг (core/config/ms3.services.php)
 * 3. Конфиги аддонов (core/config/ms3.services.d/*.php)
 *
 * Каждый следующий уровень перезаписывает предыдущий.
 *
 * Архитектура для аддонов:
 * - Каждый аддон создаёт свой файл в ms3.services.d/
 * - Файлы загружаются в алфавитном порядке
 * - Нет конфликтов при установке нескольких аддонов
 *
 * Особенности:
 * - Валидация классов (существование, интерфейсы, наследование)
 * - Автоматический fallback на дефолтные классы при ошибке
 * - Логирование всех операций для отладки
 * - Lazy loading - сервисы создаются только при обращении
 *
 * @package MiniShop3
 */
class ServiceRegistry
{
    /** @var modX */
    protected modX $modx;

    /**
     * Дефолтные сервисы (встроенные в компонент)
     *
     * Формат: [service_key => [class, interface]]
     *
     * @var array
     */
    protected array $defaultServices = [
        'ms3_config_manager' => [
            'class' => \MiniShop3\Services\ConfigManager::class,
            'interface' => null, // TODO: создать интерфейс
        ],
        'ms3_field_config_manager' => [
            'class' => \MiniShop3\Services\FieldConfigManager::class,
            'interface' => null,
        ],
        'ms3_config_service' => [
            'class' => \MiniShop3\Services\ConfigService::class,
            'interface' => null,
        ],
        'ms3_product_data_service' => [
            'class' => \MiniShop3\Services\Product\ProductDataService::class,
            'interface' => null,
        ],
        'ms3_product_image' => [
            'class' => \MiniShop3\Services\Product\ProductImageService::class,
            'interface' => null,
        ],
        'ms3_vendor_service' => [
            'class' => \MiniShop3\Services\Vendor\VendorService::class,
            'interface' => null,
        ],
        'ms3_delivery_service' => [
            'class' => \MiniShop3\Services\Delivery\DeliveryService::class,
            'interface' => null,
        ],
        'ms3_payment_service' => [
            'class' => \MiniShop3\Services\Payment\PaymentService::class,
            'interface' => null,
        ],
        'ms3_order_service' => [
            'class' => \MiniShop3\Services\Order\OrderService::class,
            'interface' => null,
        ],
        'ms3_token_service' => [
            'class' => \MiniShop3\Services\TokenService::class,
            'interface' => null,
        ],
        'ms3_category_service' => [
            'class' => \MiniShop3\Services\Category\CategoryService::class,
            'interface' => null,
        ],
        'ms3_category_option_service' => [
            'class' => \MiniShop3\Services\Category\CategoryOptionService::class,
            'interface' => null,
        ],
        'ms3_image' => [
            'class' => \MiniShop3\Services\ImageService::class,
            'interface' => null,
        ],
        'ms3_option_service' => [
            'class' => \MiniShop3\Services\Option\OptionService::class,
            'interface' => null,
        ],
        'ms3_cart' => [
            'class' => \MiniShop3\Controllers\Cart\Cart::class,
            'interface' => null,
        ],
        'ms3_order' => [
            'class' => \MiniShop3\Controllers\Order\Order::class,
            'interface' => null,
        ],
        'ms3_customer' => [
            'class' => \MiniShop3\Controllers\Customer\Customer::class,
            'interface' => null,
        ],
        // Сервисы аутентификации и регистрации клиентов
        'ms3_auth_manager' => [
            'class' => \MiniShop3\Services\Customer\AuthManager::class,
            'interface' => null,
        ],
        'ms3_register_service' => [
            'class' => \MiniShop3\Services\Customer\RegisterService::class,
            'interface' => null,
        ],
        'ms3_email_verification_service' => [
            'class' => \MiniShop3\Services\Customer\EmailVerificationService::class,
            'interface' => null,
        ],
        'ms3_sms_verification_service' => [
            'class' => \MiniShop3\Services\Customer\SmsVerificationService::class,
            'interface' => null,
        ],
        'ms3_rate_limiter' => [
            'class' => \MiniShop3\Services\Customer\RateLimiter::class,
            'interface' => null,
        ],
    ];

    /**
     * Пользовательские переопределения (загружаются из конфига)
     *
     * @var array
     */
    protected array $customServices = [];

    /**
     * Конструктор
     *
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadCustomServices();
    }

    /**
     * Загрузка пользовательских переопределений из конфигов
     *
     * Загрузка происходит в порядке приоритета:
     * 1. core/config/ms3.services.php (пользовательский конфиг)
     * 2. core/config/ms3.services.d/*.php (конфиги аддонов, в алфавитном порядке)
     *
     * Каждый следующий файл перезаписывает предыдущие значения.
     *
     * @return void
     */
    protected function loadCustomServices(): void
    {
        // 1. Загрузка основного пользовательского конфига
        $this->loadMainConfig();

        // 2. Загрузка конфигов аддонов из ms3.services.d/
        $this->loadAddonConfigs();
    }

    /**
     * Загрузка основного пользовательского конфига
     *
     * @return void
     */
    protected function loadMainConfig(): void
    {
        $customConfigPath = $this->modx->getOption(
            'ms3_services_config',
            null,
            MODX_CORE_PATH . 'config/ms3.services.php'
        );

        if (!file_exists($customConfigPath)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 ServiceRegistry] Custom config not found: {$customConfigPath}"
            );
            return;
        }

        try {
            $config = require $customConfigPath;

            if (!is_array($config)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[MiniShop3 ServiceRegistry] Custom config must return array: {$customConfigPath}"
                );
                return;
            }

            $this->customServices = array_merge($this->customServices, $config);

            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                sprintf(
                    '[MiniShop3 ServiceRegistry] Loaded %d service(s) from main config: %s',
                    count($config),
                    basename($customConfigPath)
                )
            );
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[MiniShop3 ServiceRegistry] Error loading main config: {$e->getMessage()}"
            );
        }
    }

    /**
     * Загрузка конфигов аддонов из директории ms3.services.d/
     *
     * Файлы загружаются в алфавитном порядке.
     * Это позволяет управлять приоритетами через имена файлов:
     * - 01-base.php
     * - 50-mycartaddon.php
     * - 99-override.php
     *
     * @return void
     */
    protected function loadAddonConfigs(): void
    {
        $addonsDir = $this->modx->getOption(
            'ms3_services_addons_dir',
            null,
            MODX_CORE_PATH . 'config/ms3.services.d/'
        );

        if (!is_dir($addonsDir)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 ServiceRegistry] Addons directory not found: {$addonsDir}"
            );
            return;
        }

        // Получаем все PHP файлы из директории
        $files = glob($addonsDir . '*.php');
        if (empty($files)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 ServiceRegistry] No addon configs found in: {$addonsDir}"
            );
            return;
        }

        // Сортируем в алфавитном порядке
        sort($files);

        $loadedAddons = 0;
        foreach ($files as $file) {
            try {
                $config = require $file;

                if (!is_array($config)) {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[MiniShop3 ServiceRegistry] Addon config must return array: " . basename($file)
                    );
                    continue;
                }

                // Объединяем с уже загруженными сервисами
                $this->customServices = array_merge($this->customServices, $config);

                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    sprintf(
                        '[MiniShop3 ServiceRegistry] Loaded %d service(s) from addon: %s',
                        count($config),
                        basename($file)
                    )
                );

                $loadedAddons++;
            } catch (\Exception $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[MiniShop3 ServiceRegistry] Error loading addon config " . basename($file) . ": {$e->getMessage()}"
                );
            }
        }

        if ($loadedAddons > 0) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 ServiceRegistry] Total addon configs loaded: {$loadedAddons}"
            );
        }
    }

    /**
     * Регистрация всех сервисов в DI контейнере
     *
     * Объединяет дефолтные и пользовательские сервисы.
     * Пользовательские переопределения имеют приоритет.
     *
     * @return void
     */
    public function register(): void
    {
        // Объединяем дефолтные + кастомные (кастомные перезаписывают дефолтные)
        $services = array_merge($this->defaultServices, $this->customServices);

        $registered = 0;
        $skipped = 0;

        foreach ($services as $serviceKey => $config) {
            if ($this->registerService($serviceKey, $config)) {
                $registered++;
            } else {
                $skipped++;
            }
        }

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            sprintf(
                '[MiniShop3 ServiceRegistry] Registered %d service(s), skipped %d (already registered)',
                $registered,
                $skipped
            )
        );
    }

    /**
     * Регистрация одного сервиса
     *
     * @param string $serviceKey Ключ сервиса в DI контейнере
     * @param array $config Конфигурация сервиса [class, interface]
     * @return bool True если зарегистрирован, false если уже существует
     */
    protected function registerService(string $serviceKey, array $config): bool
    {
        // Проверяем не зарегистрирован ли уже
        if ($this->modx->services->has($serviceKey)) {
            return false;
        }

        $className = $config['class'];
        $requiredInterface = $config['interface'] ?? null;

        // Получаем fallback класс из дефолтных сервисов
        $fallbackClass = $this->defaultServices[$serviceKey]['class'] ?? $className;

        // Валидация класса
        $validatedClass = $this->validateClass($className, $fallbackClass, $requiredInterface);

        // Регистрируем в DI контейнере с lazy loading
        $modx = $this->modx;

        // Для Cart, Order, Customer нужен MiniShop3 в конструкторе, для остальных - modX
        if (in_array($serviceKey, ['ms3_cart', 'ms3_order', 'ms3_customer'])) {
            $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                return new $validatedClass($ms3);
            });
        } else {
            $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                return new $validatedClass($modx);
            });
        }

        return true;
    }

    /**
     * Валидация подменяемого класса
     *
     * Проверяет:
     * - Существование класса
     * - Реализацию требуемого интерфейса (если указан)
     * - Наследование от базового класса
     *
     * При ошибках логирует и возвращает fallback класс.
     *
     * @param string $className Проверяемый класс
     * @param string $fallbackClass Класс по умолчанию (если валидация не прошла)
     * @param string|null $requiredInterface Требуемый интерфейс (опционально)
     * @return string Валидированный класс или fallback
     */
    protected function validateClass(
        string $className,
        string $fallbackClass,
        ?string $requiredInterface = null
    ): string {
        // Если это дефолтный класс - пропускаем валидацию
        if ($className === $fallbackClass) {
            return $className;
        }

        // Проверка существования класса
        if (!class_exists($className)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[MiniShop3 ServiceRegistry] Class '{$className}' not found, using fallback: {$fallbackClass}"
            );
            return $fallbackClass;
        }

        // Проверка реализации интерфейса (если указан)
        if ($requiredInterface) {
            $interfaces = class_implements($className);
            if (!in_array($requiredInterface, $interfaces ?: [])) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[MiniShop3 ServiceRegistry] Class '{$className}' must implement {$requiredInterface}, using fallback"
                );
                return $fallbackClass;
            }
        }

        // Проверка наследования от базового класса
        if (!is_subclass_of($className, $fallbackClass)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[MiniShop3 ServiceRegistry] Class '{$className}' must extend {$fallbackClass}, using fallback"
            );
            return $fallbackClass;
        }

        // Всё ок - класс валиден (логируем только если это кастомный класс)
        if ($className !== $fallbackClass) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3 ServiceRegistry] Using custom class: {$className}"
            );
        }

        return $className;
    }

    /**
     * Получить список всех зарегистрированных сервисов
     *
     * @return array Массив ключей сервисов
     */
    public function getRegisteredServices(): array
    {
        return array_keys(array_merge($this->defaultServices, $this->customServices));
    }

    /**
     * Получить конфигурацию конкретного сервиса
     *
     * @param string $serviceKey Ключ сервиса
     * @return array|null Конфигурация или null если не найден
     */
    public function getServiceConfig(string $serviceKey): ?array
    {
        $services = array_merge($this->defaultServices, $this->customServices);
        return $services[$serviceKey] ?? null;
    }

    /**
     * Проверить переопределён ли сервис пользователем
     *
     * @param string $serviceKey Ключ сервиса
     * @return bool
     */
    public function isCustomService(string $serviceKey): bool
    {
        return isset($this->customServices[$serviceKey]);
    }
}
