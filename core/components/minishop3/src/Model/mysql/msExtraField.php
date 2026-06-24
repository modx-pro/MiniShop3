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
            'description' => null,
            'xtype' => null,
            'dbtype' => null,
            'precision' => null,
            'phptype' => null,
            'null' => 0,
            'default' => null,
            'default_value' => '',
            'attributes' => null,
            'index_type' => 'NONE',
            'active' => 0,
            'select_options' => null,
            'repeater_config' => null,
            'key_value_config' => null,
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
            'description' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'xtype' => [
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
            'default_value' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
                'default' => '',
            ],
            'attributes' => [
                'dbtype' => 'varchar',
                'precision' => '191',
                'phptype' => 'string',
                'null' => true,
            ],
            'index_type' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => true,
                'default' => 'NONE',
            ],
            'active' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => true,
                'default' => 0,
            ],
            'select_options' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
            'repeater_config' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
            'key_value_config' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
        ],
        'indexes' => [],
        'composites' => [],
    ];
}
