<?php

namespace MiniShop3\Model\mysql;

class msCategoryOption extends \MiniShop3\Model\msCategoryOption
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_category_options',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'option_id' => 0,
                'category_id' => 0,
                'position' => 0,
                'active' => 0,
                'required' => 0,
                'value' => null,
            ],
        'fieldMeta' =>
            [
                'option_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'pk',
                    ],
                'category_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'pk',
                    ],
                'position' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ],
                'active' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ],
                'required' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ],
                'value' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                        'index' => 'fulltext',
                    ],
            ],
        'indexes' =>
            [
                'PRIMARY' =>
                    [
                        'alias' => 'PRIMARY',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'option_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                                'category_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'position' =>
                    [
                        'alias' => 'position',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'position' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'active' =>
                    [
                        'alias' => 'active',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'active' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'required' =>
                    [
                        'alias' => 'required',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'required' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'value_ft' =>
                    [
                        'alias' => 'value_ft',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'FULLTEXT',
                        'columns' =>
                            [
                                'value' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
            ],
        'aggregates' =>
            [
                'Category' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCategory',
                        'local' => 'category_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Option' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOption',
                        'local' => 'option_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
            ],
    ];
}
