<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msExtraField
 *
 * @property string $class
 * @property string $key
 * @property string $label
 * @property string $description
 * @property string $xtype
 * @property string $dbtype
 * @property string $precision
 * @property string $phptype
 * @property bool $null
 * @property string $default
 * @property string $default_value
 * @property string $attributes
 * @property string $index_type
 * @property bool $active
 *
 * @package MiniShop3\Model
 */
class msExtraField extends xPDOSimpleObject
{
    /**
     * Получить тип индекса
     */
    public function getIndexType(): string
    {
        $indexType = $this->get('index_type');
        return !empty($indexType) ? $indexType : 'NONE';
    }

    /**
     * Проверить нужно ли создавать индекс
     */
    public function hasIndex(): bool
    {
        return $this->getIndexType() !== 'NONE';
    }

    /**
     * Получить имя индекса
     */
    public function getIndexName(): string
    {
        $indexType = $this->getIndexType();
        $key = $this->get('key');

        if ($indexType === 'UNIQUE') {
            return 'idx_unique_' . $key;
        }

        if ($indexType === 'FULLTEXT') {
            return 'idx_ft_' . $key;
        }

        return 'idx_' . $key;
    }
}
