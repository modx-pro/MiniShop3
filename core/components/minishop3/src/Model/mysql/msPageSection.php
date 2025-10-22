<?php

namespace MiniShop3\Model\mysql;

use xPDO\xPDO;

/**
 * Class msPageSection (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msPageSection extends \MiniShop3\Model\msPageSection
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_page_sections',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'id' => null,
            'page_key' => null,
            'section_key' => null,
            'hidden' => 0,
            'sort_order' => 0,
            'config' => null,
            'is_default' => 0,
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'id' => [
                'dbtype' => 'int',
                'precision' => '10',
                'attributes' => 'unsigned',
                'phptype' => 'integer',
                'null' => false,
                'index' => 'pk',
                'generated' => 'native',
                'extra' => 'auto_increment',
            ],
            'page_key' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'section_key' => [
                'dbtype' => 'varchar',
                'precision' => '100',
                'phptype' => 'string',
                'null' => false,
            ],
            'hidden' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'sort_order' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ],
            'config' => [
                'dbtype' => 'text',
                'phptype' => 'json',
                'null' => true,
            ],
            'is_default' => [
                'dbtype' => 'tinyint',
                'precision' => '1',
                'phptype' => 'boolean',
                'null' => false,
                'default' => 0,
            ],
            'created_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'dbtype' => 'timestamp',
                'phptype' => 'timestamp',
                'null' => true,
                'extra' => 'on update CURRENT_TIMESTAMP',
            ],
        ],
        'indexes' => [
            'PRIMARY' => [
                'alias' => 'PRIMARY',
                'primary' => true,
                'unique' => true,
                'columns' => ['id'],
            ],
            'idx_page_section' => [
                'alias' => 'idx_page_section',
                'primary' => false,
                'unique' => true,
                'columns' => ['page_key', 'section_key'],
            ],
            'idx_page_key' => [
                'alias' => 'idx_page_key',
                'primary' => false,
                'unique' => false,
                'columns' => ['page_key'],
            ],
        ],
    ];
}
