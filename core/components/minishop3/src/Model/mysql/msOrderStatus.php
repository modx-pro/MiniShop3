<?php

namespace MiniShop3\Model\mysql;

class msOrderStatus extends \MiniShop3\Model\msOrderStatus
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_order_statuses',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'name' => null,
                'description' => null,
                'color' => '000000',
                'email_user' => 0,
                'email_manager' => 0,
                'subject_user' => '',
                'subject_manager' => '',
                'body_user' => 0,
                'body_manager' => 0,
                'active' => 1,
                'final' => 0,
                'fixed' => 0,
                'position' => 0,
                'editable' => 1,
            ],
        'fieldMeta' =>
            [
                'name' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'description' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'color' =>
                    [
                        'dbtype' => 'char',
                        'precision' => '6',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '000000',
                    ],
                'email_user' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'email_manager' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'subject_user' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'subject_manager' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'body_user' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => null,
                    ],
                'body_manager' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => null,
                    ],
                'active' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ],
                'final' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'fixed' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'position' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'editable' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ],
            ],
        'indexes' =>
            [
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
            ],
        'aggregates' =>
            [
                'Orders' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'id',
                        'foreign' => 'status_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
            ],
    ];
}
