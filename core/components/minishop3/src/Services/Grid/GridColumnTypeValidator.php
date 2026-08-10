<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

use MiniShop3\Interfaces\ComputedFieldInterface;
use MODX\Revolution\modX;

/**
 * Validates grid column JSON config by column type (template/relation/…).
 *
 * @phpstan-type ValidationResult array{success: bool, message?: string, config?: array<string, mixed>}
 */
final class GridColumnTypeValidator
{
    /** @var list<string> */
    private const ALLOWED_AGGREGATIONS = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

    /** @var list<string> */
    private const ALLOWED_ACTION_HANDLERS = [
        'edit', 'delete', 'view', 'refresh', 'addresses', 'publish', 'duplicate',
    ];

    /** @var list<string> */
    private const ALLOWED_SEVERITIES = ['secondary', 'success', 'info', 'warn', 'danger'];

    /** @var array<string, string> */
    private const RELATION_REQUIRED = [
        'table' => 'relation.table is required',
        'foreignKey' => 'relation.foreignKey is required',
        'displayField' => 'relation.displayField is required',
    ];

    public function __construct(private modX $modx)
    {
    }

    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateForType(string $type, array $config, string $fieldName = '', string $gridKey = ''): array
    {
        return match ($type) {
            'template' => $this->validateTemplateConfig($config),
            'relation' => $this->validateRelationConfig($config, $fieldName, $gridKey),
            'computed' => $this->validateComputedConfig($config),
            'actions' => $this->validateActionsConfig($config),
            'option' => $this->validateOptionConfig($config, $fieldName),
            default => ['success' => true],
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateTemplateConfig(array $config): array
    {
        if (empty($config['template'])) {
            return ['success' => false, 'message' => 'template is required for template field'];
        }

        if (!is_string($config['template'])) {
            return ['success' => false, 'message' => 'template must be a string'];
        }

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateRelationConfig(array $config, string $fieldName = '', string $gridKey = ''): array
    {
        $relation = $config['relation'] ?? [];

        foreach (self::RELATION_REQUIRED as $key => $message) {
            if (empty($relation[$key])) {
                return ['success' => false, 'message' => $message];
            }
        }

        if (!GridColumnRules::isValidSqlIdentifier((string) $relation['foreignKey'])) {
            return [
                'success' => false,
                'message' => 'relation.foreignKey must contain only letters, numbers and underscores',
            ];
        }

        if (!GridColumnRules::isValidSqlIdentifier((string) $relation['displayField'])) {
            return [
                'success' => false,
                'message' => 'relation.displayField must contain only letters, numbers and underscores',
            ];
        }

        if ($gridKey === 'category-products' && $fieldName !== ''
            && !GridColumnRules::isValidCategoryProductExtraFieldName($fieldName)
        ) {
            return [
                'success' => false,
                'message' => "Field name '{$fieldName}' is not allowed for relation columns: "
                    . 'it collides with a builtin product column or contains invalid characters. '
                    . "Use a distinct name like 'vendor_address' instead.",
            ];
        }

        $aggregation = $relation['aggregation'] ?? null;
        if ($aggregation !== null && !in_array($aggregation, self::ALLOWED_AGGREGATIONS, true)) {
            return [
                'success' => false,
                'message' => 'Invalid aggregation type. Allowed: ' . implode(', ', self::ALLOWED_AGGREGATIONS),
            ];
        }

        if ($gridKey === 'category-products' && $aggregation !== null) {
            return [
                'success' => false,
                'message' => 'Relation aggregation is not supported for category-products grid',
            ];
        }

        $tableOrModel = $relation['table'];
        $isModel = str_contains($tableOrModel, '\\') || str_contains($tableOrModel, '::');

        if ($isModel) {
            try {
                if (!class_exists($tableOrModel)) {
                    return ['success' => false, 'message' => "Invalid model class: {$tableOrModel}"];
                }

                $tableName = $this->modx->getTableName($tableOrModel);

                if (empty($tableName)) {
                    return ['success' => false, 'message' => "Unable to get table name for model: {$tableOrModel}"];
                }

                $config['relation']['resolvedTableName'] = $tableName;
                $config['relation']['resolvedModelClass'] = $tableOrModel;
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => "Invalid model class: {$tableOrModel}. " . $e->getMessage(),
                ];
            }
        } else {
            $tablePrefix = $this->modx->config['table_prefix'] ?? '';
            if ($tablePrefix !== '' && !str_starts_with($tableOrModel, $tablePrefix)) {
                $tableOrModel = $tablePrefix . $tableOrModel;
            }
            $config['relation']['resolvedTableName'] = $tableOrModel;

            $modelClass = self::resolveShortModelClass((string) $relation['table']);
            if ($modelClass !== null) {
                $config['relation']['resolvedModelClass'] = $modelClass;
            }
        }

        return ['success' => true, 'config' => $config];
    }


    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateComputedConfig(array $config): array
    {
        $computed = $config['computed'] ?? [];

        if (empty($computed['className'])) {
            return ['success' => false, 'message' => 'computed.className is required'];
        }

        if (!class_exists($computed['className'])) {
            return ['success' => false, 'message' => "Class {$computed['className']} not found"];
        }

        $interfaces = class_implements($computed['className']) ?: [];
        if (!isset($interfaces[ComputedFieldInterface::class])) {
            return ['success' => false, 'message' => 'Class must implement ComputedFieldInterface'];
        }

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateActionsConfig(array $config): array
    {
        $actions = $config['actions'] ?? [];

        if (!is_array($actions)) {
            return ['success' => false, 'message' => 'actions must be an array'];
        }

        $actionNames = [];

        foreach ($actions as $index => $action) {
            if (empty($action['name'])) {
                return ['success' => false, 'message' => "actions[{$index}].name is required"];
            }

            if (in_array($action['name'], $actionNames, true)) {
                return ['success' => false, 'message' => "Duplicate action name: {$action['name']}"];
            }
            $actionNames[] = $action['name'];

            if (empty($action['handler'])) {
                return ['success' => false, 'message' => "actions[{$index}].handler is required"];
            }

            $handler = $action['handler'];
            if (
                !in_array($handler, self::ALLOWED_ACTION_HANDLERS, true)
                && !str_starts_with($handler, 'custom:')
            ) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[GridColumnTypeValidator] Custom handler used: {$handler} in action {$action['name']}"
                );
            }

            if (!empty($action['severity']) && !in_array($action['severity'], self::ALLOWED_SEVERITIES, true)) {
                return [
                    'success' => false,
                    'message' => "Invalid severity for action {$action['name']}. Allowed: "
                        . implode(', ', self::ALLOWED_SEVERITIES),
                ];
            }
        }

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $config
     * @return ValidationResult
     */
    public function validateOptionConfig(array $config, string $fieldName = ''): array
    {
        $option = $config['option'] ?? [];

        if (empty($option['key'])) {
            return ['success' => false, 'message' => 'option.key is required for option field'];
        }

        $key = (string) $option['key'];
        if (!OptionColumnSpec::isValidOptionKey($key)) {
            return ['success' => false, 'message' => 'option.key must contain only letters, numbers and underscores'];
        }

        if ($fieldName !== '' && !OptionColumnSpec::isValidFieldName($fieldName)) {
            return [
                'success' => false,
                'message' => "Field name '{$fieldName}' is not allowed for option columns: "
                    . 'it collides with a builtin product column or contains invalid characters. '
                    . "Use a name like 'option_{$key}' instead.",
            ];
        }

        return ['success' => true];
    }

    private static function resolveShortModelClass(string $tableName): ?string
    {
        $map = [
            'msOrder' => 'MiniShop3\Model\msOrder',
            'ms3_orders' => 'MiniShop3\Model\msOrder',
            'msOrderStatus' => 'MiniShop3\Model\msOrderStatus',
            'ms3_order_statuses' => 'MiniShop3\Model\msOrderStatus',
            'msOrderAddress' => 'MiniShop3\Model\msOrderAddress',
            'ms3_order_addresses' => 'MiniShop3\Model\msOrderAddress',
            'msOrderProduct' => 'MiniShop3\Model\msOrderProduct',
            'ms3_order_products' => 'MiniShop3\Model\msOrderProduct',
            'msDelivery' => 'MiniShop3\Model\msDelivery',
            'ms3_deliveries' => 'MiniShop3\Model\msDelivery',
            'msPayment' => 'MiniShop3\Model\msPayment',
            'ms3_payments' => 'MiniShop3\Model\msPayment',
            'msProduct' => 'MiniShop3\Model\msProduct',
            'ms3_products' => 'MiniShop3\Model\msProduct',
            'msProductData' => 'MiniShop3\Model\msProductData',
            'ms3_product_data' => 'MiniShop3\Model\msProductData',
            'msCategory' => 'MiniShop3\Model\msCategory',
            'msCategoryMember' => 'MiniShop3\Model\msCategoryMember',
            'ms3_category_members' => 'MiniShop3\Model\msCategoryMember',
            'msVendor' => 'MiniShop3\Model\msVendor',
            'ms3_vendors' => 'MiniShop3\Model\msVendor',
            'msCustomer' => 'MiniShop3\Model\msCustomer',
            'ms3_customers' => 'MiniShop3\Model\msCustomer',
            'modUser' => 'MODX\Revolution\modUser',
            'modUserProfile' => 'MODX\Revolution\modUserProfile',
            'modResource' => 'MODX\Revolution\modResource',
            'site_content' => 'MODX\Revolution\modResource',
        ];

        return $map[$tableName] ?? null;
    }
}
