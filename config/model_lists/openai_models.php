<?php
return [
    [
        'active' => env('MODELS_OPENAI_GPT5_6_LUNA_ACTIVE', true),
        'id' => 'gpt-5.6-luna',
        'label' => 'GPT-5.6 Luna',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_6_LUNA_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_6_LUNA_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_6_LUNA_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_6_TERRA_ACTIVE', true),
        'id' => 'gpt-5.6-terra',
        'label' => 'GPT-5.6 Terra',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_6_TERRA_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_6_TERRA_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_6_TERRA_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_6_SOL_ACTIVE', true),
        'id' => 'gpt-5.6-sol',
        'label' => 'GPT-5.6 Sol',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_6_SOL_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_6_SOL_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_6_SOL_PARAMS_TEMP', 1.0),
        ],
    ],
];
