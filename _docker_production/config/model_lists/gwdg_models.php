<?php

return [
    [
        'active' => env('MODELS_GWDG_DEEPSEEK_V4_FLASH_ACTIVE', true),
        'id' => 'deepseek-v4-flash-0731',
        'label' => 'GWDG DeepSeek V4 Flash',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_DEEPSEEK_V4_FLASH_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=1.0
            'temp' => env('MODELS_GWDG_DEEPSEEK_V4_FLASH_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_DEEPSEEK_V4_FLASH_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_GEMMA_4_31B_INSTRUCT_ACTIVE', true),
        'id' => 'gemma-4-31b-it',
        'label' => 'GWDG Gemma 4 31B Instruct',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_GEMMA_4_31B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG lists no specific recommendation; API defaults apply unless overridden via env
            'temp' => env('MODELS_GWDG_GEMMA_4_31B_INSTRUCT_PARAMS_TEMP'),
            'top_p' => env('MODELS_GWDG_GEMMA_4_31B_INSTRUCT_PARAMS_TOP_P'),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_GLM_5_3_FLASH_ACTIVE', true),
        'id' => 'glm-5.3-flash',
        'label' => 'GWDG GLM 5.3 Flash',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_GLM_5_3_FLASH_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_GLM_5_3_FLASH_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_GLM_5_3_FLASH_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GLM_5_3_FLASH_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_ACTIVE', true),
        'id' => 'meta-llama-3.1-8b-instruct',
        'label' => 'GWDG Meta Llama 3.1 8B Instruct',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official sampling recommendations from Meta; API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_ACTIVE', true),
        'id' => 'qwen3-30b-a3b-instruct-2507',
        'label' => 'GWDG Qwen 3 30B A3B Instruct 2507',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.6, top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_CODER_NEXT_FP8_ACTIVE', true),
        'id' => 'qwen3-coder-next',
        'label' => 'GWDG Qwen 3 Coder Next',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_CODER_NEXT_FP8_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG lists no specific recommendation; API defaults apply unless overridden via env
            'temp' => env('MODELS_GWDG_QWEN3_CODER_NEXT_FP8_PARAMS_TEMP'),
            'top_p' => env('MODELS_GWDG_QWEN3_CODER_NEXT_FP8_PARAMS_TOP_P'),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_ACTIVE', true),
        'id' => 'qwen3-omni-30b-a3b-instruct',
        'label' => 'GWDG Qwen 3 Omni 30B A3B Instruct',
        'input' => [
            'text',
            'image',
            'audio',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG lists no specific recommendation; API defaults apply unless overridden via env
            'temp' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TEMP'),
            'top_p' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TOP_P'),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN35_397B_A17B_ACTIVE', true),
        'id' => 'qwen3.5-397b-a17b',
        'label' => 'GWDG Qwen 3.5 397B A17B',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
            'thought',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN35_397B_A17B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN35_397B_A17B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN35_397B_A17B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN35_397B_A17B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN38_27B_ACTIVE', true),
        'id' => 'qwen3.8-27b',
        'label' => 'GWDG Qwen 3.8 27B',
        'input' => [
            'text',
            'image'
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN38_27B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN38_27B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN38_27B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_QWEN38_27B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN36_35B_A3B_ACTIVE', true),
        'id' => 'qwen3.6-35b-a3b',
        'label' => 'GWDG Qwen 3.6 35B A3B',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN36_35B_A3B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN36_35B_A3B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN36_35B_A3B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_QWEN36_35B_A3B_PARAMS_TOP_P', 0.95),
        ],
    ],
];
