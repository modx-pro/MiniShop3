<?php

namespace MiniShop3\Model\mysql;

class msOrder extends \MiniShop3\Model\msOrder
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_orders',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'user_id' => NULL,
                'session_id' => '',
                'createdon' => NULL,
                'updatedon' => NULL,
                'num' => '',
                'cost' => 0.0,
                'cart_cost' => 0.0,
                'delivery_cost' => 0.0,
                'weight' => 0.0,
                'status_id' => 0,
                'delivery_id' => 0,
                'payment_id' => 0,
                'context' => 'web',
                'order_comment' => NULL,
                'properties' => NULL,
            ),
        'fieldMeta' =>
            array(
                'user_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ),
                'session_id' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '32',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                    ),
                'createdon' =>
                    array(
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ),
                'updatedon' =>
                    array(
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ),
                'num' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '20',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'cost' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'cart_cost' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'delivery_cost' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'weight' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '13,3',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'status_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'delivery_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'payment_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'context' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => 'web',
                    ),
                'order_comment' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'properties' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
            ),
        'indexes' =>
            array(
                'user_id' =>
                    array(
                        'alias' => 'user_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'user_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'session_id' =>
                    array(
                        'alias' => 'session_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'session_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'status_id' =>
                    array(
                        'alias' => 'status_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'status_id' =>
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
                'Address' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrderAddress',
                        'local' => 'address_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Products' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrderProduct',
                        'local' => 'id',
                        'foreign' => 'order_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'Log' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrderLog',
                        'local' => 'id',
                        'foreign' => 'order_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
        'aggregates' =>
            array(
                'User' =>
                    array(
                        'class' => 'MODX\\Revolution\\modUser',
                        'local' => 'user_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'UserProfile' =>
                    array(
                        'class' => 'MODX\\Revolution\\modUserProfile',
                        'local' => 'user_id',
                        'foreign' => 'internalKey',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
                'CustomerProfile' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCustomerProfile',
                        'local' => 'user_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
                'Status' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrderStatus',
                        'local' => 'status_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Delivery' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msDelivery',
                        'local' => 'delivery_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Payment' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msPayment',
                        'local' => 'payment_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
