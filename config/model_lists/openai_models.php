<?php
return [
    [
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-5',
        'label' => 'OpenAI GPT 5',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            // Basic features (@deprecated - will migrate to capabilities system)
            'stream' => 'native',
            'file_upload' => 'native',
            'vision'=> 'native',

            // Tool execution strategies
            'test_tool' => 'function_call',
            'dice_roll' => 'mcp',  // Model supports MCP protocol
        ],
    ],
    [
        'active'=> env('MODELS_OPENAI_GPT4_1_ACTIVE', default: true),
        'id' => 'gpt-4.1',
        'label' => 'OpenAI GPT 4.1',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => 'native',
            'file_upload' => 'native',
            'vision'=> 'native',

            'test_tool' => 'function_call',
//            'dice_roll' => 'mcp',
        ],
    ],
    [
        'active'=> env('MODELS_OPENAI_GPT4_1_NANO_ACTIVE', true),
        'id' => 'gpt-4.1-nano',
        'label' => 'OpenAI GPT 4.1 Nano',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => 'native',
            'file_upload' => 'unsupported',
            'vision'=> 'native',

        ],
    ],
    [
        'active'=> env('MODELS_OPENAI_O4_MINI_ACTIVE', true),
        'id' => 'o4-mini',
        'label' => 'OpenAI o4 mini',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => 'native',
            'file_upload' => 'unsupported',
            'vision'=> 'unsupported',

            'test_tool' => 'function_call',
        ],
    ],
];
