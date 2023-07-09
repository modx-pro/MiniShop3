<?php

namespace MiniShop3\Model\mysql;

class msProductData extends \MiniShop3\Model\msProductData
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_products',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'article' => null,
                'price' => 0.0,
                'old_price' => 0.0,
                'weight' => 0.0,
                'image' => null,
                'thumb' => null,
                'vendor_id' => 0,
                'made_in' => '',
                'new' => 0,
                'popular' => 0,
                'favorite' => 0,
                'tags' => null,
                'color' => null,
                'size' => null,
                'source_id' => 1,
            ],
        'fieldMeta' =>
            [
                'article' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'price' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'old_price' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'weight' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '13,3',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'image' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'thumb' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'vendor_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'made_in' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'new' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ],
                'popular' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ],
                'favorite' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ],
                'tags' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ],
                'color' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ],
                'size' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ],
                'source_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ],
            ],
        'indexes' =>
            [
                'article' =>
                    [
                        'alias' => 'article',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'article' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'price' =>
                    [
                        'alias' => 'price',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'price' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'old_price' =>
                    [
                        'alias' => 'old_price',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'old_price' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'vendor_id' =>
                    [
                        'alias' => 'vendor_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'vendor_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'new' =>
                    [
                        'alias' => 'new',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'new' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'favorite' =>
                    [
                        'alias' => 'favorite',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'favorite' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'popular' =>
                    [
                        'alias' => 'popular',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'popular' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'made_in' =>
                    [
                        'alias' => 'made_in',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'made_in' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
            ],
        'composites' =>
            [
                'Options' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProductOption',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
                'Files' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProductFile',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
                'Categories' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCategoryMember',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
            ],
        'aggregates' =>
            [
                'Product' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Vendor' =>
                    [
                        'class' => 'MiniShop3\\Model\\msVendor',
                        'local' => 'vendor_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
            ],
    ];
}
