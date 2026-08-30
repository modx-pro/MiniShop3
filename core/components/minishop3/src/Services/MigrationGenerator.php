<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;

class MigrationGenerator
{
    private modX $modx;
    private string $migrationsPath;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $corePath = defined('MODX_CORE_PATH') ? \MODX_CORE_PATH : (dirname(__DIR__, 2) . '/');
        $this->migrationsPath = $corePath . 'components/minishop3/migrations/';
    }

    /**
     * Generate migration for adding column
     */
    public function generateAddColumnMigration(msExtraField $field): string
    {
        $timestamp = date('YmdHis');
        $className = $this->generateClassName('Add', $field);
        $fileName = $timestamp . '_' . $this->toSnakeCase($className) . '.php';
        $filePath = $this->migrationsPath . $fileName;

        $template = $this->renderAddColumnTemplate($className, $field);

        file_put_contents($filePath, $template);

        $this->modx->log(modX::LOG_LEVEL_INFO, "[MigrationGenerator] Created migration: {$fileName}");

        return $filePath;
    }

    /**
     * Generate migration for dropping column
     */
    public function generateDropColumnMigration(msExtraField $field): string
    {
        $timestamp = date('YmdHis');
        $className = $this->generateClassName('Drop', $field);
        $fileName = $timestamp . '_' . $this->toSnakeCase($className) . '.php';
        $filePath = $this->migrationsPath . $fileName;

        $template = $this->renderDropColumnTemplate($className, $field);

        file_put_contents($filePath, $template);

        $this->modx->log(modX::LOG_LEVEL_INFO, "[MigrationGenerator] Created migration: {$fileName}");

        return $filePath;
    }

    /**
     * Migration template for adding column
     */
    private function renderAddColumnTemplate(string $className, msExtraField $field): string
    {
        $tableName = $this->resolveTableName($field->get('class'));
        $columnName = $field->get('key');
        $dbtype = $this->mapDbTypeToPhinx($field->get('dbtype'));
        $precision = $this->parsePrecision($field);
        $nullable = $field->get('null') ? 'true' : 'false';
        $default = $this->getDefaultValue($field);
        $attributes = $this->parseAttributes($field);
        $comment = addslashes($field->get('label') ?: $field->get('key'));

        // Generate index code
        $indexCode = '';
        if ($field->hasIndex()) {
            $indexName = $field->getIndexName();
            $indexType = $field->getIndexType();

            if ($indexType === 'UNIQUE') {
                $indexCode = "\n        ->addIndex(['{$columnName}'], ['unique' => true, 'name' => '{$indexName}'])";
            } elseif ($indexType === 'FULLTEXT') {
                $indexCode = "\n        ->addIndex(['{$columnName}'], ['type' => 'fulltext', 'name' => '{$indexName}'])";
            } else { // INDEX
                $indexCode = "\n        ->addIndex(['{$columnName}'], ['name' => '{$indexName}'])";
            }
        }

        return <<<PHP
<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Auto-generated migration for adding '{$columnName}' to '{$tableName}'
 * Generated: {date('Y-m-d H:i:s')}
 * Extra Field ID: {$field->get('id')}
 */
final class {$className} extends AbstractMigration
{
    public function change(): void
    {
        \$table = \$this->table('{$tableName}');

        \$table->addColumn('{$columnName}', '{$dbtype}', [
            {$precision}
            'null' => {$nullable},
            {$default}
            {$attributes}
            'comment' => '{$comment}',
        ]){$indexCode}
        ->update();
    }
}
PHP;
    }

    /**
     * Migration template for dropping column
     */
    private function renderDropColumnTemplate(string $className, msExtraField $field): string
    {
        $tableName = $this->resolveTableName($field->get('class'));
        $columnName = $field->get('key');
        $indexName = $field->hasIndex() ? $field->getIndexName() : '';

        // If index exists, remove it before dropping column
        $removeIndexCode = '';
        if ($indexName) {
            $removeIndexCode = "\$table->removeIndexByName('{$indexName}');\n        ";
        }

        return <<<PHP
<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Auto-generated migration for dropping '{$columnName}' from '{$tableName}'
 * Generated: {date('Y-m-d H:i:s')}
 * Extra Field ID: {$field->get('id')}
 */
final class {$className} extends AbstractMigration
{
    public function change(): void
    {
        \$table = \$this->table('{$tableName}');
        {$removeIndexCode}\$table->removeColumn('{$columnName}')
              ->update();
    }
}
PHP;
    }

    /**
     * Generate migration class name
     */
    private function generateClassName(string $action, msExtraField $field): string
    {
        $fieldName = $this->toCamelCase($field->get('key'));
        $tableName = $this->getTableShortName($field->get('class'));

        return "{$action}{$fieldName}To{$tableName}";
    }

    /**
     * Map database types from xPDO to Phinx
     */
    private function mapDbTypeToPhinx(string $dbtype): string
    {
        $map = [
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'mediumint' => 'integer',
            'int' => 'integer',
            'bigint' => 'biginteger',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'decimal',
            'varchar' => 'string',
            'char' => 'char',
            'text' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'tinytext' => 'text',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'year' => 'integer',
            'enum' => 'enum',
            'set' => 'set',
            'json' => 'json',
            'blob' => 'blob',
        ];

        return $map[strtolower($dbtype)] ?? 'string';
    }

    /**
     * Parse precision (length/scale)
     */
    private function parsePrecision(msExtraField $field): string
    {
        $precision = $field->get('precision');
        $dbtype = strtolower($field->get('dbtype'));

        if (empty($precision)) {
            return '';
        }

        // For decimal: precision = "12,2" → limit: 12, scale: 2
        if (in_array($dbtype, ['decimal', 'float', 'double']) && str_contains($precision, ',')) {
            [$limit, $scale] = explode(',', $precision);
            return "'limit' => " . trim($limit) . ", 'scale' => " . trim($scale) . ",\n            ";
        }

        // For varchar, char: precision = "255" → limit: 255
        if (in_array($dbtype, ['varchar', 'char', 'int', 'tinyint', 'smallint'])) {
            return "'limit' => " . intval($precision) . ",\n            ";
        }

        return '';
    }

    /**
     * Get default value
     */
    private function getDefaultValue(msExtraField $field): string
    {
        $defaultType = $field->get('default');

        switch ($defaultType) {
            case 'NULL':
                return "'default' => null,\n            ";
            case 'CURRENT_TIMESTAMP':
                return "'default' => 'CURRENT_TIMESTAMP',\n            ";
            case 'USER_DEFINED':
                $value = $field->get('default_value');
                if (is_numeric($value)) {
                    return "'default' => {$value},\n            ";
                }
                return "'default' => '" . addslashes($value) . "',\n            ";
            default:
                return '';
        }
    }

    /**
     * Parse attributes (unsigned, auto_increment)
     */
    private function parseAttributes(msExtraField $field): string
    {
        $attributes = $field->get('attributes');

        if (empty($attributes)) {
            return '';
        }

        $result = [];

        if (str_contains(strtolower($attributes), 'unsigned')) {
            $result[] = "'signed' => false";
        }

        if (str_contains(strtolower($attributes), 'auto_increment')) {
            $result[] = "'identity' => true";
        }

        return !empty($result) ? implode(', ', $result) . ",\n            " : '';
    }

    /**
     * Whether the model class has its own DB table (not STI / modResource inheritance).
     */
    public function canHostExtraField(string $class): bool
    {
        return $this->ownTableName($class) !== null;
    }

    /**
     * Unprefixed, unquoted table name for Phinx (table_prefix is applied in phinx.php).
     */
    public function resolveTableName(string $class): string
    {
        $table = $this->ownTableName($class);
        if ($table === null) {
            throw new \InvalidArgumentException("Model class cannot host extra fields: {$class}");
        }

        return $table;
    }

    /**
     * Own logical table from mysql metaMap, or null when the class inherits another table.
     */
    private function ownTableName(string $class): ?string
    {
        $mysqlClass = str_replace('\\Model\\', '\\Model\\mysql\\', $class);
        if (!str_starts_with($mysqlClass, 'MiniShop3\\Model\\mysql\\') || !class_exists($mysqlClass)) {
            return null;
        }

        $table = $mysqlClass::$metaMap['table'] ?? null;
        if (!is_string($table) || $table === '') {
            return null;
        }

        $table = trim($table, '`');
        if (str_contains($table, '.')) {
            $parts = explode('.', $table);
            $table = (string) end($parts);
        }

        if ($table === '' || str_contains($table, '`')) {
            return null;
        }

        return $table;
    }

    /**
     * Get short table name for migration class
     */
    private function getTableShortName(string $class): string
    {
        $table = $this->resolveTableName($class);
        $base = preg_replace('/^ms3_/', '', $table) ?? $table;

        return $this->toCamelCase($base);
    }

    private function toCamelCase(string $str): string
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    private function toSnakeCase(string $str): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }
}
