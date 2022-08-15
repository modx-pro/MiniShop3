<?php

namespace MiniShop3\Model\mysql;

class msProduct extends \MiniShop3\Model\msProduct
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'extends' => 'MODX\\Revolution\\modResource',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'class_key' => 'MiniShop3\\Model\\msProduct',
            ),
        'fieldMeta' =>
            array(
                'class_key' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => 'MiniShop3\\Model\\msProduct',
                    ),
            ),
        'composites' =>
            array(
                'Data' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductData',
                        'local' => 'id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
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
                'Options' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductOption',
                        'local' => 'id',
                        'foreign' => 'product_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
        'aggregates' =>
            array(
                'Category' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategory',
                        'local' => 'parent',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
