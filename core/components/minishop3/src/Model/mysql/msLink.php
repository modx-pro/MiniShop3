<?php

namespace MiniShop3\Model\mysql;

class msLink extends \MiniShop3\Model\msLink
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_links',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'type' => NULL,
                'name' => NULL,
                'description' => NULL,
            ),
        'fieldMeta' =>
            array(
                'type' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'name' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
            ),
        'indexes' =>
            array(
                'type' =>
                    array(
                        'alias' => 'type',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'type' =>
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
                'Links' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductLink',
                        'local' => 'id',
                        'foreign' => 'link',
                        'owner' => 'local',
                        'cardinality' => 'many',
                    ),
            ),
    );

}
