<?php

namespace MiniShop3\Model\mysql;

class msProductOption extends \MiniShop3\Model\msProductOption
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_product_options',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'product_id' => null,
                'key' => null,
                'value' => '',
            ],
        'fieldMeta' =>
            [
                'product_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ],
                'key' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'value' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
            ],
        'indexes' =>
            [
                'product_id' =>
                    [
                        'alias' => 'product_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'product_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'key' =>
                    [
                        'alias' => 'key',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'key' =>
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
                'Product' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'product_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Option' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOption',
                        'local' => 'key',
                        'foreign' => 'key',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
            ],
    ];
}
