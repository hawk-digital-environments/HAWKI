<?php

return [
    [
        'active'=> false,
        'id' => 'model-id',
        'label' => 'Model label',
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
    ]
];
