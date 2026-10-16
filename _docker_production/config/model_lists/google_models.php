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
    [
        'active' => env('MODELS_GOOGLE_GEMMA_4_26B_A4B_INSTRUCT_ACTIVE', true),
        'id' => 'gemma-4-26b-a4b-it',
        'label' => 'Google Gemma 4 26B A4B Instruct',
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
            'file_upload' => env('MODELS_GOOGLE_GEMMA_4_26B_A4B_INSTRUCT_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMMA_4_26B_A4B_INSTRUCT_TOOLS_NATIVE_CAPABILITIES', false),
        ],
        'default_params' => [
            // Google recommended sampling for Gemma 4: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GOOGLE_GEMMA_4_26B_A4B_INSTRUCT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GOOGLE_GEMMA_4_26B_A4B_INSTRUCT_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GOOGLE_GEMMA_4_31B_INSTRUCT_ACTIVE', true),
        'id' => 'gemma-4-31b-it',
        'label' => 'Google Gemma 4 31B Instruct',
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
            'file_upload' => env('MODELS_GOOGLE_GEMMA_4_31B_INSTRUCT_TOOLS_FILE_UPLOAD', false),
            'native_capabilities' => env('MODELS_GOOGLE_GEMMA_4_31B_INSTRUCT_TOOLS_NATIVE_CAPABILITIES', false),
        ],
        'default_params' => [
            // Google recommended sampling for Gemma 4: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GOOGLE_GEMMA_4_31B_INSTRUCT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GOOGLE_GEMMA_4_31B_INSTRUCT_PARAMS_TOP_P', 0.95),
        ],
    ],
];
