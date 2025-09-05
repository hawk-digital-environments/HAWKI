<?php
return [
    [
        'active'=> env('MODELS_GOOGLE_GEMINI_2_0_FLASH_ACTIVE', true),
        'id' => 'gemini-2.0-flash',
        'label' => 'Google Gemini 2.0 Flash',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => env('MODELS_GOOGLE_GEMINI_2_0_FLASH_TOOLS_VISION', true),
            'file_upload' => env('MODELS_GOOGLE_GEMINI_2_0_FLASH_TOOLS_FILE_UPLOAD', false),
            'web_search'=> env('MODELS_GOOGLE_GEMINI_2_0_FLASH_TOOLS_WEB_SEARCH', true),
        ],
    ],
    [
        'active'=> env('MODELS_GOOGLE_GEMINI_2_0_FLASH_LITE_ACTIVE', true),
        'id' => 'gemini-2.0-flash-lite',
        'label' => 'Google Gemini 2.0 Flash Lite',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => env('MODELS_GOOGLE_GEMINI_2_0_FLASH_LITE_TOOLS_VISION', false),
            'file_upload' => env('MODELS_GOOGLE_GEMINI_2_0_FLASH_LITE_TOOLS_FILE_UPLOAD', false),
        ],
    ],
    [
        'active'=> env('MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE', true),
        'id' => 'gemini-2.5-pro',
        'label' => 'Google Gemini 2.5 Pro',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'vision' => env('MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_VISION', true),
            'file_upload' => env('MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_FILE_UPLOAD', false),
            'web_search'=> env('MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_WEB_SEARCH', true),
        ],
    ]
];
