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
            'tool_calling' => true,
            'file_upload' => true,
            'vision'=> true,
        ],
        'default_params' => [
            // OpenAI API defaults: temp=1.0, top_p=1.0
            'temp' => env('MODELS_OPENAI_GPT5_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_GPT5_PARAMS_TOP_P', 1.0),
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
            'tool_calling' => true,
            'file_upload' => true,
            'vision'=> true,

            // AiTool capabilities
            'web_search' => 'native',
        ],
        'default_params' => [
            // OpenAI API defaults: temp=1.0, top_p=1.0
            'temp' => env('MODELS_OPENAI_GPT4_1_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_GPT4_1_PARAMS_TOP_P', 1.0),
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
            'tool_calling' => true,
            'file_upload' => false,
            'vision'=> false,
        ],
        'default_params' => [
            // OpenAI API defaults: temp=1.0, top_p=1.0
            'temp' => env('MODELS_OPENAI_GPT4_1_NANO_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_GPT4_1_NANO_PARAMS_TOP_P', 1.0),
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
            'tool_calling' => true,
            'file_upload' => false,
            'vision'=> false,
        ],
        'default_params' => [
            // Reasoning model (o-series); temperature is not tunable in the traditional sense — API defaults used
            'temp' => env('MODELS_OPENAI_O4_MINI_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_O4_MINI_PARAMS_TOP_P', 1.0),
        ],
    ],
];
