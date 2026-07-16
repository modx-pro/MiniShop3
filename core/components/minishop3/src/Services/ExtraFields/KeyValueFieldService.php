<?php

namespace MiniShop3\Services\ExtraFields;

use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;

class KeyValueFieldService
{
    public const XTYPE = 'ms3-key-value';

    private modX $modx;

    /** Request-scoped cache of key-value field configs keyed by model class. */
    private array $fieldsByClass = [];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function isKeyValueXtype(?string $xtype): bool
    {
        return $xtype === self::XTYPE;
    }

    public function defaultConfig(): array
    {
        return [
            'mode' => 'fixed',
            'keys' => [],
        ];
    }

    /**
     * @param mixed $json JSON string or array
     * @param string|null $fieldKey Extra field key for log context when config JSON is invalid
     */
    public function parseConfig(mixed $json, ?string $fieldKey = null): array
    {
        $config = $this->defaultConfig();

        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            } else {
                $context = $fieldKey !== null && $fieldKey !== '' ? " for field \"{$fieldKey}\"" : '';
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    '[ms3-key-value] malformed key_value_config JSON' . $context . ': ' . json_last_error_msg()
                );
            }
        } elseif (is_array($json)) {
            $config = array_merge($config, $json);
        }

        $config['mode'] = $this->resolveMode($config);

        if (!is_array($config['keys'])) {
            $config['keys'] = [];
        }

        $normalizedKeys = [];
        foreach ($config['keys'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalizedKeys[] = [
                'key' => trim((string)($item['key'] ?? '')),
                'label' => trim((string)($item['label'] ?? '')),
                'valueType' => ($item['valueType'] ?? 'string') === 'number' ? 'number' : 'string',
                'required' => (bool)($item['required'] ?? false),
            ];
        }
        $config['keys'] = $normalizedKeys;

        return $config;
    }

    public function encodeConfig(array $config): string
    {
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \InvalidArgumentException(
                'Failed to encode key_value_config: ' . json_last_error_msg()
            );
        }

        return $encoded;
    }

    /**
     * @return array{success: bool, message?: string, config?: array}
     */
    public function validateConfigSchema(mixed $json): array
    {
        $config = $this->parseConfig($json);

        if ($config['mode'] === 'fixed' && $config['keys'] === []) {
            return ['success' => false, 'message' => 'Key-value fixed mode requires at least one key'];
        }

        $keys = [];
        foreach ($config['keys'] as $item) {
            $key = $item['key'];
            if ($key === '') {
                return ['success' => false, 'message' => 'Each key definition must include a non-empty key'];
            }

            if (in_array($key, $keys, true)) {
                return ['success' => false, 'message' => "Duplicate key in schema: {$key}"];
            }
            $keys[] = $key;
        }

        return ['success' => true, 'config' => $config];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new \InvalidArgumentException('Key-value value must be a JSON object');
            }

            return $decoded;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                throw new \InvalidArgumentException(
                    'Key-value value must be an object map, list arrays are not allowed'
                );
            }

            return $value;
        }

        throw new \InvalidArgumentException('Key-value value must be an object');
    }

    /**
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    public function normalizeMap(array $map, array $config): array
    {
        $schemaMap = $this->getSchemaMap($config);

        if ($this->resolveMode($config) === 'fixed') {
            $normalized = [];
            foreach ($schemaMap as $key => $item) {
                $normalized[$key] = $this->normalizeCellValue($map[$key] ?? '', $item['valueType'] ?? 'string');
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($map as $rawKey => $rawValue) {
            $key = trim((string)$rawKey);
            if ($key === '') {
                continue;
            }

            $valueType = $schemaMap[$key]['valueType'] ?? 'string';
            $normalized[$key] = $this->normalizeCellValue($rawValue, $valueType);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $map
     * @return array{ok: bool, errors?: list<string>}
     */
    public function validateMap(array $map, array $config): array
    {
        $errors = [];
        $mode = $this->resolveMode($config);
        $schemaMap = $this->getSchemaMap($config);

        if ($mode === 'fixed') {
            foreach ($schemaMap as $key => $item) {
                if (!($item['required'] ?? false)) {
                    continue;
                }

                $value = $map[$key] ?? null;
                if ($value === null || $value === '') {
                    $errors[] = "Key {$key} is required";
                }
            }
        }

        foreach ($map as $key => $value) {
            $valueType = $schemaMap[$key]['valueType'] ?? 'string';
            if ($valueType === 'number' && $this->isInvalidNumericValue($value)) {
                $errors[] = "Key {$key} must be numeric";
            }
        }

        return $errors === [] ? ['ok' => true] : ['ok' => false, 'errors' => $errors];
    }

    /**
     * Decode, normalize, then validate key-value payload.
     *
     * Fixed mode strips unknown keys during normalize (strip-first contract).
     *
     * @return array<string, mixed>
     */
    public function processValue(mixed $value, array $config): array
    {
        $map = $this->decodeValue($value);
        $normalized = $this->normalizeMap($map, $config);
        $validation = $this->validateMap($normalized, $config);

        if (!$validation['ok']) {
            throw new \InvalidArgumentException(implode('; ', $validation['errors'] ?? []));
        }

        return $normalized;
    }

    /**
     * @return array<string, array> field key => parsed config
     */
    public function getKeyValueFieldsForClass(string $modelClass): array
    {
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
            $map[$key] = $this->parseConfig($field->get('key_value_config'), $key);
        }

        $this->fieldsByClass[$modelClass] = $map;

        return $map;
    }

    /**
     * @return array<string, array{key: string, label: string, valueType: string, required: bool}>
     */
    private function getSchemaMap(array $config): array
    {
        $schema = [];
        foreach ($config['keys'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $schema[$key] = [
                'key' => $key,
                'label' => trim((string)($item['label'] ?? '')),
                'valueType' => ($item['valueType'] ?? 'string') === 'number' ? 'number' : 'string',
                'required' => (bool)($item['required'] ?? false),
            ];
        }

        return $schema;
    }

    private function resolveMode(array $config): string
    {
        return ($config['mode'] ?? '') === 'free' ? 'free' : 'fixed';
    }

    private function isInvalidNumericValue(mixed $value): bool
    {
        return $value !== null && $value !== '' && !is_numeric($value);
    }

    private function normalizeCellValue(mixed $rawValue, string $valueType): mixed
    {
        if (is_string($rawValue)) {
            $value = trim($rawValue);
        } elseif (is_scalar($rawValue) || $rawValue === null) {
            $value = trim((string)$rawValue);
        } else {
            $value = trim((string)json_encode($rawValue, JSON_UNESCAPED_UNICODE));
        }

        if ($valueType === 'number') {
            return $this->castNumericValue($value);
        }

        return $value;
    }

    private function castNumericValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return '';
        }

        $float = filter_var($stringValue, FILTER_VALIDATE_FLOAT);
        if ($float === false) {
            return $value;
        }

        return $float == (int)$float ? (int)$float : $float;
    }
}
