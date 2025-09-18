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
            'stream' => true,
            'file_upload' => env('MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD', false),
            'vision'=> env('MODELS_OPENAI_GPT5_TOOLS_VISION', true),
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
            'stream' => true,
            'file_upload' => env('MODELS_OPENAI_GPT4_1_TOOLS_FILE_UPLOAD', false),
            'vision'=> env('MODELS_OPENAI_GPT4_1_TOOLS_VISION', true),

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
            'file_upload' => env('MODELS_OPENAI_GPT4_1_NANO_TOOLS_FILE_UPLOAD', false),
            'vision'=> env('MODELS_OPENAI_GPT4_1_NANO_TOOLS_VISION', true),
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
            'file_upload' => env('MODELS_OPENAI_O4_MINI_TOOLS_FILE_UPLOAD', false),
            'vision'=> env('MODELS_OPENAI_O4_MINI_TOOLS_VISION', false),
        ],
    ],
];
