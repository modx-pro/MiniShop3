<?php

namespace MiniShop3\Model\mysql;

class msOrderStatus extends \MiniShop3\Model\msOrderStatus
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_order_statuses',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'name' => NULL,
                'description' => NULL,
                'color' => '000000',
                'email_user' => 0,
                'email_manager' => 0,
                'subject_user' => '',
                'subject_manager' => '',
                'body_user' => 0,
                'body_manager' => 0,
                'active' => 1,
                'final' => 0,
                'fixed' => 0,
                'position' => 0,
                'editable' => 1,
            ),
        'fieldMeta' =>
            array(
                'name' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'color' =>
                    array(
                        'dbtype' => 'char',
                        'precision' => '6',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '000000',
                    ),
                'email_user' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'email_manager' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'subject_user' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'subject_manager' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'body_user' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'body_manager' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'active' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
                'final' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'fixed' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'position' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'editable' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
            ),
        'indexes' =>
            array(
                'active' =>
                    array(
                        'alias' => 'active',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'active' =>
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
                'Orders' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'id',
                        'foreign' => 'status_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
    );

}
