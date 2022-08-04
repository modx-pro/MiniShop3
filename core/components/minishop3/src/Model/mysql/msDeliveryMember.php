<?php

namespace MiniShop3\Model\mysql;

class msDeliveryMember extends \MiniShop3\Model\msDeliveryMember
{
    public static $metaMap = array(
        'package' => 'MiniShop3\\Model',
        'version' => '3.0',
        'table' => 'ms_delivery_payments',
        'extends' => 'xPDO\\Om\\xPDOObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'delivery_id' => NULL,
                'payment_id' => NULL,
            ),
        'fieldMeta' =>
            array(
                'delivery_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                        'index' => 'pk',
                    ),
                'payment_id' =>
                    array(
                        'dbtype' => 'int',
                        'precision' => '10',
                        'attributes' => 'unsigned',
                        'phptype' => 'integer',
                        'null' => false,
                        'index' => 'pk',
                    ),
            ),
        'indexes' =>
            array(
                'delivery' =>
                    array(
                        'alias' => 'delivery',
                        'primary' => true,
                        'unique' => true,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'delivery_id' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                                'payment_id' =>
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
                'Delivery' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msDelivery',
                        'local' => 'delivery_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
                'Payment' =>
                    array(
                        'class' => 'MiniShop3\\Model\\msPayment',
                        'local' => 'payment_id',
                        'foreign' => 'id',
                        'cardinality' => 'one',
                        'owner' => 'foreign',
                    ),
            ),
    );

}
