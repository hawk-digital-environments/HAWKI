<?php

return [
    [
        'active'=> true,
        'id' => 'llava:latest',
        'label' => 'IXD llava:latest',
        "input"=> [
            "text",
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'image_generation' => true,
            'vision' => true,
            'web_search' => true,
            'file_upload' => true,
        ],

    ],
    [
        'active'=> true,
        'id' => 'llama3.1:8b',
        'label' => 'llama3.1:8b',
        "input"=> [
            "text",
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'image_generation' => true,
            'vision' => true,
            'web_search' => true,
            'file_upload' => true,
        ],

    ],
    [
        'active'=> true,
        'id' => 'tinyllama',
        'label' => 'IXD TinyLlama',
        "input"=> [
            "text",
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'image_generation' => true,
            'vision' => true,
            'web_search' => true,
            'file_upload' => true,
        ],

    ],
];
