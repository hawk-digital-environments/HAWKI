<?php


use App\Services\FileConverter\Handlers\GwdgDoclingConverter;
use App\Services\FileConverter\Handlers\HawkiDocConverter;
use App\Services\FileConverter\Handlers\KreuzbergConverter;

return [

    'default' => env('FILE_CONVERTER', 'hawki_converter'),
    'fallback' => 'hawki_converter',

    /*
    |--------------------------------------------------------------------------
    | Pre-processing Binaries
    |--------------------------------------------------------------------------
    | Paths to the external binaries used by ImagePreProcessingConverter.
    | Override these if the executables are not on the system PATH.
    |
    | FILE_CONVERTER_BINARY_RSVG_CONVERT  – rsvg-convert (librsvg2-bin)
    | FILE_CONVERTER_BINARY_IMAGE_MAGICK  – ImageMagick convert / magick
    */
    'binaries' => [
        'rsvg_convert' => env('FILE_CONVERTER_BINARY_RSVG_CONVERT', 'rsvg-convert'),
        'image_magick'  => env('FILE_CONVERTER_BINARY_IMAGE_MAGICK', 'convert'),
    ],
    /*
    | When setting large timeouts make sure the infrastructure supports it and consider increasing
    | server configurations default timeouts like nginx 60s defaults 
    */ 
    'converters' => [
        'hawki_converter' => [
            'api_url' => env('HAWKI_FILE_CONVERTER_API_URL'),
            'api_key' => env('HAWKI_FILE_CONVERTER_API_KEY'),
            'class' => HawkiDocConverter::class,
            'timeout' => env('HAWKI_FILE_CONVERTER_TIMEOUT', 60),
        ],
        'gwdg_docling' =>[
            'api_url' => env('GWDG_FILE_CONVERTER_API_URL', 'https://chat-ai.academiccloud.de/v1/documents/convert'),
            'api_key' => env('GWDG_API_KEY'),
            'class' => GwdgDoclingConverter::class,
            'timeout' => env('GWDG_FILE_CONVERTER_TIMEOUT', 240),
        ],
        'kreuzberg' => [
            'api_url' => env('KREUZBERG_FILE_CONVERTER_API_URL'),
            'class' => KreuzbergConverter::class,
            'timeout' => env('KREUZBERG_FILE_CONVERTER_TIMEOUT', 60),
        ]
    ]
];
