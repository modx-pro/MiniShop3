<?php

namespace MiniShop3\Model\mysql;

class msOrderAddress extends \MiniShop3\Model\msOrderAddress
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_order_addresses',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'order_id' => NULL,
                'user_id' => NULL,
                'createdon' => NULL,
                'updatedon' => NULL,
                'receiver' => NULL,
                'phone' => NULL,
                'email' => NULL,
                'country' => NULL,
                'index' => NULL,
                'region' => NULL,
                'city' => NULL,
                'metro' => NULL,
                'street' => NULL,
                'building' => NULL,
                'entrance' => NULL,
                'floor' => NULL,
                'room' => NULL,
                'comment' => NULL,
                'text_address' => NULL,
                'properties' => NULL,
            ),
        'fieldMeta' =>
            array(
                'order_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ),
                'user_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
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
                'receiver' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'phone' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '20',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'email' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'country' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'index' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'region' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'city' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'metro' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'street' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'building' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '10',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'entrance' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '10',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'floor' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '10',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'room' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '10',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'comment' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'text_address' =>
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
                'order_id' =>
                    array(
                        'alias' => 'order_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'order_id' =>
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
                'Order' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'order_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
                'User' =>
                    array(
                        'class' => 'MODX\\Revolution\\modUser',
                        'local' => 'user_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
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
                        'foreign' => 'internalKey',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
            ),
    );

}
