<?php

namespace MiniShop3\Utils;

use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;
use PDO;
use PDOException;

class DBManager
{
    private modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function hasField(string $class, string $columnName): bool
    {
        if (!empty($class)) {
            try {
                $tableName = $this->modx->getTableName($class);
                if ($tableName) {
                    $stmt = $this->modx->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                    $stmt->execute([$columnName]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        return true;
                    }
                }
            } catch (PDOException $e) {
                return false;
            }
        }
        return false;
    }

    public function addField(msExtraField $field): bool
    {
        if ($this->addFieldToMap($field)) {
            return $this
                ->modx
                ->getManager()
                ->addField($field->get('class'), $field->get('key'));
        }

        return false;
    }

    private function addFieldToMap(msExtraField $field): bool
    {
        if ($field == null || !($field instanceof msExtraField)) {
            return false;
        }

        foreach (['class', 'key', 'dbtype', 'phptype'] as $required) {
            if (empty($field->get($required))) {
                return false;
            }
        }

        $className = $field->get('class');
        $columnName = $field->get('key');

        $meta = [];
        foreach (['dbtype', 'phptype', 'null', 'precision', 'attributes'] as $k) {
            $v = $field->get($k);
            if (!empty($v)) {
                $meta[$k] = $v;
            }
        }

        $default = $field->get('default');
        switch ($default) {
            case 'NULL':
                $meta['default'] = null;
                break;
            case 'CURRENT_TIMESTAMP':
                $meta['default'] = 'CURRENT_TIMESTAMP';
                break;
            case 'USER_DEFINED':
                $meta['default'] = $field->get('default_value');
                break;
            default:
                break;
        }

        $classMap = $this->modx->map[$className];
        $classMap['fields'][$columnName] = array_key_exists('default', $meta) ? $meta['default'] : null;
        $classMap['fieldMeta'][$columnName] = $meta;

        $this->modx->map[$className] = $classMap;

        return true;
    }
}
