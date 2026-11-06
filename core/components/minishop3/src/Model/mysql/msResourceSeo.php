<?php

namespace MiniShop3\Model\mysql;

/**
 * Class msResourceSeo (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msResourceSeo extends \MiniShop3\Model\msResourceSeo
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_resource_seo',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'resource_id' => null,
            'title' => null,
            'description' => null,
            'canonical' => null,
            'robots' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ],
        'fieldMeta' => [
            'resource_id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'index' => 'pk',
            ],
            'title' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'description' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'canonical' => [
                'dbtype' => 'varchar',
                'precision' => '500',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'robots' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'og_title' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'og_description' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
            'og_image' => [
                'dbtype' => 'varchar',
                'precision' => '500',
                'phptype' => 'string',
                'null' => true,
                'default' => null,
            ],
        ],
        'indexes' => [
            'resource_id' => [
                'alias' => 'resource_id',
                'primary' => true,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [
                    'resource_id' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
    ];
}
