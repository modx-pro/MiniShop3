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
                'password' => null,
                'email_verified_at' => null,
                'is_active' => 1,
                'is_blocked' => 0,
                'failed_login_attempts' => 0,
                'blocked_until' => null,
                'created_at' => null,
                'updated_at' => null,
                'last_login_at' => null,
                'orders_count' => 0,
                'total_spent' => 0.00,
                'last_order_at' => null,
                'privacy_accepted_at' => null,
                'privacy_ip' => null,
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
                        'precision' => '128',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ],
                'password' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'email_verified_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'is_active' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 1,
                    ],
                'is_blocked' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 0,
                    ],
                'failed_login_attempts' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                    ],
                'blocked_until' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'created_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => false,
                        'default' => 'CURRENT_TIMESTAMP',
                    ],
                'updated_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'last_login_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'orders_count' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'default' => 0,
                    ],
                'total_spent' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => false,
                        'default' => 0.00,
                    ],
                'last_order_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'privacy_accepted_at' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'privacy_ip' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '45',
                        'phptype' => 'string',
                        'null' => true,
                    ],
            ],
        'indexes' =>
            [
                'user_id' =>
                    [
                        'alias' => 'user_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'user_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
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
        'composites' =>
            [
                'Tokens' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCustomerToken',
                        'local' => 'id',
                        'foreign' => 'customer_id',
                        'owner' => 'local',
                        'cardinality' => 'many',
                    ],
            ],
    ];
}
