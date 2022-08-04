<?php

namespace MiniShop3\Model\mysql;

class msCategoryOption extends \MiniShop3\Model\msCategoryOption
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_category_options',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'option_id' => 0,
                'category_id' => 0,
                'position' => 0,
                'active' => 0,
                'required' => 0,
                'value' => NULL,
            ),
        'fieldMeta' =>
            array(
                'option_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'pk',
                    ),
                'category_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'pk',
                    ),
                'position' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'phptype' => 'integer',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ),
                'active' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ),
                'required' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'attributes' => 'unsigned',
                        'phptype' => 'boolean',
                        'null' => false,
                        'default' => 0,
                        'index' => 'index',
                    ),
                'value' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                        'index' => 'fulltext',
                    ),
            ),
        'indexes' =>
            array(
                'PRIMARY' =>
                    array(
                        'alias' => 'PRIMARY',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'option_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                                'category_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'position' =>
                    array(
                        'alias' => 'position',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'position' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'active' =>
                    array(
                        'alias' => 'active',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'active' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'required' =>
                    array(
                        'alias' => 'required',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'required' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'value_ft' =>
                    array(
                        'alias' => 'value_ft',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'FULLTEXT',
                        'columns' =>
                            array(
                                'value' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
            ),
        'aggregates' =>
            array(
                'Category' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msCategory',
                        'local' => 'category_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Option' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOption',
                        'local' => 'option_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
