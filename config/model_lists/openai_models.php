<?php
return [
    [
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
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
            // Native capabilities
            'stream' => true,
            'file_upload' => true,
            'vision'=> true,
        ],
    ],
    [
        'active'=> env('MODELS_OPENAI_GPT4_1_ACTIVE', default: true),
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
            // Native capabilities
            'stream' => true,
            'file_upload' => true,
            'vision'=> true,

            // Tool capabilities
            'web_search' => 'native',
            'knowledge_base' => "hawki-rag-query-search",

        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT4_1_PARAMS_TEMP'),
            'top_p' => env('MODELS_OPENAI_GPT4_1_PARAMS_TOP_P'),
        ],

    ],
    [
        'active'=> env('MODELS_OPENAI_GPT4_1_NANO_ACTIVE', true),
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
            'vision'=> false,
        ],
    ],
    [
        'active'=> env('MODELS_OPENAI_O4_MINI_ACTIVE', true),
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
            'vision'=> false,
        ],
    ],
];
