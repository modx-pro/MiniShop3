<?php

namespace MiniShop3\Model\mysql;

class msPayment extends \MiniShop3\Model\msPayment
{    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_payments',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'name' => null,
                'description' => null,
                'price' => '0',
                'logo' => null,
                'position' => 0,
                'active' => 1,
                'class' => null,
                'properties' => null,
            ],
        'fieldMeta' =>
            [
                'name' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'description' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'price' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '11',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '0',
                    ],
                'logo' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'position' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'active' =>
                    [
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ],
                'class' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '50',
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
        'aggregates' =>
            [
                'Orders' =>
                    [
                        'class' => 'MiniShop3\\Model\\msOrder',
                        'local' => 'id',
                        'foreign' => 'payment_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
                'Deliveries' =>
                    [
                        'class' => 'MiniShop3\\Model\\msDeliveryMember',
                        'local' => 'id',
                        'foreign' => 'payment_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
            ],
    ];
}
