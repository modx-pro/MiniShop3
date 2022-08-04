<?php

namespace MiniShop3\Model\mysql;

class msOption extends \MiniShop3\Model\msOption
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_options',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'key' => '',
                'caption' => '',
                'description' => NULL,
                'measure_unit' => NULL,
                'category' => NULL,
                'type' => '',
                'properties' => NULL,
            ),
        'fieldMeta' =>
            array(
                'key' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'index',
                    ),
                'caption' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'fulltext',
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'measure_unit' =>
                    array(
                        'dbtype' => 'tinytext',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'category' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ),
                'type' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                        'index' => 'index',
                    ),
                'properties' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
            ),
        'indexes' =>
            array(
                'key' =>
                    array(
                        'alias' => 'type',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'type' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'caption_ft' =>
                    array(
                        'alias' => 'caption_ft',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'FULLTEXT',
                        'columns' =>
                            array(
                                'caption' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'category' =>
                    array(
                        'alias' => 'category',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'category' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
            ),
        'composites' =>
            array(
                'OptionCategories' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategoryOption',
                        'local' => 'id',
                        'foreign' => 'option_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
                'OptionProducts' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductOption',
                        'local' => 'key',
                        'foreign' => 'key',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
        'aggregates' =>
            array(
                'Category' =>
                    array(
                        'class' => 'MODX\\Revolution\\modCategory',
                        'local' => 'category',
                        'foreign' => 'id',
                        'owner' => 'foreign',
                        'cardinality' => 'one',
                    ),
            ),
        'validation' =>
            array(
                'rules' =>
                    array(
                        'key' =>
                            array(
                                'invalid' =>
                                    array(
                                        'type' => 'preg_match',
                                        'rule' => '/^(?!\\W+)(?!\\d)[a-zA-Z0-9\\x2d-\\x2f\\x7f-\\xff-_]+(?!\\s)$/',
                                        'message' => 'ms_option_err_invalid_key',
                                    ),
                                'reserved' =>
                                    array(
                                        'type' => 'preg_match',
                                        'rule' => '/^(?!(id|type|contentType|pagetitle|longtitle|description|alias|link_attributes|published|pub_date|unpub_date|parent|isfolder|introtext|content|richtext|template|menuindex|searchable|cacheable|createdby|createdon|editedby|editedon|deleted|deletedby|deletedon|publishedon|publishedby|menutitle|donthit|privateweb|privatemgr|content_dispo|hidemenu|class_key|context_key|content_type|uri|uri_override|hide_children_in_tree|show_in_tree|article|price|old_price|weight|image|thumb|vendor|made_in|new|popular|favorite|tags|color|size|source|action)$)/',
                                        'message' => 'ms_option_err_reserved_key',
                                    ),
                            ),
                    ),
            ),
    );

}
