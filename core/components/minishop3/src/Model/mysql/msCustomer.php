<?php

namespace MiniShop3\Model\mysql;

class msCustomer extends \MiniShop3\Model\msCustomer
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_customers',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'user_id' => 0,
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'token' => '',
            ],
        'fieldMeta' =>
            [
                'user_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'default' => 0,
                    ],
                'first_name' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'last_name' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'email' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'phone' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'token' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '32',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
            ],
        'indexes' =>
            [
                'first_name' =>
                    [
                        'alias' => 'first_name',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'first_name' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'last_name' =>
                    [
                        'alias' => 'last_name',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'last_name' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'email' =>
                    [
                        'alias' => 'email',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'email' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'phone' =>
                    [
                        'alias' => 'phone',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'phone' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'token' =>
                    [
                        'alias' => 'token',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'token' =>
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
                'User' =>
                    [
                        'class' => 'MODX\\Revolution\\modUser',
                        'local' => 'user_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ],
            ],
    ];
}
