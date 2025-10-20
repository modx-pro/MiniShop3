<?php

namespace MiniShop3\Model\mysql;

class msFieldConfigOverride extends \MiniShop3\Model\msFieldConfigOverride
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_field_config_overrides',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'page_key' => null,
            'field_name' => null,
            'hidden' => 0,
            'sort_order' => 0,
            'config' => null,
            'context_key' => 'web',
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'page_key' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'field_name' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'hidden' => [
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
            'config' => [
                'dbtype' => 'text',
                'phptype' => 'json',
                'null' => true,
            ],
            'context_key' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => false,
                'default' => 'web',
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
            'idx_page_field_context' => [
                'alias' => 'idx_page_field_context',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'page_key' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'field_name' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'context_key' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
        'composites' => [],
    ];
}
