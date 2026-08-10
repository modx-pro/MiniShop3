<?php

namespace MiniShop3\Processors\Utilities\Import;

use MiniShop3\Services\Import\ImportExtraFieldCatalog;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\Processors\Processor;

/**
 * Get available fields for import mapping
 */
class Fields extends Processor
{
    public $languageTopics = ['minishop3:default', 'minishop3:manager', 'minishop3:product'];
    public $permission = 'msproduct_save';

    public function checkPermissions(): bool
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }

    public function getLanguageTopics(): array
    {
        return $this->languageTopics;
    }

    public function process(): array
    {
        // Load field configuration
        $configPath = MODX_CORE_PATH . 'components/minishop3/config/import-fields.php';
        if (!file_exists($configPath)) {
            return $this->failure('Import fields configuration not found');
        }

        $config = include $configPath;

        // Build grouped fields list
        $fields = [];

        // Add skip option
        $fields[] = [
            'value' => '-',
            'label' => $this->modx->lexicon('ms3_import_skip_column'),
            'group' => '',
        ];

        $groupResource = $this->modx->lexicon('ms3_import_field_group_resource');
        $groupProduct = $this->modx->lexicon('ms3_import_field_group_product');
        $groupTv = $this->modx->lexicon('ms3_import_field_group_tv');
        $groupOptions = $this->modx->lexicon('ms3_import_field_group_options');
        $groupSpecial = $this->modx->lexicon('ms3_import_field_group_special');

        // Resource fields
        foreach ($config['resource'] as $field => $fieldConfig) {
            $fields[] = [
                'value' => $field,
                'label' => $this->modx->lexicon($fieldConfig['label'] ?? $field),
                'group' => $groupResource,
                'required' => $fieldConfig['required'] ?? false,
                'type' => $fieldConfig['type'] ?? 'string',
            ];
        }

        // Product data fields
        foreach ($config['product_data'] as $field => $fieldConfig) {
            $fields[] = [
                'value' => $field,
                'label' => $this->modx->lexicon($fieldConfig['label'] ?? $field),
                'group' => $groupProduct,
                'required' => $fieldConfig['required'] ?? false,
                'type' => $fieldConfig['type'] ?? 'string',
            ];
        }

        // msExtraField / Object Extension columns (dynamic)
        $extraCatalog = new ImportExtraFieldCatalog($this->modx);
        foreach ($extraCatalog->listImportFields($config) as $extraField) {
            $fields[] = [
                'value' => $extraField['value'],
                'label' => $extraField['label'],
                'group' => $extraField['group'] === 'resource' ? $groupResource : $groupProduct,
                'required' => $extraField['required'],
                'type' => $extraField['type'],
            ];
        }

        // Special fields
        foreach ($config['special'] as $field => $fieldConfig) {
            $fields[] = [
                'value' => $field,
                'label' => $this->modx->lexicon($fieldConfig['label'] ?? $field),
                'group' => $groupSpecial,
                'required' => $fieldConfig['required'] ?? false,
                'type' => $fieldConfig['type'] ?? 'string',
                'multiple' => $fieldConfig['multiple'] ?? false,
            ];
        }

        // Get TV fields dynamically
        $tvFields = $this->getTvFields();
        foreach ($tvFields as $tv) {
            $fields[] = [
                'value' => 'tv.' . $tv['name'],
                'label' => $tv['caption'] ?: $tv['name'],
                'group' => $groupTv,
                'type' => 'string',
            ];
        }

        // Get Option fields info
        $optionFields = $this->getOptionFields();
        foreach ($optionFields as $option) {
            $fields[] = [
                'value' => 'option.' . $option['key'],
                'label' => $option['key'],
                'group' => $groupOptions,
                'type' => 'string',
            ];
        }

        // Key fields for duplicate detection
        $keyFields = [];
        foreach ($config['key_fields'] as $field) {
            $keyFields[] = [
                'value' => $field,
                'label' => $this->getFieldLabel($field, $config),
            ];
        }

        return $this->success('', [
            'fields' => $fields,
            'key_fields' => $keyFields,
            'prefixes' => $config['prefixes'],
            'default_mapping' => $config['default_mapping'],
        ]);
    }

    /**
     * Get TV fields available for products
     */
    private function getTvFields(): array
    {
        $tvs = [];

        // Get product template
        $productTemplate = $this->modx->getOption('ms3_template_product_default', null, 0);

        $q = $this->modx->newQuery(modTemplateVar::class);
        $q->select(['id', 'name', 'caption']);

        // If we have a default template, get TVs attached to it
        if ($productTemplate) {
            $q->innerJoin('MODX\\Revolution\\modTemplateVarTemplate', 'tvtpl', 'tvtpl.tmplvarid = modTemplateVar.id');
            $q->where(['tvtpl.templateid' => $productTemplate]);
        }

        $q->sortby('name', 'ASC');
        $q->limit(50); // Limit for performance

        foreach ($this->modx->getIterator(modTemplateVar::class, $q) as $tv) {
            $tvs[] = [
                'id' => $tv->get('id'),
                'name' => $tv->get('name'),
                'caption' => $tv->get('caption'),
            ];
        }

        return $tvs;
    }

    /**
     * Get unique option keys from existing products
     */
    private function getOptionFields(): array
    {
        $options = [];

        // Get distinct option keys
        $q = $this->modx->newQuery('MiniShop3\\Model\\msProductOption');
        $q->select(['DISTINCT(`key`) as `key`']);
        $q->limit(50);

        $stmt = $q->prepare();
        if ($stmt && $stmt->execute()) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!empty($row['key'])) {
                    $options[] = ['key' => $row['key']];
                }
            }
        }

        return $options;
    }

    /**
     * Get label for a field
     */
    private function getFieldLabel(string $field, array $config): string
    {
        // Check resource fields
        if (isset($config['resource'][$field]['label'])) {
            return $this->modx->lexicon($config['resource'][$field]['label']);
        }

        // Check product data fields
        if (isset($config['product_data'][$field]['label'])) {
            return $this->modx->lexicon($config['product_data'][$field]['label']);
        }

        return $field;
    }
}
