<?php
/**
 * Example custom configuration for overriding MiniShop3 services
 *
 * USAGE INSTRUCTIONS:
 * =============================
 *
 * 1. Copy this file to core/config/ms3.services.php
 *    (or any other location specified in the ms3_services_config system setting)
 *
 * 2. Uncomment and configure only the services you want to override
 *
 * 3. Make sure your classes:
 *    - Exist and are accessible via autoloader
 *    - Extend base MiniShop3 classes
 *    - Implement required interfaces (if any)
 *
 * 4. On validation errors, the default class will be used + log entry
 *
 * IMPORTANT:
 * - Do not modify this file directly! Use a copy in core/config/
 * - Override only needed services, others will use defaults
 * - Your classes must be compatible with base class interfaces
 *
 * ADDON ARCHITECTURE:
 * - If you are developing an addon for MiniShop3, use the core/config/ms3.services.d/ directory
 * - Create a file there with your addon name: mycartaddon.php
 * - Files are loaded in alphabetical order, allowing priority management
 *
 * =============================
 */

return [
    // =========================================================================
    // SERVICE OVERRIDE EXAMPLES
    // =========================================================================

    /**
     * Example 1: Override Cart Controller
     *
     * IMPORTANT: This is NOT a service, but a cart controller!
     * Base class: \MiniShop3\Controllers\Cart\Cart
     *
     * Use for implementing custom cart logic:
     * - Promotional prices and discounts
     * - Special cost calculation rules
     * - Custom product validation
     * - Stock availability checks
     * - Minimum/maximum order amount
     *
     * Access: $ms3->cart or $modx->services->get('ms3_cart')
     *
     * Example: core/components/minishop3/src/Controllers/Cart/PromoCart.php
     */
    // 'ms3_cart' => [
    //     'class' => \MyProject\Controllers\CustomCart::class,
    //     'interface' => null,
    // ],

    /**
     * Example 2: Override Order Controller
     *
     * IMPORTANT: This is NOT a service, but an order controller!
     * Base class: \MiniShop3\Controllers\Order\Order
     *
     * Use for:
     * - Custom order processing workflows
     * - CRM/ERP system integration
     * - Special order business rules
     * - Additional order field validation
     * - Automated post-order processing
     *
     * Access: $ms3->order or $modx->services->get('ms3_order')
     */
    // 'ms3_order' => [
    //     'class' => \MyProject\Controllers\CustomOrder::class,
    //     'interface' => null,
    // ],

    /**
     * Example 3: Override Customer Controller
     *
     * Base class: \MiniShop3\Controllers\Customer\Customer
     *
     * Use for:
     * - Loyalty system integration
     * - Additional customer data validation
     * - Special registration/authentication rules
     *
     * Access: $ms3->customer or $modx->services->get('ms3_customer')
     */
    // 'ms3_customer' => [
    //     'class' => \MyProject\Controllers\CustomCustomer::class,
    //     'interface' => null,
    // ],

    /**
     * Example 4: Override Order Service
     *
     * IMPORTANT: This is a service, not a controller!
     * Used for order business logic.
     *
     * Use for:
     * - Custom order operations
     * - External system integration
     */
    // 'ms3_order_service' => [
    //     'class' => \MyProject\Services\CustomOrderService::class,
    //     'interface' => null,
    // ],

    /**
     * Example 5: Override Delivery Service
     *
     * Use for:
     * - External delivery service integration
     * - Special cost calculation algorithms
     * - Custom delivery address validation
     */
    // 'ms3_delivery_service' => [
    //     'class' => \MyProject\Services\CustomDeliveryService::class,
    //     'interface' => null,
    // ],

    /**
     * Example 6: Override Payment Service
     *
     * Use for:
     * - Adding custom payment providers
     * - Custom payment processing logic
     * - Special payment data validation rules
     */
    // 'ms3_payment_service' => [
    //     'class' => \MyProject\Services\CustomPaymentService::class,
    //     'interface' => null,
    // ],

    /**
     * Example 7: Override Product Data Service
     *
     * Use for:
     * - Custom product options processing
     * - External catalog integration
     * - Special data validation rules
     */
    // 'ms3_product_data_service' => [
    //     'class' => \MyProject\Services\CustomProductDataService::class,
    //     'interface' => null,
    // ],

    /**
     * Example 8: Override Product Image Service
     *
     * Use for:
     * - Custom image processing algorithms
     * - CDN or external storage integration
     * - Special watermark settings
     */
    // 'ms3_product_image' => [
    //     'class' => \MyProject\Services\CustomProductImageService::class,
    // ],

    // =========================================================================
    // COMPLETE LIST OF AVAILABLE SERVICES
    // =========================================================================

    /*
     * Configuration:
     * -------------
     * 'ms3_field_config_manager'    - Field configuration manager
     * 'ms3_config_service'          - Facade over config managers
     *
     * Products:
     * -------
     * 'ms3_product_data_service'    - Product data operations
     * 'ms3_product_image'           - Product image processing
     *
     * Vendors:
     * -----------
     * 'ms3_vendor_service'          - Vendor operations
     *
     * Delivery and Payment:
     * ------------------
     * 'ms3_delivery_service'        - Delivery service
     * 'ms3_payment_service'         - Payment service
     *
     * Orders:
     * -------
     * 'ms3_order_service'           - Order operations
     *
     * Categories:
     * ----------
     * 'ms3_category_service'         - Category operations
     * 'ms3_category_option_service'  - Category → option fields (Category\CategoryOptionService)
     *
     * Product Options (override via ms3.services.php / ms3.services.d/):
     * --------------
     * 'ms3_option_service'           - EAV options facade
     * 'ms3_option_loader'            - load option values / admin fields
     * 'ms3_option_sync'              - save/sync product option values
     * 'ms3_option_category_service'  - option ↔ category links (Option\OptionCategoryService)
     *
     * Order manager cost:
     * -------------------
     * 'ms3_manager_order_cost_recalculator' - manager order totals recalc
     *
     * Utilities:
     * --------
     * 'ms3_token_service'           - Token operations
     * 'ms3_image'                   - Image processing (Intervention Image)
     */

    // =========================================================================
    // REAL PROJECT CONFIG EXAMPLE
    // =========================================================================

    /*
    // Real example for external CRM integration:
    'ms3_order_service' => [
        'class' => \MyCompany\Integration\CRMOrderService::class,
    ],

    // Custom cart processing with promo codes:
    'ms3_cart' => [
        'class' => \MyCompany\Cart\PromoCodeCart::class,
    ],

    // CDEK delivery integration:
    'ms3_delivery_service' => [
        'class' => \MyCompany\Delivery\CdekDeliveryService::class,
    ],
    */

];
