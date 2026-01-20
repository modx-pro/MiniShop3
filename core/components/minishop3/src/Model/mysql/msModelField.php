<?php

namespace MiniShop3\Model\mysql;

/**
 * Class msModelField (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msModelField extends \MiniShop3\Model\msModelField
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_model_fields',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'model' => null,
            'name' => null,
            'label' => null,
            'xtype' => 'textfield',
            'visible' => 1,
            'required' => 0,
            'sort_order' => 0,
            'section_id' => null,
            'width' => 6,
            'placeholder' => null,
            'description' => null,
            'config' => null,
        ],
        'fieldMeta' => [
            'model' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
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
            'section_id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'null' => true,
            ],
            'width' => [
                'dbtype' => 'tinyint',
                'precision' => '2',
                'phptype' => 'integer',
                'null' => false,
                'default' => 6,
            ],
            'placeholder' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
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
        ],
        'indexes' => [
            'idx_model_name' => [
                'alias' => 'idx_model_name',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'model' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'name' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_model_visible_sort_order' => [
                'alias' => 'idx_model_visible_sort_order',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'model' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'visible' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'sort_order' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_section_id' => [
                'alias' => 'idx_section_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'section_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => true,
                    ],
                ],
            ],
        ],
        'aggregates' => [
            'Section' => [
                'class' => 'MiniShop3\\Model\\msModelFieldSection',
                'local' => 'section_id',
                'foreign' => 'id',
                'owner' => 'foreign',
                'cardinality' => 'one',
            ],
        ],
    ];
}
