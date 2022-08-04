<?php

namespace MiniShop3\Model\mysql;

class msPayment extends \MiniShop3\Model\msPayment
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_payments',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'name' => NULL,
                'description' => NULL,
                'price' => '0',
                'logo' => NULL,
                'position' => 0,
                'active' => 1,
                'class' => NULL,
                'properties' => NULL,
            ),
        'fieldMeta' =>
            array(
                'name' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'price' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '11',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '0',
                    ),
                'logo' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'position' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'active' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
                'class' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '50',
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
        'aggregates' =>
            array(
                'Orders' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'id',
                        'foreign' => 'payment_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'Deliveries' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msDeliveryMember',
                        'local' => 'id',
                        'foreign' => 'payment_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
    );

}
