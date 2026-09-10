<?php

namespace MiniShop3\Model\mysql;

/**
 * Class msCustomerGroup (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msCustomerGroup extends \MiniShop3\Model\msCustomerGroup
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_customer_groups',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'name' => '',
            'user_group_id' => 0,
            'active' => 1,
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'name' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => false,
                'default' => '',
            ],
            'user_group_id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'attributes' => 'unsigned',
                'null' => false,
                'default' => 0,
            ],
            'active' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'attributes' => 'unsigned',
                'null' => false,
                'default' => 1,
            ],
            'created_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => true,
                'extra' => 'on update CURRENT_TIMESTAMP',
            ],
        ],
        'indexes' => [
            'user_group_id' => [
                'alias' => 'user_group_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'user_group_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'active' => [
                'alias' => 'active',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'active' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
        'aggregates' => [
            'Customers' => [
                'class' => 'MiniShop3\\Model\\msCustomer',
                'local' => 'id',
                'foreign' => 'customer_group_id',
                'cardinality' => 'many',
                'owner' => 'local',
            ],
        ],
    ];
}
