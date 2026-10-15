<?php
return [
    [
        'active' => env('MODELS_GOOGLE_GEMINI_4_ARGON_ACTIVE', true),
        'id' => 'gemini-4-argon',
        'label' => 'Gemini 4 Argon',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_4_ARGON_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_4_ARGON_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_ACTIVE', true),
        'id' => 'gemini-3.8-flash',
        'label' => 'Gemini 3.8 Flash',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_TTS_ACTIVE', true),
        'id' => 'gemini-3.8-flash-tts',
        'label' => 'Gemini 3.8 Flash TTS',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_TTS_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_TTS_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_LITE_TTS_ACTIVE', true),
        'id' => 'gemini-3.8-flash-lite-tts',
        'label' => 'Gemini 3.8 Flash Lite TTS',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_LITE_TTS_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_8_FLASH_LITE_TTS_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_6_FLASH_ACTIVE', true),
        'id' => 'gemini-3.6-flash',
        'label' => 'Gemini 3.6 Flash',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_6_FLASH_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_6_FLASH_TOOLS_NATIVE_CAPABILITIES', true),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_LITE_ACTIVE', true),
        'id' => 'gemini-3.5-flash-lite',
        'label' => 'Gemini 3.5 Flash Lite',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_LITE_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_5_FLASH_LITE_TOOLS_NATIVE_CAPABILITIES', false),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_ACTIVE', true),
        'id' => 'gemini-3.1-flash-lite',
        'label' => 'Gemini 3.1 Flash Lite',
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
            'file_upload' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMINI_3_1_FLASH_LITE_TOOLS_NATIVE_CAPABILITIES', false),
        ],
    ],
];
