<?php

namespace MiniShop3\Model\mysql;

class msProductFile extends \MiniShop3\Model\msProductFile
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_product_files',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'product_id' => NULL,
                'source_id' => 1,
                'parent_id' => 0,
                'name' => '',
                'description' => NULL,
                'path' => '',
                'file' => NULL,
                'type' => NULL,
                'createdon' => NULL,
                'createdby' => 0,
                'position' => 0,
                'url' => '',
                'properties' => NULL,
                'hash' => '',
                'active' => 1,
            ),
        'fieldMeta' =>
            array(
                'product_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                    ),
                'source_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
                'parent_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
                    ),
                'name' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'path' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'file' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'type' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '50',
                        'phptype' => 'string',
                        'null' => true,
                    ),
                'createdon' =>
                    array(
                        'dbtype' => 'datetime',
                        'phptype' => 'datetime',
                        'null' => true,
                    ),
                'createdby' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 0,
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
                'url' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '255',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'properties' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'json',
                        'null' => true,
                    ),
                'hash' =>
                    array(
                        'dbtype' => 'char',
                        'precision' => '40',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                        'index' => 'index',
                    ),
                'active' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'integer',
                        'null' => true,
                        'default' => 1,
                    ),
            ),
        'indexes' =>
            array(
                'product_id' =>
                    array(
                        'alias' => 'product_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'product_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'type' =>
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
                'parent_id' =>
                    array(
                        'alias' => 'parent_id',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'parent_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'hash' =>
                    array(
                        'alias' => 'hash',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'hash' =>
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
            ),
        'composites' =>
            array(
                'Children' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductFile',
                        'local' => 'id',
                        'foreign' => 'parent_id',
                        'cardinality' => 'many',
                        'owner' => 'local',
                    ),
            ),
        'aggregates' =>
            array(
                'Parent' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProductFile',
                        'local' => 'parent_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Product' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'product_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Source' =>
                    array(
                        'class' => 'MODX\\Revolution\\Sources\\modMediaSource',
                        'local' => 'source_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
