<?php

return [
//    [
//        'active'=> false,
//        'id' => 'model-id',
//        'label' => 'Model label',
//        "input"=> [
//            "text",
//        ],
//        "output"=> [
//            "text"
//        ],
//        'tools' => [
//            'stream' => true,
//            'image_generation' => true,
//            'vision' => true,
//            'web_search' => true,
//            'file_upload' => true,
//        ],
//
//    ],
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
