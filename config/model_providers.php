<?php

return [

    /*
    |--------------------------------------------------------------------------
    |   System Models
    |--------------------------------------------------------------------------
    |
    |   The system models are responsible for the automated processes
    |   such as title generation and prompt improvement.
    |   Add your desired models id.
    |   Make sure that the model is included and active in the providers list below.
    |
    */
    'system_models' => [
        'default_model' => env('DEFAULT_MODEL', 'gpt-5.6-luna'),
        'title_generator' => env('TITLE_GENERATOR_MODEL', 'gpt-5.6-luna'),
        'prompt_improver' => env('PROMPT_IMPROVEMENT_MODEL', 'gpt-5.6-luna'),
        'summarizer' => env('SUMMARIZER_MODEL', 'gpt-5.6-luna'),
        'translator' => env('TRANSLATOR_MODEL', 'gpt-5.6-luna'),
    ],

    /*
     * The system models to use when accessing HAWKI via an external application
     * If null, the general system models are used above (can be useful to prevent high cost models being used by external apps)
     */
    'system_models_ext_app' => [
//        'default_model' => null,
//        'title_generator' => null,
//        'prompt_improver' => null,
//        'summarizer' => null,
//        'translator' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    |   Model Providers
    |--------------------------------------------------------------------------
    |
    |   List of model providers available on HAWKI. Add your API Key and
    |   activate the providers.
    |   To include other providers in this list please refer to the
    |   documentation of HAWKI
    |
    */
    'providers' => [
        'openAi' => [
            'active' => env('OPENAI_ACTIVE', true),
            'api_key' => env('OPENAI_API_KEY'),
            'api_url' => env('OPENAI_URL'),
            'ping_url' => env('OPENAI_PING_URL'),
            'models' => require __DIR__ . env('OPENAI_MODEL_LIST_DIR', '/model_lists/openai_models.php'),
        ],
        'gwdg' => [
            'active' => env('GWDG_ACTIVE', true),
            'api_key' => env('GWDG_API_KEY'),
            'api_url' => env('GWDG_API_URL'),
            'ping_url' => env('GWDG_PING_URL'),
            'models' => require __DIR__ . env('GWDG_MODEL_LIST_DIR', '/model_lists/gwdg_models.php'),
        ],
        'ollama' => [
            'active' => env('OLLAMA_ACTIVE', false),
            'api_url' => env('OLLAMA_API_URL'),
            'ping_url' => env('OLLAMA_API_URL'),
            'models' => require __DIR__ . env('OLLAMA_MODEL_LIST_DIR', '/model_lists/ollama_models.php'),
        ],
        'openWebUi' => [
            'active' => env('OPEN_WEB_UI_ACTIVE', false),
            'api_key' => env('OPEN_WEB_UI_API_KEY'),
            'api_url' => env('OPEN_WEB_UI_API_URL'),
            'ping_url' => env('OPEN_WEB_UI_PING_URL'),
            'models' => require __DIR__ . env('OPEN_WEB_UI_MODEL_LIST_DIR', '/model_lists/openwebui_models.php'),
        ]
    ]
];
