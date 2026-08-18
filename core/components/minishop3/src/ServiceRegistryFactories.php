<?php

namespace MiniShop3;

use MODX\Revolution\modX;

/**
 * Explicit DI factory map for ServiceRegistry (#345).
 *
 * Each entry: serviceKey => callable(modX $modx, object $services, string $class): object
 *
 * @internal
 */
class ServiceRegistryFactories
{
    /**
     * @return array<string, callable(modX, object, string): object>
     */
    public static function map(): array
    {
        $modxOnly = static fn (): callable => static function (modX $modx, object $services, string $class): object {
            return new $class($modx);
        };

        $ms3Only = static fn (): callable => static function (modX $modx, object $services, string $class): object {
            return new $class(self::ms3($modx));
        };

        $modxAndMs3 = static fn (): callable => static function (modX $modx, object $services, string $class): object {
            return new $class($modx, self::ms3($modx));
        };

        return [
            'ms3_field_config_manager' => $modxOnly(),
            'ms3_config_service' => $modxOnly(),
            'ms3_product_service' => $modxOnly(),
            'ms3_product_data_service' => $modxOnly(),
            'ms3_product_import' => $modxOnly(),
            'ms3_product_category_tree' => $modxOnly(),
            'ms3_product_link_service' => $modxOnly(),
            'ms3_product_catalog' => $modxOnly(),
            'ms3_product_facets' => $modxOnly(),
            'ms3_category_catalog' => $modxOnly(),
            'ms3_delivery_catalog' => $modxOnly(),
            'ms3_payment_catalog' => $modxOnly(),
            'ms3_repeater_field' => $modxOnly(),
            'ms3_extra_fields' => $modxOnly(),
            'ms3_key_value_field' => $modxOnly(),
            'ms3_model_field_section_service' => $modxOnly(),
            'ms3_product_image' => $modxOnly(),
            'ms3_vendor_service' => $modxOnly(),
            'ms3_delivery_service' => $modxOnly(),
            'ms3_payment_service' => $modxOnly(),
            'ms3_payment_link_resolver' => $modxOnly(),
            'ms3_order_service' => $modxOnly(),
            'ms3_customer_order' => $modxOnly(),
            'ms3_order_number_generator' => $modxOnly(),
            'ms3_manager_order_presenter' => $modxOnly(),
            'ms3_token_service' => $modxOnly(),
            'ms3_category_service' => $modxOnly(),
            'ms3_category_option_service' => $modxOnly(),
            'ms3_option_category_service' => $modxOnly(),
            'ms3_image' => $modxOnly(),
            'ms3_option_loader' => $modxOnly(),
            'ms3_option_sync' => $modxOnly(),
            'ms3_option_service' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    $services->get('ms3_option_loader'),
                    $services->get('ms3_option_sync'),
                    $services->get('ms3_option_category_service')
                );
            },
            'ms3_auth_manager' => $modxOnly(),
            'ms3_register_service' => $modxOnly(),
            'ms3_email_verification_service' => $modxOnly(),
            'ms3_sms_verification_service' => $modxOnly(),
            'ms3_rate_limiter' => $modxOnly(),
            'ms3_grid_config' => $modxOnly(),
            'ms3_category_products_list' => $modxOnly(),
            'ms3_category_product_scope' => $modxOnly(),
            'ms3_category_tree' => $modxOnly(),
            'ms3_filter_config' => $modxOnly(),
            'ms3_notifications' => $modxOnly(),
            'ms3_notification_config' => $modxOnly(),
            'ms3_customer_duplicate_checker' => $modxOnly(),
            'ms3_customer_factory' => $modxOnly(),
            'ms3_settings_combo_list' => $modxOnly(),
            'ms3_validation_service' => $modxOnly(),

            'ms3_cart' => $ms3Only(),
            'ms3_order' => $ms3Only(),
            'ms3_customer' => $ms3Only(),

            'ms3_order_draft_manager' => $modxAndMs3(),
            'ms3_order_cost_calculator' => $modxAndMs3(),
            'ms3_order_user_resolver' => $modxAndMs3(),
            'ms3_order_log' => $modxAndMs3(),
            'ms3_manager_order_cost_recalculator' => $modxAndMs3(),
            'ms3_cart_item_manager' => $modxAndMs3(),
            'ms3_customer_address_manager' => $modxAndMs3(),
            'ms3_customer_field_manager' => $modxAndMs3(),

            'ms3_order_field_manager' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_draft_manager')
                );
            },

            'ms3_order_address_manager' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_draft_manager'),
                    $services->get('ms3_order_field_manager')
                );
            },

            'ms3_order_submit_handler' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_draft_manager'),
                    $services->get('ms3_order_cost_calculator'),
                    $services->get('ms3_order_field_manager'),
                    $services->get('ms3_order_address_manager'),
                    $services->get('ms3_order_user_resolver'),
                    $services->get('ms3_order_number_generator')
                );
            },

            'ms3_order_finalize' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_number_generator')
                );
            },

            'ms3_programmatic_order' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_finalize'),
                    $services->get('ms3_order_draft_manager')
                );
            },

            'ms3_order_status' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_log')
                );
            },

            'ms3_cart_mutation_handler' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_order_draft_manager'),
                    $services->get('ms3_cart_item_manager'),
                    $services->get('ms3_order_log')
                );
            },

            'ms3_customer_order_resolver' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    self::ms3($modx),
                    $services->get('ms3_customer_field_manager')
                );
            },

            'ms3_model_field_service' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    $services->get('ms3_model_field_section_service')
                );
            },

            'ms3_manager_order_list' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    $services->get('ms3_manager_order_presenter')
                );
            },

            'ms3_manager_order_mutation' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    $services->get('ms3_manager_order_presenter'),
                    $services->get('ms3_order_log')
                );
            },

            'ms3_manager_order_products' => static function (modX $modx, object $services, string $class): object {
                return new $class(
                    $modx,
                    $services->get('ms3_order_log')
                );
            },
        ];
    }

    private static function ms3(modX $modx): MiniShop3
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');

        return $ms3;
    }
}
