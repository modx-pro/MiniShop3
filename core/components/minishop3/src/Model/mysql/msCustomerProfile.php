<?php

namespace MiniShop3\Model\mysql;

class msCustomerProfile extends \MiniShop3\Model\msCustomerProfile
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_customer_profiles',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'account' => 0.0,
                'spent' => 0.0,
                'createdon' => 'CURRENT_TIMESTAMP',
                'referrer_id' => 0,
                'referrer_code' => '',
            ],
        'fieldMeta' =>
            [
                'account' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'spent' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'createdon' =>
                    [
                        'dbtype' => 'timestamp',
                        'phptype' => 'datetime',
                        'null' => true,
                        'default' => 'CURRENT_TIMESTAMP',
                    ],
                'referrer_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => true,
                        'default' => 0,
                        'index' => 'index',
                    ],
                'referrer_code' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                        'index' => 'index',
                    ],
            ],
        'indexes' =>
            [
                'referrer_id' =>
                    [
                        'alias' => 'referrer_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'referrer_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'referrer_code' =>
                    [
                        'alias' => 'referrer_code',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'referrer_code' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'spent' =>
                    [
                        'alias' => 'spent',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'spent' =>
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
                        'local' => 'id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ],
            ],
    ];
}
