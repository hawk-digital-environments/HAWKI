<?php

return [

    /*
    |--------------------------------------------------------------------------
    |   Default AI Model
    |--------------------------------------------------------------------------
    |   This Model is used by default before the user choses their
    |   desired model.
    */
    'defaultModel' => 'gpt-4o',

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
        'title_generator' => 'gpt-4o-mini',
        'prompt_improver' => 'gpt-4o-mini',
        'summarizer' => 'gpt-4o-mini',
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
        'openai' => [
            'id' => 'openai',
            'active' => true,
            'api_key' => '',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'ping_url' => 'https://api.openai.com/v1/models',
            'models' => [
                [
                    'id' => 'gpt-4o',
                    'label' => 'OpenAI GPT 4o',
                    'streamable' => true,
                ],
                [
                    'id' => 'gpt-4o-mini',
                    'label' => 'OpenAI GPT 4o mini',
                    'streamable' => true,
                ],
                [
                    'id' => 'o1-mini',
                    'label' => 'OpenAI O1 mini',
                    'streamable' => false,
                ]
            ]
        ],

        'gwdg' => [
            'id' => 'gwdg',
            'active' => true,
            'api_key' => '',
            'api_url' => 'https://chat-ai.academiccloud.de/v1/chat/completions',
            'ping_url' => 'https://chat-ai.academiccloud.de/v1/models',
            'status_check' => true,

            'models' => [
                [
                    'id' => 'meta-llama-3.1-8b-instruct',
                    'label' => 'GWDG Meta Llama 3.1 8b Instruct',
                    'streamable' => true,
                ],
                [
                    'id' => 'meta-llama-3.1-70b-instruct',
                    'label' => 'GWDG Meta Llama 3.1 70b Instruct',
                    'streamable' => true,
                ],
                [
                    'id' => 'mistral-large-instruct',
                    'label' => 'GWDG Mistral Large Instruct',
                    'streamable' => true,
                ],
                [
                    'id' => 'qwen2.5-72b-instruct',
                    'label' => 'GWDG Qwen 2.5 72b Instruct',
                    'streamable' => true,
                ],
                [
                    'id' => 'deepseek-r1-distill-llama-70b',
                    'label' => 'GWDG DeepSeek R1 Distill Llama 70B',
                    'streamable' => true,
                ],
                [
                    'id' => 'codestral-22b',
                    'label' => 'GWDG Codestral 22b',
                    'streamable' => true,
                ],

            ]
        ],

        'google' => [
            'id' => 'google',
            'active' => true,
            'api_key' => '',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'streaming_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'ping_url' => '',
            'models' => [
                [
                    'id' => 'gemini-1.5-flash',
                    'label' => 'Google Gemini 1.5 Flash',
                    'streamable' => false,
                ],
                [
                    'id' => 'gemini-2.0-flash-lite',
                    'label' => 'Google Gemini 2.0 Flash Lite',
                    'streamable' => true,
                    'web_search' => false,
                ],
                [
                    'id' => 'gemini-2.0-flash-exp',
                    'label' => 'Google Gemini 2.0 Flash Exp',
                    'streamable' => false,
                ]
            ]
        ],
        'ollama' => [
            'id' => 'ollama',
            'active' => false,
            'api_url' => 'http://localhost:11434/api/generate',
            'models' => [
                [
                    'id' => 'llama3.2',
                    'label' => 'Llama3.2',
                    'streamable' => true,
                ]
            ]
        ],
        'openWebUi' => [
            'id' => 'openWebUi',
            'active' => false,
            'api_key' => '',
            'api_url' => 'your_url/api/chat/completions',
            'ping_url' => 'your_url/api/models',
            'models' => [
                [
                    'id' => 'model-id',
                    'label' => 'Model Name',
                    'streamable' => true,
                ]
            ]
        ],

    ]
];
