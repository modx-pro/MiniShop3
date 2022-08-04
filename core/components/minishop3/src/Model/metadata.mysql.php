<?php

$xpdo_meta_map = [
    'version' => '3.0',
    'namespace' => 'MiniShop3\\Model',
    'namespacePrefix' => 'MiniShop3',
    'class_map' =>
        [
            'MODX\\Revolution\\modResource' =>
                [
                    'MiniShop3\\Model\\msCategory',
                    'MiniShop3\\Model\\msProduct',
                ],
            'xPDO\\Om\\xPDOSimpleObject' =>
                [
                    'MiniShop3\\Model\\msProductData',
                    'MiniShop3\\Model\\msVendor',
                    'MiniShop3\\Model\\msProductFile',
                    'MiniShop3\\Model\\msDelivery',
                    'MiniShop3\\Model\\msPayment',
                    'MiniShop3\\Model\\msOrder',
                    'MiniShop3\\Model\\msOrderStatus',
                    'MiniShop3\\Model\\msOrderLog',
                    'MiniShop3\\Model\\msOrderAddress',
                    'MiniShop3\\Model\\msOrderProduct',
                    'MiniShop3\\Model\\msLink',
                    'MiniShop3\\Model\\msCustomerProfile',
                    'MiniShop3\\Model\\msOption',
                ],
            'xPDO\\Om\\xPDOObject' =>
                [
                    'MiniShop3\\Model\\msCategoryMember',
                    'MiniShop3\\Model\\msProductOption',
                    'MiniShop3\\Model\\msDeliveryMember',
                    'MiniShop3\\Model\\msProductLink',
                    'MiniShop3\\Model\\msCategoryOption',
                ],
        ],
];
