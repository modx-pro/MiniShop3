<?php

namespace MiniShop3\Model\mysql;

class msProductData extends \MiniShop3\Model\msProductData
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_products',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'article' => NULL,
                'price' => 0.0,
                'old_price' => 0.0,
                'weight' => 0.0,
                'image' => NULL,
                'thumb' => NULL,
                'vendor_id' => 0,
                'made_in' => '',
                'new' => 0,
                'popular' => 0,
                'favorite' => 0,
                'tags' => NULL,
                'color' => NULL,
                'size' => NULL,
                'source' => 1,
            ),
        'fieldMeta' =>
            array(
                'article' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'price' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'old_price' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'weight' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '13,3',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'image' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'thumb' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'vendor_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'made_in' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'new' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ),
                'popular' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ),
                'favorite' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 0,
                    ),
                'tags' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
                'color' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
                'size' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
                'source' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
            ),
        'indexes' =>
            array(
                'article' =>
                    array(
                        'alias' => 'article',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'article' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'price' =>
                    array(
                        'alias' => 'price',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'price' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'old_price' =>
                    array(
                        'alias' => 'old_price',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'old_price' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'vendor_id' =>
                    array(
                        'alias' => 'vendor_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'vendor_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'new' =>
                    array(
                        'alias' => 'new',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'new' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'favorite' =>
                    array(
                        'alias' => 'favorite',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'favorite' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'popular' =>
                    array(
                        'alias' => 'popular',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'popular' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'made_in' =>
                    array(
                        'alias' => 'made_in',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'made_in' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
            ),
        'composites' =>
            array(
                'Options' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductOption',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'Files' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductFile',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'Categories' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategoryMember',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
        'aggregates' =>
            array(
                'Product' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Vendor' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msVendor',
                        'local' => 'vendor_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
