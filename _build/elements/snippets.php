<?php

return [
    'msProducts' => [
        'file' => 'ms3_products',
        'description' => '',
        'properties' => [
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msProducts.row',
            ],
            'limit' => [
                'type' => 'numberfield',
                'value' => 10,
            ],
            'offset' => [
                'type' => 'numberfield',
                'value' => 0,
            ],
            'depth' => [
                'type' => 'numberfield',
                'value' => 10,
            ],
            'sortby' => [
                'type' => 'textfield',
                'value' => 'id',
            ],
            'sortbyOptions' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'sortdir' => [
                'type' => 'list',
                'options' => [
                    ['text' => 'ASC', 'value' => 'ASC'],
                    ['text' => 'DESC', 'value' => 'DESC'],
                ],
                'value' => 'ASC',
            ],
            'toPlaceholder' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'toSeparatePlaceholders' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'showLog' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'parents' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'resources' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'includeContent' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'includeTVs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'includeThumbs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'includeVendorFields' => [
                'type' => 'textfield',
                'value' => '*',
            ],
            'optionFilters' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'where' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'link' => [
                'type' => 'numberfield',
                'value' => '',
            ],
            'master' => [
                'type' => 'numberfield',
                'value' => '',
            ],
            'slave' => [
                'type' => 'numberfield',
                'value' => '',
            ],
            'tvPrefix' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'outputSeparator' => [
                'type' => 'textfield',
                'value' => "\n",
            ],
            'returnIds' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'return' => [
                'type' => 'textfield',
                'value' => 'data',
            ],
            'showUnpublished' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'showDeleted' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'showHidden' => [
                'type' => 'combo-boolean',
                'value' => true,
            ],
            'showZeroPrice' => [
                'type' => 'combo-boolean',
                'value' => true,
            ],
            'wrapIfEmpty' => [
                'type' => 'combo-boolean',
                'value' => true,
            ],
        ],
    ],
    'msCart' => [
        'file' => 'ms3_cart',
        'description' => '',
        'properties' => [
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msCart',
            ],
            'includeTVs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'includeThumbs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'toPlaceholder' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'showLog' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
        ],
    ],
    'msGallery' => [
        'file' => 'ms3_gallery',
        'description' => '',
        'properties' => [
            'product' => [
                'type' => 'numberfield',
                'value' => '',
            ],
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msGallery',
            ],
            'limit' => [
                'type' => 'numberfield',
                'value' => 0,
            ],
            'offset' => [
                'type' => 'numberfield',
                'value' => 0,
            ],
            'sortby' => [
                'type' => 'textfield',
                'value' => 'position',
            ],
            'sortdir' => [
                'type' => 'list',
                'options' => [
                    ['text' => 'ASC', 'value' => 'ASC'],
                    ['text' => 'DESC', 'value' => 'DESC'],
                ],
                'value' => 'ASC',
            ],
            'toPlaceholder' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'showLog' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'where' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'filetype' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'return' => array(
                'type' => 'textfield',
                'value' => 'tpl',
            ),
        ],
    ],
    'msOptions' => [
        'file' => 'ms3_options',
        'description' => '',
        'properties' => [
            'product' => [
                'type' => 'numberfield',
                'value' => '',
            ],
            'options' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'sortOptionValues' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msOptions',
            ],
        ],
    ],
    'msCustomer' => [
        'file' => 'ms3_customer',
        'description' => '',
        'properties' => [
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msCustomerForm',
            ],
        ],
    ],
    'msOrder' => [
        'file' => 'ms3_order',
        'description' => '',
        'properties' => [
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msOrder',
            ],
            'userFields' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'showLog' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
            'includeDeliveryFields' => [
                'type' => 'textfield',
                'value' => '*',
            ],
            'includePaymentFields' => [
                'type' => 'textfield',
                'value' => '*',
            ],
            'return' => [
                'type' => 'textfield',
                'value' => 'tpl',
            ],
        ],
    ],
    'msGetOrder' => [
        'file' => 'ms3_get_order',
        'description' => '',
        'properties' => [
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msGetOrder',
            ],
            'includeTVs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'includeThumbs' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'toPlaceholder' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'showLog' => [
                'type' => 'combo-boolean',
                'value' => false,
            ],
        ],
    ],
    'msProductOptions' => [
        'file' => 'ms3_product_options',
        'description' => '',
        'properties' => [
            'product' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'tpl' => [
                'type' => 'textfield',
                'value' => 'tpl.msProductOptions',
            ],
            'ignoreGroups' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'ignoreOptions' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'onlyOptions' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'sortGroups' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'sortOptions' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'sortOptionValues' => [
                'type' => 'textfield',
                'value' => '',
            ],
            'groups' => [
                'type' => 'textfield',
                'value' => '',
            ],
        ],
    ],
];
