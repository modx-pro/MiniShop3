<?php

namespace MiniShop3\Model\mysql;

class msExtraField extends \MiniShop3\Model\msExtraField
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_extra_fields',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'class' => null,
            'key' => null,
            'label' => null,
            'dbtype' => null,
            'precision' => null,
            'phptype' => null,
            'null' => 0,
            'default' => null,
            'attributes' => null,
            'active' => 0,
        ],
        'fieldMeta' => [
            'class' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'key' => [
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => true,
            ],
            'label' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'dbtype' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => true,
            ],
            'precision' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => true,
            ],
            'phptype' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => true,
            ],
            'null' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => true,
                'default' => 1,
            ],
            'default' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'attributes' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'active' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => true,
                'default' => 0,
            ]
        ],
        'indexes' => [],
        'composites' => [],
    ];
}
