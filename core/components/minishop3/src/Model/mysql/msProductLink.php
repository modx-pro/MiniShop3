<?php

namespace MiniShop3\Model\mysql;

class msProductLink extends \MiniShop3\Model\msProductLink
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_product_links',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'link' => NULL,
                'master' => NULL,
                'slave' => NULL,
            ),
        'fieldMeta' =>
            array(
                'link' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'index' => 'pk',
                    ),
                'master' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'index' => 'pk',
                    ),
                'slave' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'index' => 'pk',
                    ),
            ),
        'indexes' =>
            array(
                'type' =>
                    array(
                        'alias' => 'link',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'link' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                                'master' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                                'slave' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
            ),
        'aggregates' =>
            array(
                'Link' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msLink',
                        'local' => 'link',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
                'Master' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'master',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
                'Slave' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'slave',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
            ),
    );

}
