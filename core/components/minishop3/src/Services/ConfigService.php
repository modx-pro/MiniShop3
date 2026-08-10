<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 */
class ConfigService
{
    /** @var modX */
    protected $modx;


    protected $configManager;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get page field configuration from ms3_product_fields
     * Returns only VISIBLE fields and sections for display in form
     *
     * @param string $pageKey Page key (product_data, product_gallery, etc.)
     * @param string $contextKey Context key (default: web)
     * @return array
     * @throws \Exception
     */
    public function getPageFields(string $pageKey, string $contextKey = 'web'): array
    {
        $allFields = $this->getAllPageFields($pageKey, $contextKey);

        $visibleFields = array_filter($allFields['fields'], function($field) {
            return !($field['hidden'] ?? false);
        });

        $sections = $this->getSections($pageKey, $contextKey);

        $sectionsById = [];
        foreach ($sections as $section) {
            if (!($section['hidden'] ?? false)) {
                $sectionsById[$section['id']] = $section;
            }
        }

        return [
            'fields' => array_values($visibleFields),
            'sections' => $sectionsById
        ];
    }

    /**
     * Get ALL available fields (including hidden) from ms3_product_fields table
     *
     * @param string $pageKey Page key
     * @param string $contextKey Context key (default: web)
     * @return array
     * @throws \Exception
     */
    public function getAllPageFields(string $pageKey, string $contextKey = 'web'): array
    {
        $query = $this->modx->newQuery('MiniShop3\\Model\\msProductField');
        $query->sortby('sort_order', 'ASC');

        $collection = $this->modx->getCollection('MiniShop3\\Model\\msProductField', $query);

        $fields = [];
        foreach ($collection as $field) {
            $config = $field->get('config');

            if (is_string($config)) {
                $config = json_decode($config, true) ?: [];
            } elseif (!is_array($config)) {
                $config = [];
            }

            $fieldName = $field->get('name');
            $labelFromDb = $field->get('label');
            $descriptionFromDb = $field->get('description');

            if (!empty($labelFromDb)) {
                $label = $labelFromDb;
            } else {
                $lexiconKey = 'ms3_product_' . $fieldName;
                $labelFromLexicon = $this->getLexiconValue($lexiconKey, 'minishop3', 'product');
                $label = ($labelFromLexicon === $lexiconKey) ? $fieldName : $labelFromLexicon;
            }

            if (!empty($descriptionFromDb)) {
                $description = $descriptionFromDb;
            } else {
                $lexiconKey = 'ms3_product_' . $fieldName . '_help';
                $descriptionFromLexicon = $this->getLexiconValue($lexiconKey, 'minishop3', 'product');
                $description = ($descriptionFromLexicon === $lexiconKey) ? '' : $descriptionFromLexicon;
            }

            $fieldData = [
                'name' => $fieldName,
                'label' => $label,
                'xtype' => $field->get('xtype'),
                'section' => $field->get('section'),
                'hidden' => !(bool)$field->get('visible'),
                'visible' => (bool)$field->get('visible'),
                'required' => (bool)$field->get('required'),
                'sort_order' => (int)$field->get('sort_order'),
                'width' => (int)$field->get('width'),
                'description' => $description,
                'is_system' => (bool)$field->get('is_system'),
                'is_default' => (bool)$field->get('is_default'),
            ];

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
     * Save bulk field changes to ms3_product_fields table
     *
     * @param array $fields Array of fields with settings
     * @return bool
     */
    public function saveFieldsConfig(array $fields): bool
    {
        try {
            foreach ($fields as $fieldData) {
                $fieldName = $fieldData['name'] ?? null;

                if (!$fieldName) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                        '[ConfigService] Skipping field without name');
                    continue;
                }

                $field = $this->modx->getObject('MiniShop3\\Model\\msProductField', [
                    'name' => $fieldName
                ]);

                if (!$field) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                        "[ConfigService] Field not found: {$fieldName}");
                    continue;
                }

                if (isset($fieldData['visible'])) {
                    $field->set('visible', $fieldData['visible'] ? 1 : 0);
                } elseif (isset($fieldData['hidden'])) {
                    $field->set('visible', $fieldData['hidden'] ? 0 : 1);
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
     * Get value from lexicon with fallback logic
     *
     * Logic:
     * 1. Try to load for current admin language
     * 2. If not found - try English (en)
     * 3. If not found - return the key itself
     *
     * @param string $key Lexicon key
     * @param string $namespace Lexicon namespace (default: minishop3)
     * @param string $topic Lexicon topic (default: default)
     * @return string
     */
    protected function getLexiconValue(string $key, string $namespace = 'minishop3', string $topic = 'default'): string
    {
        $currentLanguage = $this->modx->getOption('cultureKey', null, 'en');
        if (!empty($this->modx->cultureKey)) {
            $currentLanguage = $this->modx->cultureKey;
        }

        $lexiconPath = MODX_CORE_PATH . "components/{$namespace}/lexicon/{$currentLanguage}/{$topic}.inc.php";

        if (file_exists($lexiconPath)) {
            $_lang = [];
            include $lexiconPath;

            if (isset($_lang[$key])) {
                return $_lang[$key];
            }
        }

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

        return $key;
    }

    /**
     * Get page sections from database with translations from lexicon
     *
     * @param string $pageKey Page key (product_data, etc.)
     * @param string $contextKey Context key (not used yet, for future extension)
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

            $config = is_array($configRaw) ? $configRaw : (is_string($configRaw) ? json_decode($configRaw, true) : []);

            $section = [
                'id' => (int)$item->get('id'),
                'key' => $item->get('section_key'),
                'lexicon_key' => $config['lexicon_key'] ?? '',
                'sort_order' => (int)$item->get('sort_order'),
                'hidden' => (bool)$item->get('hidden'),
                'is_default' => (bool)$item->get('is_default'),
            ];

            // Priority: 1) explicit label from config, 2) lexicon translation, 3) section_key
            if (!empty($config['label'])) {
                // User explicitly set a custom label - use it
                $section['label'] = $config['label'];
            } else if (!empty($section['lexicon_key'])) {
                // No custom label, but has lexicon key - use translation
                $section['label'] = $this->getLexiconValue($section['lexicon_key'], 'minishop3', 'product');
            } else {
                // Fallback to section key
                $section['label'] = $item->get('section_key');
            }

            $sections[] = $section;
        }

        usort($sections, function($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });

        return $sections;
    }


    /**
     * Save sections (order, visibility)
     *
     * @param string $pageKey
     * @param array $sections Array of sections with settings
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
                $sortOrder = $index;

                $pageSection = $this->modx->getObject('MiniShop3\\Model\\msPageSection', [
                    'page_key' => $pageKey,
                    'section_key' => $sectionKey,
                ]);

                if (!$pageSection) {
                    $pageSection = $this->modx->newObject('MiniShop3\\Model\\msPageSection');
                    $pageSection->set('page_key', $pageKey);
                    $pageSection->set('section_key', $sectionKey);
                    $pageSection->set('is_default', $isDefault);
                }

                $pageSection->set('hidden', $hidden);
                $pageSection->set('sort_order', $sortOrder);

                // Build config from passed data
                // Use array_key_exists() instead of isset() to handle null values correctly
                $config = [];
                if (array_key_exists('lexicon_key', $section) && !empty($section['lexicon_key'])) {
                    $config['lexicon_key'] = $section['lexicon_key'];
                }
                if (array_key_exists('label', $section) && !empty($section['label'])) {
                    $config['label'] = $section['label'];
                }

                // Always update config field (even if empty, to clear old values)
                $pageSection->set('config', !empty($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : null);

                if (!$pageSection->save()) {
                    $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                        "[ConfigService] Failed to save section: {$sectionKey}");
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
     * Delete section from database
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
                return true;
            }

            return $section->remove();
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ConfigService] Error deleting section: " . $e->getMessage());
            return false;
        }
    }
}
