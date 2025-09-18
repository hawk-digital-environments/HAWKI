<?php


return [

    'default' => env('FILE_CONVERTER', 'hawki_converter'),
    'fallback' => 'hawki_converter',

    'converters' => [
        'hawki_converter' => [
            'api_url' => env('HAWKI_FILE_CONVERTER_API_URL'),
            'api_key' => env('HAWKI_FILE_CONVERTER_API_KEY'),
        ],
        'gwdg_docling' =>[
            'api_url' => env('GWDG_FILE_CONVERTER_API_URL', 'https://chat-ai.academiccloud.de/v1/documents/convert'),
            'api_key' => env('GWDG_API_KEY')
        ]
    ]
];
