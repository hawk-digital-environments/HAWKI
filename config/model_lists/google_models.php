<?php
return [
    [
        'active'=> true,
        'id' => 'gemini-2.0-flash',
        'label' => 'Google Gemini 2.0 Flash',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => true,
            'file_upload' => false,
            'web_search'=> true,

        ],
    ],
    [
        'active'=> true,
        'id' => 'gemini-2.0-flash-lite',
        'label' => 'Google Gemini 2.0 Flash Lite',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => false,
            'file_upload' => false,
        ],
    ],
    [
        'active'=> true,
        'id' => 'gemini-2.5-pro',
        'label' => 'Google Gemini 2.5 Pro',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => true,
            'file_upload' => false,
            'web_search'=> true,
        ],
    ]
];
