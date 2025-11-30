<?php

return [
    'MiniShop3' => [
        'file' => 'minishop3',
        'description' => '',
        'events' => [
            'OnMODXInit',
            'OnLoadWebDocument',
            'OnManagerPageBeforeRender',
            'OnUserSave',
            'OnBeforeUserFormSave',
            'OnUserRemove',
        ],
    ],
];
