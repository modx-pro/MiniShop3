<?php

namespace MiniShop3\Model\mysql;

class msCategory extends \MiniShop3\Model\msCategory
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
                'class_key' => 'MiniShop3\\Model\\msCategory',
            ),
        'fieldMeta' =>
            array(
                'class_key' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => 'MiniShop3\\Model\\msCategory',
                    ),
            ),
        'composites' =>
            array(
                'OwnProducts' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'id',
                        'foreign' => 'parent',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'AlienProducts' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategoryMember',
                        'local' => 'id',
                        'foreign' => 'category_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'CategoryOptions' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategoryOption',
                        'local' => 'id',
                        'foreign' => 'category_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
    );

}
