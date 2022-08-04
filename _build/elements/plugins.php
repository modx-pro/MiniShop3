<?php

return [
    'MiniShop3' => [
        'file' => 'minishop3',
        'description' => '',
        'events' => [
            'OnMODXInit',
            'OnHandleRequest',
            'OnLoadWebDocument',
            'OnWebPageInit',
            'OnUserSave',
            'msOnChangeOrderStatus',
            'OnManagerPageBeforeRender',
        ],
    ],
];
