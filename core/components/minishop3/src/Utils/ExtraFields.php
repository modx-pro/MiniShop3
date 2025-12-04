<?php

namespace MiniShop3\Utils;

use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;
use PDO;
use PDOException;
use xPDO\xPDO;

class ExtraFields
{
    private modX $modx;

    private $cacheKey = 'extra_fields_meta';
    private $cacheOptions = [xPDO::OPT_CACHE_KEY => 'ms3'];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Adds extra fields to the xPDO map
     *
     * @return void
     */
    public function loadMap()
    {
        if (!$fieldsMeta = $this->modx->cacheManager->get($this->cacheKey, $this->cacheOptions)) {
            $fieldsMeta = [];
            $c = $this->modx->newQuery(msExtraField::class, ['active' => 1]);
            $fields = $this->modx->getIterator(msExtraField::class, $c);
            foreach ($fields as $field) {
                $fieldsMeta[] = $this->getFieldInfo($field);
            }
            $this->modx->cacheManager->add($this->cacheKey, $fieldsMeta, 0, $this->cacheOptions);
        }

        foreach ($fieldsMeta as $fieldMeta) {
            $this->addFieldToMap($fieldMeta);
        }
    }

    /**
     * Clears cache
     *
     * @return void
     */
    public function clearCache()
    {
        $this->modx->cacheManager->delete($this->cacheKey, $this->cacheOptions);
    }

    /**
     * Checks that a column exists in a table
     *
     * @param string $class
     * @param string $columnName
     * @return boolean
     */
    public function columnExists(string $class, string $columnName): bool
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

    /**
     * Adds a column to a table
     *
     * @param msExtraField $msExtraField
     * @return boolean
     */
    public function addColumn(msExtraField $msExtraField): bool
    {
        $meta = $this->getFieldInfo($msExtraField);
        if ($meta) {
            $this->addFieldToMap($meta);

            $xpdoManager = $this->modx->getManager();
            $result = $xpdoManager->addField($msExtraField->get('class'), $msExtraField->get('key'));

            if ($result && $msExtraField->hasIndex()) {
                $this->addIndex($msExtraField);
            }

            return $result;
        }

        return false;
    }

    /**
     * Adds an index to a column
     *
     * @param msExtraField $msExtraField
     * @return boolean
     */
    public function addIndex(msExtraField $msExtraField): bool
    {
        $class = $msExtraField->get('class');
        $key = $msExtraField->get('key');
        $indexType = $msExtraField->getIndexType();
        $indexName = $msExtraField->getIndexName();

        if ($indexType === 'NONE') {
            return true;
        }

        $xpdoManager = $this->modx->getManager();
        $tableName = $this->modx->getTableName($class);

        try {
            $indexDef = [
                'columns' => [$key => []],
            ];

            if ($indexType === 'UNIQUE') {
                $indexDef['unique'] = true;
            }

            if ($indexType === 'FULLTEXT') {
                $indexDef['type'] = 'FULLTEXT';
            }

            return $xpdoManager->addIndex($class, $indexName, $indexDef);

        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ExtraFields] Failed to add index '{$indexName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Removes an index from a column
     *
     * @param msExtraField $msExtraField
     * @return boolean
     */
    public function removeIndex(msExtraField $msExtraField): bool
    {
        if (!$msExtraField->hasIndex()) {
            return true;
        }

        $class = $msExtraField->get('class');
        $indexName = $msExtraField->getIndexName();

        $xpdoManager = $this->modx->getManager();

        try {
            return $xpdoManager->removeIndex($class, $indexName);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ExtraFields] Failed to remove index '{$indexName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds a field to the xPDO map
     *
     * @param array $meta
     * @return void
     */
    private function addFieldToMap(array $meta)
    {
        $class = $meta['class'];
        $field = $meta['field'];

        $classMap = $this->modx->map[$class];
        $classMap['fields'][$field] = $meta['default'];
        $classMap['fieldMeta'][$field] = $meta['meta'];

        $this->modx->map[$class] = $classMap;
    }

    /**
     * Prepares field info with metadata
     *
     * @param msExtraField $field
     * @return null|array
     */
    private function getFieldInfo(msExtraField $field): mixed
    {
        if ($field == null || !($field instanceof msExtraField)) {
            return null;
        }

        foreach (['class', 'key', 'dbtype', 'phptype'] as $required) {
            if (empty($field->get($required))) {
                return null;
            }
        }

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

        return [
            'class' => $field->get('class'),
            'field' => $field->get('key'),
            'default' => array_key_exists('default', $meta) ? $meta['default'] : null,
            'meta' => $meta
        ];
    }
}
