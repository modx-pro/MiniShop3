<?php

namespace MiniShop3\Services\ExtraFields;

use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;

class RepeaterFieldService
{
    public const XTYPE = 'ms3-repeater';

    private modX $modx;

    /** @var array<string, array<string, array>>|null */
    private ?array $fieldsByClass = null;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function isRepeaterXtype(?string $xtype): bool
    {
        return $xtype === self::XTYPE;
    }

    public function defaultConfig(): array
    {
        return [
            'columns' => [],
            'minRows' => 0,
            'maxRows' => null,
            'sortable' => true,
            'rankField' => 'rank',
        ];
    }

    /**
     * @param mixed $json JSON string or array
     */
    public function parseConfig(mixed $json): array
    {
        $config = $this->defaultConfig();

        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            }
        } elseif (is_array($json)) {
            $config = array_merge($config, $json);
        }

        if (!is_array($config['columns'])) {
            $config['columns'] = [];
        }

        if ($config['rankField'] === '' || $config['rankField'] === null) {
            $config['rankField'] = 'rank';
        }

        return $config;
    }

    public function encodeConfig(array $config): string
    {
        return json_encode($config, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{success: bool, message?: string, config?: array}
     */
    public function validateConfigSchema(mixed $json): array
    {
        $config = $this->parseConfig($json);

        if (empty($config['columns'])) {
            return ['success' => false, 'message' => 'Repeater requires at least one column'];
        }

        $keys = [];
        foreach ($config['columns'] as $column) {
            if (!is_array($column)) {
                return ['success' => false, 'message' => 'Invalid column definition'];
            }
            $key = trim((string)($column['key'] ?? ''));
            if ($key === '') {
                return ['success' => false, 'message' => 'Each column must have a key'];
            }
            if (in_array($key, $keys, true)) {
                return ['success' => false, 'message' => "Duplicate column key: {$key}"];
            }
            $keys[] = $key;
        }

        return ['success' => true, 'config' => $config];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function decodeValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Repeater value must be a JSON array');
            }
            return array_values($decoded);
        }

        if (is_array($value)) {
            return array_values($value);
        }

        throw new \InvalidArgumentException('Repeater value must be an array');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function normalizeRows(array $rows, array $config): array
    {
        $rankField = (string)($config['rankField'] ?? 'rank');
        $maxRows = $config['maxRows'] ?? null;
        $normalized = [];
        $rank = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $cleanRow = $this->sanitizeRow($row, $config);
            if ($this->isEmptyRow($cleanRow, $rankField)) {
                continue;
            }

            $cleanRow[$rankField] = $rank;
            $normalized[] = $cleanRow;
            $rank++;

            if ($maxRows !== null && count($normalized) >= (int)$maxRows) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{ok: bool, errors?: list<string>}
     */
    public function validateRows(array $rows, array $config): array
    {
        $errors = [];
        $minRows = (int)($config['minRows'] ?? 0);
        $maxRows = $config['maxRows'] ?? null;

        if (count($rows) < $minRows) {
            $errors[] = "Minimum {$minRows} rows required";
        }

        if ($maxRows !== null && count($rows) > (int)$maxRows) {
            $errors[] = "Maximum {$maxRows} rows allowed";
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $errors[] = "Row {$index} must be an object";
                continue;
            }

            foreach ($config['columns'] ?? [] as $column) {
                $key = (string)($column['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $cellValue = $row[$key] ?? null;
                if (!empty($column['required']) && ($cellValue === null || $cellValue === '')) {
                    $errors[] = "Row {$index}: {$key} is required";
                }

                if (($column['xtype'] ?? 'textfield') === 'numberfield'
                    && $cellValue !== null
                    && $cellValue !== ''
                    && !is_numeric($cellValue)
                ) {
                    $errors[] = "Row {$index}: {$key} must be numeric";
                }
            }
        }

        return empty($errors) ? ['ok' => true] : ['ok' => false, 'errors' => $errors];
    }

    /**
     * Decode, validate and normalize repeater payload.
     *
     * @return list<array<string, mixed>>
     */
    public function processValue(mixed $value, array $config): array
    {
        $rows = $this->decodeValue($value);
        $validation = $this->validateRows($rows, $config);

        if (!$validation['ok']) {
            throw new \InvalidArgumentException(implode('; ', $validation['errors'] ?? []));
        }

        return $this->normalizeRows($rows, $config);
    }

    /**
     * @return array<string, array> field key => parsed config
     */
    public function getRepeaterFieldsForClass(string $modelClass): array
    {
        if ($this->fieldsByClass === null) {
            $this->fieldsByClass = [];
        }

        if (isset($this->fieldsByClass[$modelClass])) {
            return $this->fieldsByClass[$modelClass];
        }

        $map = [];
        $query = $this->modx->newQuery(msExtraField::class);
        $query->where([
            'class' => $modelClass,
            'xtype' => self::XTYPE,
            'active' => true,
        ]);

        foreach ($this->modx->getIterator(msExtraField::class, $query) as $field) {
            $key = (string)$field->get('key');
            if ($key === '') {
                continue;
            }
            $map[$key] = $this->parseConfig($field->get('repeater_config'));
        }

        $this->fieldsByClass[$modelClass] = $map;

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeRow(array $row, array $config): array
    {
        $rankField = (string)($config['rankField'] ?? 'rank');
        $allowedKeys = array_column($config['columns'] ?? [], 'key');
        $clean = [];

        foreach ($allowedKeys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (array_key_exists($key, $row)) {
                $clean[$key] = $row[$key];
            }
        }

        unset($clean[$rankField]);

        return $clean;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row, string $rankField): bool
    {
        foreach ($row as $key => $value) {
            if ($key === $rankField) {
                continue;
            }
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
