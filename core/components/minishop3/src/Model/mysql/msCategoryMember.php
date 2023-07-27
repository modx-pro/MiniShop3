<?php

namespace MiniShop3\Model\mysql;

class msCategoryMember extends \MiniShop3\Model\msCategoryMember
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_product_categories',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'product_id' => null,
                'category_id' => null,
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
                        'index' => 'pk',
                    ],
                'category_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                        'index' => 'pk',
                    ],
            ],
        'indexes' =>
            [
                'product' =>
                    [
                        'alias' => 'product',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'product_id' =>
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
                'Category' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCategory',
                        'local' => 'category_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
            ],
    ];
}
