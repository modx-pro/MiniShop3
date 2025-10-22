<?php

namespace MiniShop3\Model\mysql;

use xPDO\xPDO;

/**
 * Class msProductField (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msProductField extends \MiniShop3\Model\msProductField
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_product_fields',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'id' => null,
            'name' => null,
            'label' => null,
            'xtype' => 'textfield',
            'section' => null,
            'visible' => 1,
            'required' => 0,
            'sort_order' => 0,
            'width' => 4,
            'description' => null,
            'config' => null,
            'is_system' => 0,
            'is_default' => 0,
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'index' => 'pk',
                'generated' => 'native',
                'extra' => 'auto_increment',
            ],
            'name' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'label' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
            ],
            'xtype' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => false,
                'default' => 'textfield',
            ],
            'section' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => true,
            ],
            'visible' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 1,
            ],
            'required' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'sort_order' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ],
            'width' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'null' => false,
                'default' => 4,
            ],
            'description' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
            'config' => [
                'dbtype' => 'text',
                'phptype' => 'json',
                'null' => true,
            ],
            'is_system' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'is_default' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'created_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => true,
                'extra' => 'on update CURRENT_TIMESTAMP',
            ],
        ],
        'indexes' => [
            'PRIMARY' => [
                'alias' => 'PRIMARY',
                'primary' => true,
                'unique' => true,
                'columns' => ['id'],
            ],
            'idx_unique_name' => [
                'alias' => 'idx_unique_name',
                'primary' => false,
                'unique' => true,
                'columns' => ['name'],
            ],
            'idx_section' => [
                'alias' => 'idx_section',
                'primary' => false,
                'unique' => false,
                'columns' => ['section'],
            ],
            'idx_visible_sort' => [
                'alias' => 'idx_visible_sort',
                'primary' => false,
                'unique' => false,
                'columns' => ['visible', 'sort_order'],
            ],
        ],
    ];
}
