<?php

namespace MiniShop3\Model\mysql;

use xPDO\xPDO;

class msCustomerToken extends \MiniShop3\Model\msCustomerToken
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_customer_tokens',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'customer_id' => 0,
            'token' => '',
            'type' => 'api',
            'expires_at' => null,
            'created_at' => null,
            'used_at' => null,
        ],
        'fieldMeta' => [
            'customer_id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'attributes' => 'unsigned',
                'null' => false,
                'default' => 0,
            ],
            'token' => [
                'dbtype' => 'varchar',
                'precision' => '128',
                'phptype' => 'string',
                'null' => false,
                'default' => '',
            ],
            'type' => [
                'dbtype' => 'enum',
                'precision' => "'api','refresh','magic_link','email_verification'",
                'phptype' => 'string',
                'null' => false,
                'default' => 'api',
            ],
            'expires_at' => [
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ],
            'created_at' => [
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'used_at' => [
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ],
        ],
        'indexes' => [
            'idx_token_unique' => [
                'alias' => 'idx_token_unique',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'token' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_customer_type' => [
                'alias' => 'idx_customer_type',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'customer_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'type' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'idx_expires' => [
                'alias' => 'idx_expires',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'expires_at' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
        'aggregates' => [
            'Customer' => [
                'class' => 'MiniShop3\\Model\\msCustomer',
                'local' => 'customer_id',
                'foreign' => 'id',
                'owner' => 'foreign',
                'cardinality' => 'one',
            ],
        ],
    ];
}
