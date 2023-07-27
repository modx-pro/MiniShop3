<?php

namespace MiniShop3\Model\mysql;

class msVendor extends \MiniShop3\Model\msVendor
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_vendors',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'position' => 0,
                'name' => null,
                'resource_id' => 0,
                'country' => null,
                'logo' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
                'description' => null,
                'properties' => null,
            ],
        'fieldMeta' =>
            [
                'position' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'name' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                    ],
                'resource_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ],
                'country' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'logo' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'address' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'phone' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '20',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'email' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'description' =>
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
        'aggregates' =>
            [
                'Products' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'id',
                        'foreign' => 'vendor_id',
                        'cardinality' => 'many',
                        'owner' => 'foreign',
                    ],
                'Resource' =>
                    [
                        'class' => 'MODX\\Revolution\\modResource',
                        'local' => 'resource_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'local',
                    ],
            ],
    ];
}
