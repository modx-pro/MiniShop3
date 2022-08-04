<?php

namespace MiniShop3\Model\mysql;

class msProductOption extends \MiniShop3\Model\msProductOption
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_product_options',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'product_id' => NULL,
                'key' => NULL,
                'value' => '',
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
                'key' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '191',
                        'phptype' => 'string',
                        'null' => false,
                    ),
                'value' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
            ),
        'indexes' =>
            array(
                'product' =>
                    array(
                        'alias' => 'product',
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
                                'key' =>
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
                'Product' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msProduct',
                        'local' => 'product_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Option' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msOption',
                        'local' => 'key',
                        'foreign' => 'key',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
