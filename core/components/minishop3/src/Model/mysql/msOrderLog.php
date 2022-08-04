<?php

namespace MiniShop3\Model\mysql;

class msOrderLog extends \MiniShop3\Model\msOrderLog
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_order_logs',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'user_id' => 0,
                'order_id' => 0,
                'timestamp' => NULL,
                'action' => '',
                'entry' => '0',
                'ip' => NULL,
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
                        'default' => 0,
                    ),
                'order_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                    ),
                'timestamp' =>
                    array(
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ),
                'action' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                    ),
                'entry' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '0',
                    ),
                'ip' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => false,
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
                'Order' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'order_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
            ),
    );

}
