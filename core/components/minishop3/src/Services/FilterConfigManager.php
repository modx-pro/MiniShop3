<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 * Filter Configuration Manager
 *
 * Manages grid filter configurations from file-based configs.
 * Supports custom configs that won't be overwritten on component update.
 *
 * Config locations:
 * - Default: core/components/minishop3/config/filters/{grid}.php
 * - Custom:  core/components/minishop3/custom/filters/{grid}.php
 *
 * Filter types:
 * - text: Text input with search across multiple fields
 * - select: Dropdown with options from model, API, or static list
 * - datepicker: Single date picker
 * - daterange: Date range picker (from/to)
 */
class FilterConfigManager
{
    private modX $modx;
    private string $configPath;
    private string $customPath;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->configPath = MODX_CORE_PATH . 'components/minishop3/config/filters/';
        $this->customPath = MODX_CORE_PATH . 'components/minishop3/custom/filters/';
    }

    /**
     * Get filters configuration for a grid
     *
     * @param string $gridKey Grid identifier (orders, customers, products)
     * @param bool $resolveOptions Whether to resolve select options
     * @return array Filter configurations
     */
    public function getFilters(string $gridKey, bool $resolveOptions = false): array
    {
        $filters = $this->loadConfig($gridKey);

        // Filter only visible filters
        $filters = array_filter($filters, fn($f) => ($f['visible'] ?? true) === true);

        // Resolve select options if requested
        if ($resolveOptions) {
            foreach ($filters as $key => $filter) {
                if (($filter['type'] ?? '') === 'select' && !empty($filter['source'])) {
                    // Only resolve non-API sources (model, static)
                    // API sources are resolved on frontend
                    if (($filter['source']['type'] ?? '') !== 'api') {
                        $options = $this->resolveSelectOptions($filter['source']);
                        $filters[$key]['options'] = $options;
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * Load and merge config files
     *
     * @param string $gridKey Grid identifier
     * @return array Merged configuration
     */
    private function loadConfig(string $gridKey): array
    {
        // Sanitize grid key
        $gridKey = preg_replace('/[^a-z0-9_-]/i', '', $gridKey);

        // Load default config
        $defaultFile = $this->configPath . $gridKey . '.php';
        $filters = [];

        if (file_exists($defaultFile)) {
            $filters = include $defaultFile;
            if (!is_array($filters)) {
                $filters = [];
            }
        }

        // Merge with custom config (custom overrides default)
        $customFile = $this->customPath . $gridKey . '.php';
        if (file_exists($customFile)) {
            $custom = include $customFile;
            if (is_array($custom)) {
                $filters = array_replace_recursive($filters, $custom);
            }
        }

        return $filters;
    }

    /**
     * Resolve select options from source configuration
     *
     * @param array $source Source configuration
     * @return array Options array [{value, label}, ...]
     */
    public function resolveSelectOptions(array $source): array
    {
        $type = $source['type'] ?? '';

        return match ($type) {
            'model' => $this->resolveFromModel($source),
            'static' => $source['options'] ?? [],
            default => [],
        };
    }

    /**
     * Resolve options from xPDO model
     *
     * Optimized query:
     * - SELECT only required fields (valueField, labelField)
     * - WHERE conditions from config
     * - ORDER BY from config
     * - LIMIT protection against large tables
     *
     * @param array $source Source configuration
     * @return array Options array
     */
    private function resolveFromModel(array $source): array
    {
        $class = $source['class'] ?? '';
        if (empty($class)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[FilterConfigManager] Empty class in source config");
            return [];
        }

        $valueField = $source['valueField'] ?? 'id';
        $labelField = $source['labelField'] ?? 'name';
        $limit = $source['limit'] ?? 500;

        try {
            $c = $this->modx->newQuery($class);

            // SELECT only required fields
            $c->select([
                $this->modx->escape($valueField),
                $this->modx->escape($labelField)
            ]);

            // WHERE conditions
            if (!empty($source['where'])) {
                $c->where($source['where']);
            }

            // ORDER BY
            if (!empty($source['sort'])) {
                foreach ($source['sort'] as $field => $dir) {
                    $c->sortby($field, $dir);
                }
            }

            // LIMIT protection
            $c->limit($limit);

            $options = [];
            foreach ($this->modx->getIterator($class, $c) as $item) {
                $label = $item->get($labelField);

                // Translate lexicon keys for status names
                if (str_starts_with($label, 'ms3_')) {
                    $translated = $this->modx->lexicon($label);
                    if ($translated !== $label) {
                        $label = $translated;
                    }
                }

                $options[] = [
                    'value' => $item->get($valueField),
                    'label' => $label,
                ];
            }

            return $options;
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[FilterConfigManager] Error resolving options from model {$class}: " . $e->getMessage()
            );
            return [];
        }
    }

    /**
     * Get available grids that have filter configs
     *
     * @return array List of grid keys
     */
    public function getAvailableGrids(): array
    {
        $grids = [];

        // Scan default config directory
        if (is_dir($this->configPath)) {
            foreach (glob($this->configPath . '*.php') as $file) {
                $grids[] = basename($file, '.php');
            }
        }

        // Scan custom config directory
        if (is_dir($this->customPath)) {
            foreach (glob($this->customPath . '*.php') as $file) {
                $grid = basename($file, '.php');
                if (!in_array($grid, $grids)) {
                    $grids[] = $grid;
                }
            }
        }

        return $grids;
    }
}
