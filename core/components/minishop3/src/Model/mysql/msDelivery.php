<?php

namespace MiniShop3\Model\mysql;

class msDelivery extends \MiniShop3\Model\msDelivery
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_deliveries',
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
                'weight_price' => 0.0,
                'distance_price' => 0.0,
                'logo' => NULL,
                'position' => 0,
                'active' => 1,
                'class' => NULL,
                'properties' => NULL,
                'requires' => 'email,receiver',
                'free_delivery_amount' => 0.0,
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
                'weight_price' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
                'distance_price' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
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
                'requires' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => 'email,receiver',
                    ),
                'free_delivery_amount' =>
                    array(
                        'dbtype' => 'decimal',
                        'precision' => '12,2',
                        'phptype' => 'float',
                        'null' => true,
                        'default' => 0.0,
                    ),
            ),
        'aggregates' =>
            array(
                'Orders' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'id',
                        'foreign' => 'delivery_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'Payments' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msDeliveryMember',
                        'local' => 'id',
                        'foreign' => 'delivery_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
    );

}
