<?php

/**
 * Конфигурация полей для импорта товаров
 *
 * Структура:
 * - resource: поля modResource (msProduct)
 * - product_data: поля msProductData
 * - special: специальные поля (gallery)
 * - prefixes: динамические префиксы (tv.*, option.*)
 *
 * msExtraField / Object Extension columns are merged dynamically in
 * Processors\Utilities\Import\Fields via ImportExtraFieldCatalog (#291).
 */

return [
    // Поля ресурса (modResource / msProduct)
    'resource' => [
        'pagetitle' => [
            'label' => 'ms3_product_pagetitle',
            'required' => true,
            'type' => 'string',
        ],
        'longtitle' => [
            'label' => 'ms3_product_longtitle',
            'type' => 'string',
        ],
        'description' => [
            'label' => 'ms3_product_description',
            'type' => 'string',
        ],
        'introtext' => [
            'label' => 'ms3_product_introtext',
            'type' => 'string',
        ],
        'content' => [
            'label' => 'ms3_product_content',
            'type' => 'string',
        ],
        'alias' => [
            'label' => 'ms3_product_alias',
            'type' => 'string',
        ],
        'parent' => [
            'label' => 'ms3_product_parent',
            'required' => true,
            'type' => 'integer',
        ],
        'template' => [
            'label' => 'ms3_product_template',
            'type' => 'integer',
        ],
        'published' => [
            'label' => 'ms3_product_published',
            'type' => 'boolean',
        ],
        'hidemenu' => [
            'label' => 'ms3_product_hidemenu',
            'type' => 'boolean',
        ],
        'menuindex' => [
            'label' => 'ms3_product_menuindex',
            'type' => 'integer',
        ],
        'searchable' => [
            'label' => 'ms3_product_searchable',
            'type' => 'boolean',
        ],
        'cacheable' => [
            'label' => 'ms3_product_cacheable',
            'type' => 'boolean',
        ],
        'menutitle' => [
            'label' => 'ms3_product_menutitle',
            'type' => 'string',
        ],
        'link_attributes' => [
            'label' => 'ms3_product_link_attributes',
            'type' => 'string',
        ],
        'pub_date' => [
            'label' => 'ms3_product_pub_date',
            'type' => 'datetime',
        ],
        'unpub_date' => [
            'label' => 'ms3_product_unpub_date',
            'type' => 'datetime',
        ],
        'uri' => [
            'label' => 'ms3_product_uri',
            'type' => 'string',
        ],
        'uri_override' => [
            'label' => 'ms3_product_uri_override',
            'type' => 'boolean',
        ],
        'show_in_tree' => [
            'label' => 'ms3_product_show_in_tree',
            'type' => 'boolean',
        ],
    ],

    // Поля данных товара (msProductData)
    'product_data' => [
        'article' => [
            'label' => 'ms3_product_article',
            'type' => 'string',
        ],
        'price' => [
            'label' => 'ms3_product_price',
            'type' => 'float',
        ],
        'old_price' => [
            'label' => 'ms3_product_old_price',
            'type' => 'float',
        ],
        'stock' => [
            'label' => 'ms3_product_stock',
            'type' => 'integer',
        ],
        'remains' => [
            'label' => 'ms3_product_remains',
            'type' => 'integer',
        ],
        'weight' => [
            'label' => 'ms3_product_weight',
            'type' => 'float',
        ],
        'image' => [
            'label' => 'ms3_product_image',
            'type' => 'string',
        ],
        'thumb' => [
            'label' => 'ms3_product_thumb',
            'type' => 'string',
        ],
        'vendor' => [
            'label' => 'ms3_product_vendor_id',
            'type' => 'string',
            'description' => 'ms3_import_vendor_desc',
        ],
        'made_in' => [
            'label' => 'ms3_product_made_in',
            'type' => 'string',
        ],
        'new' => [
            'label' => 'ms3_product_new',
            'type' => 'boolean',
        ],
        'popular' => [
            'label' => 'ms3_product_popular',
            'type' => 'boolean',
        ],
        'favorite' => [
            'label' => 'ms3_product_favorite',
            'type' => 'boolean',
        ],
        'tags' => [
            'label' => 'ms3_product_tags',
            'type' => 'json',
            'description' => 'ms3_import_tags_desc',
        ],
        'color' => [
            'label' => 'ms3_product_color',
            'type' => 'json',
        ],
        'size' => [
            'label' => 'ms3_product_size',
            'type' => 'json',
        ],
        'source_id' => [
            'label' => 'ms3_product_source_id',
            'type' => 'integer',
        ],
    ],

    // Специальные поля
    'special' => [
        'gallery' => [
            'label' => 'ms3_product_tab_gallery',
            'type' => 'string',
            'multiple' => true,
            'description' => 'ms3_import_gallery_desc',
        ],
    ],

    // Динамические префиксы
    'prefixes' => [
        'tv' => [
            'label' => 'ms3_import_tv_prefix',
            'description' => 'ms3_import_tv_prefix_desc',
            'example' => 'tv.myfield',
        ],
        'option' => [
            'label' => 'ms3_import_option_prefix',
            'description' => 'ms3_import_option_prefix_desc',
            'example' => 'option.color',
        ],
    ],

    // Поля-ключи для обновления (которые можно использовать как уникальный идентификатор)
    'key_fields' => [
        'id',
        'article',
        'pagetitle',
        'alias',
    ],

    // Поля по умолчанию для нового импорта
    'default_mapping' => [
        'pagetitle',
        'parent',
        'price',
        'article',
    ],
];
