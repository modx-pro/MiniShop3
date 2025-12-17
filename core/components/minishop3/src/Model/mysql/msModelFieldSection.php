<?php

namespace MiniShop3\Model\mysql;

/**
 * Class msModelFieldSection (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msModelFieldSection extends \MiniShop3\Model\msModelFieldSection
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_model_field_sections',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'model' => null,
            'section_key' => null,
            'label' => null,
            'lexicon_key' => null,
            'hidden' => 0,
            'sort_order' => 0,
            'is_default' => 0,
        ],
        'fieldMeta' => [
            'model' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'section_key' => [
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
            'is_default' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
        ],
        'indexes' => [
            'idx_model_section' => [
                'alias' => 'idx_model_section',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'model' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'section_key' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_model_sort' => [
                'alias' => 'idx_model_sort',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'model' => [
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
        ],
        'composites' => [
            'Fields' => [
                'class' => 'MiniShop3\\Model\\msModelField',
                'local' => 'id',
                'foreign' => 'section_id',
                'cardinality' => 'many',
                'owner' => 'local',
            ],
        ],
    ];
}
