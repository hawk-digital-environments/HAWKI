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
        'active' => env('MODELS_OPENAI_GPT6_SOL_ACTIVE', true),
        'id' => 'gpt-6-sol',
        'label' => 'GPT-6 Sol',
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
            'file_upload' => env('MODELS_OPENAI_GPT6_SOL_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT6_SOL_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT6_SOL_PARAMS_TEMP', 1.0),
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
        'active' => env('MODELS_OPENAI_GPT5_5_ACTIVE', true),
        'id' => 'gpt-5.5',
        'label' => 'GPT-5.5',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_5_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_5_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_5_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_5_PRO_ACTIVE', true),
        'id' => 'gpt-5.5-pro',
        'label' => 'GPT-5.5 Pro',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_5_PRO_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_5_PRO_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_5_PRO_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_4_ACTIVE', true),
        'id' => 'gpt-5.4',
        'label' => 'GPT-5.4',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_4_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_4_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_4_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_4_MINI_ACTIVE', true),
        'id' => 'gpt-5.4-mini',
        'label' => 'GPT-5.4 Mini',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_4_MINI_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_4_MINI_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_4_MINI_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_4_PRO_ACTIVE', true),
        'id' => 'gpt-5.4-pro',
        'label' => 'GPT-5.4 Pro',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_4_PRO_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_4_PRO_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_4_PRO_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_1_ACTIVE', true),
        'id' => 'gpt-5.1',
        'label' => 'GPT-5.1',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_1_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_1_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_1_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_MINI_ACTIVE', true),
        'id' => 'gpt-5-mini',
        'label' => 'GPT-5 Mini',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_MINI_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_MINI_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_MINI_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_GPT5_NANO_ACTIVE', true),
        'id' => 'gpt-5-nano',
        'label' => 'GPT-5 Nano',
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
            'file_upload' => env('MODELS_OPENAI_GPT5_NANO_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_GPT5_NANO_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            'temp' => env('MODELS_OPENAI_GPT5_NANO_PARAMS_TEMP', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_OPENAI_O3_ACTIVE', true),
        'id' => 'o3',
        'label' => 'o3',
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
            'file_upload' => env('MODELS_OPENAI_O3_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_OPENAI_O3_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            // Reasoning model (o-series); temperature is not tunable in the traditional sense — API defaults used
            'temp' => env('MODELS_OPENAI_O3_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_O3_PARAMS_TOP_P', 1.0),
        ],
    ],
];
