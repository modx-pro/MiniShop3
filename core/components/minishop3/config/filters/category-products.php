<?php
/**
 * Category products grid filters configuration
 *
 * Filter types:
 * - text: Text input for search
 * - select: Dropdown with options from model, API, or static list
 * - checkbox: Boolean checkbox
 *
 * @see \MiniShop3\Services\FilterConfigManager
 */

return [
    // Text search across multiple fields
    'query' => [
        'type' => 'text',
        'label' => 'search',
        'placeholder' => 'search_by_title_article',
        'width' => '250px',
        'position' => 10,
    ],

    // Published filter
    'published' => [
        'type' => 'select',
        'label' => 'published',
        'placeholder' => 'all',
        'source' => [
            'type' => 'static',
            'options' => [
                ['label' => 'ms3_yes', 'value' => 1],
                ['label' => 'ms3_no', 'value' => 0],
            ],
        ],
        'width' => '120px',
        'position' => 20,
    ],

    // Deleted filter (show deleted products)
    'deleted' => [
        'type' => 'select',
        'label' => 'deleted',
        'placeholder' => 'all',
        'source' => [
            'type' => 'static',
            'options' => [
                ['label' => 'ms3_show_deleted', 'value' => 1],
                ['label' => 'ms3_hide_deleted', 'value' => 0],
            ],
        ],
        'width' => '150px',
        'position' => 30,
        'visible' => false,
    ],
];
