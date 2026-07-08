<?php
return [
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_ACTIVE', true),
        'id' => 'gemini-3.5-flash',
        'label' => 'Google Gemini 3.5 Flash',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_TOOLS_NATIVE_CAPABILITIES', true),
        ],
        'default_params' => [
            // Not in the reference table; Google AI Studio defaults for Gemini Flash: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_LITE_ACTIVE', true),
        'id' => 'gemini-3.1-flash-lite',
        'label' => 'Google Gemini 3.1 Flash Lite',
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
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_TOOLS_NATIVE_CAPABILITIES', false),
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_TOOLS_FILE_UPLOAD', false),
        ],
        'default_params' => [
            // Not in the reference table; Google AI Studio defaults for Gemini Flash Lite: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_1_PRO_ACTIVE', true),
        'id' => 'gemini-3.1-pro-preview',
        'label' => 'Google Gemini 3.1 Pro',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_1_PRO_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_1_PRO_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE', true),
        'id' => 'gemini-2.5-pro',
        'label' => 'Google Gemini 2.5 Pro',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_2_5_PRO_NATIVE_CAPABILITIES', true),
        ],
    ]
];
