<?php
return [
    [
        'active' => env('MODELS_OPENAI_GPT6_ASTRA_ACTIVE', true),
        'id' => 'gpt-6-astra',
        'label' => 'GPT-6 Astra',
        "input" => [
            "text",
            "image"
        ],
        "output" => [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_OPENAI_GPT6_ASTRA_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT6_ASTRA_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT6_ASTRA_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT6_1_SOL_ACTIVE', true),
        'id' => 'gpt-6.1-sol',
        'label' => 'GPT-6.1 Sol',
        "input" => [
            "text",
            "image"
        ],
        "output" => [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_OPENAI_GPT6_1_SOL_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT6_1_SOL_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT6_1_SOL_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT6_1_TERRA_ACTIVE', true),
        'id' => 'gpt-6.1-terra',
        'label' => 'GPT-6.1 Terra',
        "input" => [
            "text",
            "image"
        ],
        "output" => [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_OPENAI_GPT6_1_TERRA_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT6_1_TERRA_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT6_1_TERRA_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT6_LUNA_ACTIVE', true),
        'id' => 'gpt-6-luna',
        'label' => 'GPT-6 Luna',
        "input" => [
            "text",
            "image"
        ],
        "output" => [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_OPENAI_GPT6_LUNA_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT6_LUNA_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT6_LUNA_PARAMS_TEMP', 1.0),
        ],
    ],
];
