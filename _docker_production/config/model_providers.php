<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    |   Default AI Models
    |--------------------------------------------------------------------------
    |   These Models are used as predefined models for each task.
    |   Make sure that the model is included and active in the providers list below.
    |
    */
    'default_models' => [
        'default_model' => env('DEFAULT_MODEL', 'gpt-4.1-nano'),
        'default_web_search_model' => env('DEFAULT_WEBSEARCH_MODEL', 'gemini-2.0-flash'),
        'default_file_upload_model' => env('DEFAULT_FILEUPLOAD_MODEL', 'meta-llama-3.1-8b-instruct'),
        'default_vision_model' => env('DEFAULT_VISION_MODEL', 'qwen2.5-vl-72b-instruct'),
    ],
    
    /*
     * The default models to use when accessing HAWKI via an external application
     * If null, the general default models are used above (can be useful to prevent high cost models being used by external apps)
     */
    'default_models.ext_app' => [
//        'default_model' => null,
//        'default_web_search_model' => null,
//        'default_file_upload_model' => null,
//        'default_vision_model' => null,
    ],
    
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
        'title_generator' => env('TITLE_GENERATOR_MODEL', 'gpt-4.1-nano'),
        'prompt_improver' => env('PROMPT_IMPROVEMENT_MODEL', 'gpt-4.1-nano'),
        'summarizer' => env('SUMMARIZER_MODEL', 'gpt-4.1-nano'),
    ],
    
    /*
     * The system models to use when accessing HAWKI via an external application
     * If null, the general system models are used above (can be useful to prevent high cost models being used by external apps)
     */
    'system_models.ext_app' => [
//        'title_generator' => null,
//        'prompt_improver' => null,
//        'summarizer' => null,
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
            'active' => true,
            'api_key' => env('OPENAI_API_KEY'),
            'api_url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
            'ping_url' => env('OPENAI_PING_URL', 'https://api.openai.com/v1/models'),
            'models' => require __DIR__ . env('OPENAI_MODEL_LIST_DIR', '/model_lists/openai_models.php'),
        ],
        'gwdg' => [
            'active' => env('GWDG_ACTIVE', true),
            'api_key' => env('GWDG_API_KEY'),
            'api_url' => env('GWDG_API_URL', 'https://chat-ai.academiccloud.de/v1/chat/completions'),
            'ping_url' => env('GWDG_PING_URL', 'https://chat-ai.academiccloud.de/v1/models'),
            'models' => require __DIR__ . env('GWDG_MODEL_LIST_DIR', '/model_lists/gwdg_models.php'),
        ],
        'google' => [
            'active' => env('GOOGLE_ACTIVE', true),
            'api_key' => env('GOOGLE_API_KEY'),
            'api_url' => env('GOOGLE_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/'),
            'stream_url' => env('GOOGLE_STREAM_URL', 'https://generativelanguage.googleapis.com/v1beta/models/'),
            'ping_url' => env('GOOGLE_PING_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            'models' => require __DIR__ . env('GOOGLE_MODEL_LIST_DIR', '/model_lists/google_models.php'),
        ],
        'ollama' => [
            'active' => env('OLLAMA_ACTIVE', false),
            'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/api/chat'),
            'ping_url' => env('OLLAMA_API_URL', 'http://localhost:11434/api/tags'),
            'models' => require __DIR__ . env('OLLAMA_MODEL_LIST_DIR', '/model_lists/ollama_models.php'),
        ],
        'openWebUi' => [
            'active' => env('OPEN_WEB_UI_ACTIVE', false),
            'api_key' => env('OPEN_WEB_UI_API_KEY'),
            'api_url' => env('OPEN_WEB_UI_API_URL', 'your_url/api/chat/completions'),
            'ping_url' => env('OPEN_WEB_UI_PING_URL', 'your_url/api/models'),
            'models' => require __DIR__ . env('OPEN_WEB_UI_MODEL_LIST_DIR', '/model_lists/openwebui_models.php'),
        ]
    ]
];
