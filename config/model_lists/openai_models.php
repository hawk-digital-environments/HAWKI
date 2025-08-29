<?php
return [
    [
        'active'=> true,
        'id' => 'gpt-5',
        'label' => 'OpenAI GPT 5',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => false,
            'vision'=> true,
        ],
    ],
    [
        'active'=> true,
        'id' => 'gpt-4.1',
        'label' => 'OpenAI GPT 4.1',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => false,
            'vision'=> true,
        ],
    ],
    [
        'active'=> true,
        'id' => 'gpt-4.1-nano',
        'label' => 'OpenAI GPT 4.1 Nano',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => false,
            'vision'=> true,
        ],
    ],
    [
        'active'=> true,
        'id' => 'o4-mini',
        'label' => 'OpenAI o4 mini',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => false,
            'vision' => false,

        ],

    ],
];
