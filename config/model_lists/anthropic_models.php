<?php
return [
    [
        'active' => env('MODELS_ANTHROPIC_CLAUDE_3_5_SONNET_ACTIVE', true),
        'id' => 'claude-3-5-sonnet-20241022',
        'label' => 'Claude 3.5 Sonnet',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_ANTHROPIC_CLAUDE_3_5_SONNET_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_ANTHROPIC_CLAUDE_3_5_SONNET_TOOLS_VISION', true),
        ],
        'external' => env('MODELS_ANTHROPIC_CLAUDE_3_5_SONNET_EXTERNAL', true),
    ],
    [
        'active' => env('MODELS_ANTHROPIC_CLAUDE_3_5_HAIKU_ACTIVE', true),
        'id' => 'claude-3-5-haiku-20241022',
        'label' => 'Claude 3.5 Haiku',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_ANTHROPIC_CLAUDE_3_5_HAIKU_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_ANTHROPIC_CLAUDE_3_5_HAIKU_TOOLS_VISION', true),
        ],
        'external' => env('MODELS_ANTHROPIC_CLAUDE_3_5_HAIKU_EXTERNAL', true),
    ],
    [
        'active' => env('MODELS_ANTHROPIC_CLAUDE_3_OPUS_ACTIVE', true),
        'id' => 'claude-3-opus-20240229',
        'label' => 'Claude 3 Opus',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_ANTHROPIC_CLAUDE_3_OPUS_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_ANTHROPIC_CLAUDE_3_OPUS_TOOLS_VISION', true),
        ],
        'external' => env('MODELS_ANTHROPIC_CLAUDE_3_OPUS_EXTERNAL', false), // Expensive
    ],
    [
        'active' => env('MODELS_ANTHROPIC_CLAUDE_3_SONNET_ACTIVE', true),
        'id' => 'claude-3-sonnet-20240229',
        'label' => 'Claude 3 Sonnet',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_ANTHROPIC_CLAUDE_3_SONNET_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_ANTHROPIC_CLAUDE_3_SONNET_TOOLS_VISION', true),
        ],
        'external' => env('MODELS_ANTHROPIC_CLAUDE_3_SONNET_EXTERNAL', true),
    ],
    [
        'active' => env('MODELS_ANTHROPIC_CLAUDE_3_HAIKU_ACTIVE', true),
        'id' => 'claude-3-haiku-20240307',
        'label' => 'Claude 3 Haiku',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_ANTHROPIC_CLAUDE_3_HAIKU_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_ANTHROPIC_CLAUDE_3_HAIKU_TOOLS_VISION', true),
        ],
        'external' => env('MODELS_ANTHROPIC_CLAUDE_3_HAIKU_EXTERNAL', true),
    ],
];
