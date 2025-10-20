<?php

/**
 * Responses API Model List
 * 
 * These models are compatible with OpenAI's Responses API (/v1/responses)
 * Only GPT-4/5/6 families are supported (o1/o3 models are NOT compatible)
 * 
 * All models support STREAMING ONLY (non-streaming not available)
 */

return [
    [
        'active' => env('MODELS_RESPONSES_GPT5_ACTIVE', true),
        'id' => 'gpt-5',
        'label' => 'GPT-5 (Responses API)',
        'input' => [
            'text',
            'image'
        ],
        'output' => [
            'text'
        ],
        'tools' => [
            'stream' => true,              // Streaming only
            'file_upload' => false,        // Not supported yet in Responses API
            'vision' => true,
            'web_search' => true,
        ],
        'metadata' => [
            'api_format' => 'responses',   // Distinguish from chat completions
            'supports_reasoning' => true,
            'supports_mcp_tools' => true,
        ],
        'ai_model_tools' => [
            // API tool parameters for Responses API
            // These are sent in the 'tools' array to the API endpoint
            [
                'type' => 'web_search',
            ],
        ],
    ],
    [
        'active' => env('MODELS_RESPONSES_GPT4_1_ACTIVE', true),
        'id' => 'gpt-4.1',
        'label' => 'GPT-4.1 (Responses API)',
        'input' => [
            'text',
            'image'
        ],
        'output' => [
            'text'
        ],
        'tools' => [
            'stream' => true,              // Streaming only
            'file_upload' => false,        // Not supported yet in Responses API
            'vision' => true,
        ],
        'metadata' => [
            'api_format' => 'responses',   // Distinguish from chat completions
            'supports_reasoning' => true,
            'supports_mcp_tools' => true,
        ],
        'ai_model_tools' => [
            // No web_search for gpt-4.1 - add if needed
        ],
    ],
    [
        'active' => env('MODELS_RESPONSES_GPT4_1_NANO_ACTIVE', true),
        'id' => 'gpt-4.1-nano',
        'label' => 'GPT-4.1 Nano (Responses API)',
        'input' => [
            'text',
            'image'
        ],
        'output' => [
            'text'
        ],
        'tools' => [
            'stream' => true,              // Streaming only
            'file_upload' => false,        // Not supported yet in Responses API
            'vision' => true,
        ],
        'metadata' => [
            'api_format' => 'responses',   // Distinguish from chat completions
            'supports_reasoning' => true,
            'supports_mcp_tools' => true,
        ],
        'ai_model_tools' => [
            // No web_search for gpt-4.1-nano - add if needed
        ],
    ],
];
