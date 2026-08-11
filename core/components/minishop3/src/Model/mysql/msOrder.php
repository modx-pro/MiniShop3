<?php

namespace MiniShop3\Model\mysql;

class msOrder extends \MiniShop3\Model\msOrder
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_orders',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'user_id' => 0,
                'customer_id' => 0,
                'token' => '',
                'uuid' => '',
                'idempotency_key' => null,
                'createdon' => null,
                'updatedon' => null,
                'num' => null,
                'cost' => 0.0,
                'cart_cost' => 0.0,
                'delivery_cost' => 0.0,
                'weight' => 0.0,
                'status_id' => 0,
                'delivery_id' => 0,
                'payment_id' => 0,
                'context' => 'web',
                'order_comment' => null,
                'properties' => null,
            ],
        'fieldMeta' =>
            [
                'user_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'customer_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'token' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '128',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'uuid' =>
                    [
                        'dbtype' => 'char',
                        'precision' => '36',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'idempotency_key' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '128',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => null,
                    ],
                'createdon' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'updatedon' =>
                    [
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ],
                'num' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '20',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => null,
                    ],
                'cost' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'cart_cost' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'delivery_cost' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'weight' =>
                    [
                        'dbtype' => 'decimal',
                        'precision' => '13,3',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ],
                'status_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'delivery_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'payment_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'context' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => 'web',
                    ],
                'order_comment' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'properties' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'json',
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
                'customer_id' =>
                    [
                        'alias' => 'customer_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'customer_id' =>
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
                        'unique' => false,
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
                'uuid' =>
                    [
                        'alias' => 'uuid',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'uuid' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'idempotency_key' =>
                    [
                        'alias' => 'idempotency_key',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'idempotency_key' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => true,
                                    ],
                            ],
                    ],
                'num' =>
                    [
                        'alias' => 'num',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'num' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => true,
                                    ],
                            ],
                    ],
                'status_id' =>
                    [
                        'alias' => 'status_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'status_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
            ],
        'composites' =>
            [
                'Address' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrderAddress',
                        'local' => 'id',
                        'foreign' => 'order_id',
                        'cardinality' => 'one',
                        'owner' => 'local',
                    ],
                'Products' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrderProduct',
                        'local' => 'id',
                        'foreign' => 'order_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
                'Log' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrderLog',
                        'local' => 'id',
                        'foreign' => 'order_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
            ],
        'aggregates' =>
            [
                'User' =>
                    [
                        'class' => 'MODX\\Revolution\\modUser',
                        'local' => 'user_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'UserProfile' =>
                    [
                        'class' => 'MODX\\Revolution\\modUserProfile',
                        'local' => 'user_id',
                        'foreign' => 'internalKey',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ],
                'Customer' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCustomer',
                        'local' => 'customer_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Status' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrderStatus',
                        'local' => 'status_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Delivery' =>
                    [
                        'class' => 'MiniShop3\\Model\\msDelivery',
                        'local' => 'delivery_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
                'Payment' =>
                    [
                        'class' => 'MiniShop3\\Model\\msPayment',
                        'local' => 'payment_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ],
            ],
    ];

}
