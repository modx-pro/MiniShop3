<?php

namespace MiniShop3\Model\mysql;

use xPDO\xPDO;

class msGridField extends \MiniShop3\Model\msGridField
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_grid_fields',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'grid_key' => null,
            'field_name' => null,
            'label' => null,
            'lexicon_key' => null,
            'visible' => 1,
            'sort_order' => 0,
            'sortable' => 1,
            'filterable' => 0,
            'frozen' => 0,
            'width' => null,
            'min_width' => null,
            'config' => null,
            'is_system' => 0,
            'is_default' => 1,
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'grid_key' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => false,
            ],
            'field_name' => [
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
            'lexicon_key' => [
                'dbtype' => 'varchar',
                'precision' => '255',
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
            'sort_order' => [
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ],
            'sortable' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 1,
            ],
            'filterable' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'frozen' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'width' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => true,
            ],
            'min_width' => [
                'dbtype' => 'varchar',
                'precision' => '50',
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
                'default' => 1,
            ],
            'created_at' => [
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
                'extra' => 'on update CURRENT_TIMESTAMP',
            ],
        ],
        'indexes' => [
            'idx_grid_field' => [
                'alias' => 'idx_grid_field',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'grid_key' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'field_name' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_grid_key' => [
                'alias' => 'idx_grid_key',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'grid_key' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_visible' => [
                'alias' => 'idx_visible',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'visible' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
    ];
}
