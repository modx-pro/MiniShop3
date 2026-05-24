<?php

namespace MiniShop3;

use MODX\Revolution\modX;

/**
 * Service Registry - centralized MiniShop3 service registration
 *
 * Manages registration of all component services in MODX DI container.
 * Supports user service overrides via configuration files.
 *
 * Loading priorities:
 * 1. Component default classes (built-in)
 * 2. User config (core/config/ms3.services.php)
 * 3. Addon configs (core/config/ms3.services.d/*.php)
 *
 * Each next level overrides the previous one.
 *
 * Architecture for addons:
 * - Each addon creates its file in ms3.services.d/
 * - Files are loaded in alphabetical order
 * - No conflicts when installing multiple addons
 *
 * Features:
 * - Class validation (existence, interfaces, inheritance)
 * - Automatic fallback to default classes on error
 * - All operations logging for debugging
 * - Lazy loading - services are created only on access
 *
 * @package MiniShop3
 */
class ServiceRegistry
{
    /** @var modX */
    protected modX $modx;

    /**
     * Default services (built into component)
     *
     * Format: [service_key => [class, interface]]
     *
     * @var array
     */
    protected array $defaultServices = [
        'ms3_config_manager' => [
            'class' => \MiniShop3\Services\ConfigManager::class,
            'interface' => null,
        ],
        'ms3_field_config_manager' => [
            'class' => \MiniShop3\Services\FieldConfigManager::class,
            'interface' => null,
        ],
        'ms3_config_service' => [
            'class' => \MiniShop3\Services\ConfigService::class,
            'interface' => null,
        ],
        'ms3_product_service' => [
            'class' => \MiniShop3\Services\Product\ProductService::class,
            'interface' => null,
        ],
        'ms3_product_data_service' => [
            'class' => \MiniShop3\Services\Product\ProductDataService::class,
            'interface' => null,
        ],
        'ms3_repeater_field' => [
            'class' => \MiniShop3\Services\ExtraFields\RepeaterFieldService::class,
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
        // Order workflow services (used by Order controller)
        // All services can be overridden via ms3.services.php config
        'ms3_order_draft_manager' => [
            'class' => \MiniShop3\Services\Order\OrderDraftManager::class,
            'interface' => null,
        ],
        'ms3_order_cost_calculator' => [
            'class' => \MiniShop3\Services\Order\OrderCostCalculator::class,
            'interface' => null,
        ],
        'ms3_order_field_manager' => [
            'class' => \MiniShop3\Services\Order\OrderFieldManager::class,
            'interface' => null,
        ],
        'ms3_order_address_manager' => [
            'class' => \MiniShop3\Services\Order\OrderAddressManager::class,
            'interface' => null,
        ],
        'ms3_order_user_resolver' => [
            'class' => \MiniShop3\Services\Order\OrderUserResolver::class,
            'interface' => null,
        ],
        'ms3_order_submit_handler' => [
            'class' => \MiniShop3\Services\Order\OrderSubmitHandler::class,
            'interface' => null,
        ],
        'ms3_order_log' => [
            'class' => \MiniShop3\Services\Order\OrderLogService::class,
            'interface' => null,
        ],
        'ms3_order_status' => [
            'class' => \MiniShop3\Services\Order\OrderStatusService::class,
            'interface' => null,
        ],
        'ms3_order_finalize' => [
            'class' => \MiniShop3\Services\Order\OrderFinalizeService::class,
            'interface' => null,
        ],
        // Cart services
        'ms3_cart_item_manager' => [
            'class' => \MiniShop3\Services\Cart\CartItemManager::class,
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
        'ms3_customer_address_manager' => [
            'class' => \MiniShop3\Services\Customer\CustomerAddressManager::class,
            'interface' => null,
        ],
        'ms3_grid_config' => [
            'class' => \MiniShop3\Services\GridConfigService::class,
            'interface' => null,
        ],
        'ms3_filter_config' => [
            'class' => \MiniShop3\Services\FilterConfigManager::class,
            'interface' => null,
        ],
        // Notification Center
        'ms3_notifications' => [
            'class' => \MiniShop3\Notifications\NotificationManager::class,
            'interface' => null,
        ],
        'ms3_notification_config' => [
            'class' => \MiniShop3\Services\Notification\NotificationConfigService::class,
            'interface' => null,
        ],
        // Customer services
        'ms3_customer_duplicate_checker' => [
            'class' => \MiniShop3\Services\CustomerDuplicateChecker::class,
            'interface' => null,
        ],
        'ms3_customer_factory' => [
            'class' => \MiniShop3\Services\CustomerFactory::class,
            'interface' => null,
        ],
    ];

    /**
     * User overrides (loaded from config)
     *
     * @var array
     */
    protected array $customServices = [];

    /**
     * Constructor
     *
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadCustomServices();
    }

    /**
     * Load user overrides from configs
     *
     * Loading happens in priority order:
     * 1. core/config/ms3.services.php (user config)
     * 2. core/config/ms3.services.d/*.php (addon configs, in alphabetical order)
     *
     * Each next file overwrites previous values.
     *
     * @return void
     */
    protected function loadCustomServices(): void
    {
        $this->loadMainConfig();

        $this->loadAddonConfigs();
    }

    /**
     * Load main user config
     *
     * @return void
     */
    protected function loadMainConfig(): void
    {
        $defaultConfigPath = MODX_CORE_PATH . 'config/ms3.services.php';
        $configuredPath = $this->modx->getOption('ms3_services_config');
        $customConfigPath = $configuredPath ?: $defaultConfigPath;

        if (!file_exists($customConfigPath)) {
            if (!empty($configuredPath)) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[MiniShop3 ServiceRegistry] Custom config not found: {$customConfigPath}"
                );
            }
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
     * Load addon configs from ms3.services.d/ directory
     *
     * Files are loaded in alphabetical order.
     * This allows priority management via file names:
     * - 01-base.php
     * - 50-mycartaddon.php
     * - 99-override.php
     *
     * @return void
     */
    protected function loadAddonConfigs(): void
    {
        $defaultAddonsDir = MODX_CORE_PATH . 'config/ms3.services.d/';
        $configuredAddonsDir = $this->modx->getOption('ms3_services_addons_dir');
        $addonsDir = $configuredAddonsDir ?: $defaultAddonsDir;

        if (!is_dir($addonsDir)) {
            if (!empty($configuredAddonsDir)) {
                $this->modx->log(
                    modX::LOG_LEVEL_DEBUG,
                    "[MiniShop3 ServiceRegistry] Addons directory not found: {$addonsDir}"
                );
            }
            return;
        }

        $files = glob($addonsDir . '*.php');
        if (empty($files)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 ServiceRegistry] No addon configs found in: {$addonsDir}"
            );
            return;
        }

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
     * Register all services in DI container
     *
     * Merges default and custom services.
     * Custom overrides have priority.
     *
     * @return void
     */
    public function register(): void
    {
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
     * Register one service
     *
     * @param string $serviceKey Service key in DI container
     * @param array $config Service configuration [class, interface]
     * @return bool True if registered, false if already exists
     */
    protected function registerService(string $serviceKey, array $config): bool
    {
        if ($this->modx->services->has($serviceKey)) {
            return false;
        }

        $className = $config['class'];
        $requiredInterface = $config['interface'] ?? null;

        $fallbackClass = $this->defaultServices[$serviceKey]['class'] ?? $className;

        $validatedClass = $this->validateClass($className, $fallbackClass, $requiredInterface);

        $modx = $this->modx;

        // Controllers requiring only MiniShop3 instance: __construct(MiniShop3 $ms3)
        $controllersWithMs3Only = ['ms3_cart', 'ms3_order', 'ms3_customer'];

        // Services requiring both modX and MiniShop3: __construct(modX $modx, MiniShop3 $ms3)
        $servicesWithModxAndMs3 = [
            'ms3_order_draft_manager',
            'ms3_order_cost_calculator',
            'ms3_order_user_resolver',
            'ms3_order_log',
            'ms3_order_finalize',
            'ms3_cart_item_manager',
            'ms3_customer_address_manager',
        ];

        // Services with complex dependencies (resolved via DI)
        $servicesWithDependencies = [
            'ms3_order_field_manager',
            'ms3_order_address_manager',
            'ms3_order_submit_handler',
            'ms3_order_status',
        ];

        if (in_array($serviceKey, $controllersWithMs3Only)) {
            $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                return new $validatedClass($ms3);
            });
        } elseif (in_array($serviceKey, $servicesWithModxAndMs3)) {
            $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                return new $validatedClass($modx, $ms3);
            });
        } elseif (in_array($serviceKey, $servicesWithDependencies)) {
            // Services with dependencies - resolve them from DI
            $this->registerServiceWithDependencies($serviceKey, $validatedClass);
        } else {
            $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                return new $validatedClass($modx);
            });
        }

        return true;
    }

    /**
     * Register service with complex dependencies
     *
     * Dependencies are resolved from DI container (lazy loading).
     * This allows overriding any dependency via config.
     *
     * @param string $serviceKey Service key
     * @param string $validatedClass Validated class name
     * @return void
     */
    protected function registerServiceWithDependencies(string $serviceKey, string $validatedClass): void
    {
        $modx = $this->modx;

        switch ($serviceKey) {
            case 'ms3_order_field_manager':
                // OrderFieldManager(modX, MiniShop3, OrderDraftManager)
                $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                    $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                    $draftManager = $modx->services->get('ms3_order_draft_manager');
                    return new $validatedClass($modx, $ms3, $draftManager);
                });
                break;

            case 'ms3_order_address_manager':
                // OrderAddressManager(modX, MiniShop3, OrderDraftManager, OrderFieldManager)
                $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                    $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                    $draftManager = $modx->services->get('ms3_order_draft_manager');
                    $fieldManager = $modx->services->get('ms3_order_field_manager');
                    return new $validatedClass($modx, $ms3, $draftManager, $fieldManager);
                });
                break;

            case 'ms3_order_submit_handler':
                // OrderSubmitHandler(modX, MiniShop3, OrderDraftManager, OrderCostCalculator,
                //                    OrderFieldManager, OrderAddressManager, OrderUserResolver)
                $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                    $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                    $draftManager = $modx->services->get('ms3_order_draft_manager');
                    $costCalculator = $modx->services->get('ms3_order_cost_calculator');
                    $fieldManager = $modx->services->get('ms3_order_field_manager');
                    $addressManager = $modx->services->get('ms3_order_address_manager');
                    $userResolver = $modx->services->get('ms3_order_user_resolver');
                    return new $validatedClass(
                        $modx,
                        $ms3,
                        $draftManager,
                        $costCalculator,
                        $fieldManager,
                        $addressManager,
                        $userResolver
                    );
                });
                break;

            case 'ms3_order_status':
                // OrderStatusService(modX, MiniShop3, OrderLogService)
                $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                    $ms3 = $modx->getService('MiniShop3', \MiniShop3\MiniShop3::class);
                    $orderLog = $modx->services->get('ms3_order_log');
                    return new $validatedClass($modx, $ms3, $orderLog);
                });
                break;

            default:
                // Fallback: create with modX only
                $this->modx->services->add($serviceKey, function () use ($validatedClass, $modx) {
                    return new $validatedClass($modx);
                });
        }
    }

    /**
     * Validate substituted class
     *
     * Checks:
     * - Class existence
     * - Required interface implementation (if specified)
     * - Base class inheritance
     *
     * Logs errors and returns fallback class on failures.
     *
     * @param string $className Class to validate
     * @param string $fallbackClass Default class (if validation fails)
     * @param string|null $requiredInterface Required interface (optional)
     * @return string Validated class or fallback
     */
    protected function validateClass(
        string $className,
        string $fallbackClass,
        ?string $requiredInterface = null
    ): string {
        if ($className === $fallbackClass) {
            return $className;
        }

        if (!class_exists($className)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[MiniShop3 ServiceRegistry] Class '{$className}' not found, using fallback: {$fallbackClass}"
            );
            return $fallbackClass;
        }

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

        if (!is_subclass_of($className, $fallbackClass)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[MiniShop3 ServiceRegistry] Class '{$className}' must extend {$fallbackClass}, using fallback"
            );
            return $fallbackClass;
        }

        if ($className !== $fallbackClass) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3 ServiceRegistry] Using custom class: {$className}"
            );
        }

        return $className;
    }

    /**
     * Get list of all registered services
     *
     * @return array Array of service keys
     */
    public function getRegisteredServices(): array
    {
        return array_keys(array_merge($this->defaultServices, $this->customServices));
    }

    /**
     * Get specific service configuration
     *
     * @param string $serviceKey Service key
     * @return array|null Configuration or null if not found
     */
    public function getServiceConfig(string $serviceKey): ?array
    {
        $services = array_merge($this->defaultServices, $this->customServices);
        return $services[$serviceKey] ?? null;
    }

    /**
     * Check if service is overridden by user
     *
     * @param string $serviceKey Service key
     * @return bool
     */
    public function isCustomService(string $serviceKey): bool
    {
        return isset($this->customServices[$serviceKey]);
    }
}
