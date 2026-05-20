<?php

namespace MiniShop3\Model\mysql;

/**
 * Class msOptionGroup (MySQL)
 *
 * @package MiniShop3\Model\mysql
 */
class msOptionGroup extends \MiniShop3\Model\msOptionGroup
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_option_groups',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => [
            'engine' => 'InnoDB',
        ],
        'fields' => [
            'name' => '',
            'description' => null,
            'sort_order' => 0,
            'created_at' => null,
            'updated_at' => null,
        ],
        'fieldMeta' => [
            'name' => [
                'dbtype' => 'varchar',
                'precision' => '255',
                'phptype' => 'string',
                'null' => false,
                'default' => '',
            ],
            'description' => [
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ],
            'sort_order' => [
                'dbtype' => 'int',
                'precision' => '10',
                'phptype' => 'integer',
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
            'idx_sort_order' => [
                'alias' => 'idx_sort_order',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'sort_order' => [
                        'length' => '',
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
        'aggregates' => [
            // Group does NOT own its options — defining this as `composites` would
            // make $group->remove() cascade-delete every msOption in the group,
            // which contradicts the documented behavior: deleting a group should
            // detach its options (option_group_id = NULL), not destroy them.
            // OptionGroupsController calls detachOptionsFromGroup() explicitly before remove().
            'Options' => [
                'class' => 'MiniShop3\\Model\\msOption',
                'local' => 'id',
                'foreign' => 'option_group_id',
                'cardinality' => 'many',
                'owner' => 'local',
            ],
        ],
    ];
}
