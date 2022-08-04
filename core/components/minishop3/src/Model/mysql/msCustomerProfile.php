<?php

namespace MiniShop3\Model\mysql;

class msCustomerProfile extends \MiniShop3\Model\msCustomerProfile
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_customer_profiles',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'id' => NULL,
                'account' => 0.0,
                'spent' => 0.0,
                'createdon' => 'CURRENT_TIMESTAMP',
                'referrer_id' => 0,
                'referrer_code' => '',
            ),
        'fieldMeta' =>
            array(
                'id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => false,
                        'index' => 'pk',
                    ),
                'account' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'spent' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'createdon' =>
                    array(
                        'dbtype' => 'timestamp',
                        'phptype' => 'datetime',
                        'null' => true,
                        'default' => 'CURRENT_TIMESTAMP',
                    ),
                'referrer_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'attributes' => 'unsigned',
                        'null' => true,
                        'default' => 0,
                        'index' => 'index',
                    ),
                'referrer_code' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                        'index' => 'index',
                    ),
            ),
        'indexes' =>
            array(
                'id' =>
                    array(
                        'alias' => 'id',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'referrer_id' =>
                    array(
                        'alias' => 'referrer_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'referrer_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'referrer_code' =>
                    array(
                        'alias' => 'referrer_code',
                        'primary' => false,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'referrer_code' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'spent' =>
                    array(
                        'alias' => 'spent',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'spent' =>
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
                        'local' => 'id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
            ),
    );

}
