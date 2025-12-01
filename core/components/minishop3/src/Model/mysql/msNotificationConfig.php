<?php

namespace MiniShop3\Model\mysql;

class msNotificationConfig extends \MiniShop3\Model\msNotificationConfig
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_notification_configs',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'event' => null,
            'status_id' => null,
            'recipient_type' => null,
            'channel' => null,
            'enabled' => 1,
            'subject' => null,
            'template' => null,
            'delay' => 0,
            'config' => null,
            'position' => 0,
        ],
        'fieldMeta' => [
            'event' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'status_id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => true,
                'default' => null,
            ],
            'recipient_type' => [
                'dbtype' => 'varchar',
                'precision' => '20',
                'phptype' => 'string',
                'null' => false,
            ],
            'channel' => [
                'dbtype' => 'varchar',
                'precision' => '50',
                'phptype' => 'string',
                'null' => false,
            ],
            'enabled' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 1,
            ],
            'subject' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
            ],
            'template' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
            ],
            'delay' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ],
            'config' => [
                'dbtype' => 'text',
                'phptype' => 'json',
                'null' => true,
            ],
            'position' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ],
        ],
        'indexes' => [
            'event_status_recipient_channel' => [
                'alias' => 'event_status_recipient_channel',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'event' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'status_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => true,
                    ],
                    'recipient_type' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                    'channel' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'status_id' => [
                'alias' => 'status_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'status_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => true,
                    ],
                ],
            ],
            'enabled' => [
                'alias' => 'enabled',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'enabled' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
        'aggregates' => [
            'Status' => [
                'class' => 'MiniShop3\\Model\\msOrderStatus',
                'local' => 'status_id',
                'foreign' => 'id',
                'cardinality' => 'one',
                'owner' => 'foreign',
            ],
        ],
    ];
}
