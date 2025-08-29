<?php

return [

    /*
    |--------------------------------------------------------------------------
    |   Default AI Models
    |--------------------------------------------------------------------------
    |   These Models are used as predefined models for each task.
    |
    */

    'default_models' => [
        'default_model' => 'gpt-4.1',
        'default_web_search_model' => 'gemini-2.0-flash',
        'default_file_upload_model' => 'meta-llama-3.1-8b-instruct',
        'default_vision_model' => 'gemini-2.0-flash',
    ],

    /*
    |--------------------------------------------------------------------------
    |   System Models
    |--------------------------------------------------------------------------
    |
    |   The system models are responsible for different automated processes
    |   such as title generation and prompt improvement.
    |   Add your desired models id. Make sure that the model is included and
    |   active in the providers list below.
    |
    */
    'system_models' => [
        'title_generator' => "meta-llama-3.1-8b-instruct",
        'prompt_improver' => 'gpt-4.1-nano',
        'summarizer' => 'gpt-4.1-nano',
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
    'providers' =>[
        'openai' => [
            'id' => 'openai',
            'active' => true,
            'api_key' => env('OPENAI_API_KEY'),
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'ping_url' => 'https://api.openai.com/v1/models',
            'models' => require __DIR__ . '/model_lists/openai_models.php',
        ],

        'gwdg' => [
            'id' => 'gwdg',
            'active' => true,
            'api_key' => env('GWDG_API_KEY'),
            'api_url' => 'https://chat-ai.academiccloud.de/v1/chat/completions',
            'ping_url' => 'https://chat-ai.academiccloud.de/v1/models',
            'models' => require __DIR__ . '/model_lists/gwdg_models.php',
        ],

        'google' => [
            'id' => 'google',
            'active' => true,
            'api_key' => env('GOOGLE_API_KEY'),
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'stream_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'ping_url' => '',
            'models' => require __DIR__ . '/model_lists/google_models.php',
        ],
        'ollama'=> [
            'active' => true,
            'id' => 'ollama',
            // 'api_url' => 'http://localhost:11434/api/generate',
            'api_url' => 'http://localhost:11434/api/chat',
            'models' => require __DIR__ . '/model_lists/ollama_models.php',
        ],

        'openWebUi' => [
            'id' => 'openWebUi',
            'active' => false,
            'api_key' => env('OPEN_WEB_UI_API_KEY'),
            'api_url' => 'your_url/api/chat/completions',
            'ping_url' => 'your_url/api/models',
            'models' => require __DIR__ . '/model_lists/openwebui_models.php',
        ]
    ]
];
