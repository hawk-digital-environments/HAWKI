<?php

return [
    
    'default_language' => env('APP_LOCALE', 'en_US'),
    
    'langs' => [
        'de_DE' => [
            'active' => true,
            'id'=>'de_DE',
            'name'=>'Deutsch',
            'label' => 'DE',
            'file' => 'de_DE.json'
        ],
        'en_US' => [
            'active' => true,
            'id'=>'en_US',
            'name'=>'English',
            'label' => 'EN',
            'file' => 'en_US.json'
        ]
    ]
];