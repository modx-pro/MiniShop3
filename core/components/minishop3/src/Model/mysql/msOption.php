<?php

namespace MiniShop3\Model\mysql;

class msOption extends \MiniShop3\Model\msOption
{
    public static $metaMap = [
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms3_options',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            [
                'engine' => 'InnoDB',
            ],
        'fields' =>
            [
                'key' => '',
                'caption' => '',
                'description' => null,
                'measure_unit' => null,
                'category_id' => null,
                'type' => '',
                'properties' => null,
            ],
        'fieldMeta' =>
            [
                'key' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'index',
                    ],
                'caption' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'fulltext',
                    ],
                'description' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'measure_unit' =>
                    [
                        'dbtype' => 'tinytext',
                        'phptype' => 'string',
                        'null' => true,
                    ],
                'category_id' =>
                    [
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ],
                'type' =>
                    [
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'index',
                    ],
                'properties' =>
                    [
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ],
            ],
        'indexes' =>
            [
                'key' =>
                    [
                        'alias' => 'type',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'type' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'caption_ft' =>
                    [
                        'alias' => 'caption_ft',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'FULLTEXT',
                        'columns' =>
                            [
                                'caption' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
                'category_id' =>
                    [
                        'alias' => 'category_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            [
                                'category_id' =>
                                    [
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ],
                            ],
                    ],
            ],
        'composites' =>
            [
                'OptionCategories' =>
                    [
                        'class' => 'MiniShop3\\Model\\msCategoryOption',
                        'local' => 'id',
                        'foreign' => 'option_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
                'OptionProducts' =>
                    [
                        'class' => 'MiniShop3\\Model\\msProductOption',
                        'local' => 'key',
                        'foreign' => 'key',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ],
            ],
        'aggregates' =>
            [
                'Category' =>
                    [
                        'class' => 'MODX\\Revolution\\modCategory',
                        'local' => 'category_id',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ],
            ],
        'validation' =>
            [
                'rules' =>
                    [
                        'key' =>
                            [
                                'invalid' =>
                                    [
                                        'type' => 'preg_match',
                                        'rule' => '/^(?!\\W+)(?!\\d)[a-zA-Z0-9\\x2d-\\x2f\\x7f-\\xff-_]+(?!\\s)$/',
                                        'message' => 'ms3_option_err_invalid_key',
                                    ],
                                'reserved' =>
                                    [
                                        'type' => 'preg_match',
                                        'rule' => '/^(?!(id|type|contentType|pagetitle|longtitle|description|alias|link_attributes|published|pub_date|unpub_date|parent|isfolder|introtext|content|richtext|template|menuindex|searchable|cacheable|createdby|createdon|editedby|editedon|deleted|deletedby|deletedon|publishedon|publishedby|menutitle|donthit|privateweb|privatemgr|content_dispo|hidemenu|class_key|context_key|content_type|uri|uri_override|hide_children_in_tree|show_in_tree|article|price|old_price|weight|image|thumb|vendor|made_in|new|popular|favorite|tags|color|size|source|action)$)/',
                                        'message' => 'ms3_option_err_reserved_key',
                                    ],
                            ],
                    ],
            ],
    ];
}
