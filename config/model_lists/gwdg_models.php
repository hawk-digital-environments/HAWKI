<?php

return [
    [
        'active' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_ACTIVE', true),
        'id' => 'apertus-70b-instruct-2509',
        'label' => 'GWDG Apertus 70B Instruct 2509',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Academic fine-tune; tool-calling capability not confirmed
            'file_upload' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.8, top_p=0.9
            'temp' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_PARAMS_TEMP', 0.8),
            'top_p' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_PARAMS_TOP_P', 0.9),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_ACTIVE', true),
        'id' => 'deepseek-r1-distill-llama-70b',
        'label' => 'GWDG DeepSeek R1 Distill Llama 70B',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Reasoning/R1 model; chain-of-thought output is incompatible with tool calling
            'file_upload' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_PARAMS_TOP_P', 0.8),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_ACTIVE', true),
        'id' => 'devstral-2-123b-instruct-2512',
        'label' => 'GWDG Devstral 2 123B Instruct 2512',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG lists no specific recommendation; API defaults apply unless overridden via env
            'temp' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_PARAMS_TEMP'),
            'top_p' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_PARAMS_TOP_P'),
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
        'active' => env('MODELS_GWDG_GLM_4_7_ACTIVE', true),
        'id' => 'glm-4.7',
        'label' => 'GWDG GLM 4.7',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_GLM_4_7_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_GLM_4_7_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GLM_4_7_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_INTERNVL3_5_30B_A3B_ACTIVE', true),
        'id' => 'internvl3.5-30b-a3b',
        'label' => 'GWDG InternVL 3.5 30B A3B',
        'input' => [
            'text',
            'image',
            'video',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Vision-focused model; tool-calling not exposed via GWDG API
            'file_upload' => env('MODELS_GWDG_INTERNVL3_5_30B_A3B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_INTERNVL3_5_30B_A3B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Model card recommends temp=0.6 and top_p=0.95, especially when thinking mode is enabled
            'temp' => env('MODELS_GWDG_INTERNVL3_5_30B_A3B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_INTERNVL3_5_30B_A3B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_MEDGEMMA_27B_IT_ACTIVE', true),
        'id' => 'medgemma-27b-it',
        'label' => 'GWDG MedGemma 27B Instruct',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Medical fine-tune; specialized task model, tool calling not supported
            'file_upload' => env('MODELS_GWDG_MEDGEMMA_27B_IT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // vLLM example config for MedGemma 27B Instruct uses temp=0.7 and top_p=0.95
            'temp' => env('MODELS_GWDG_MEDGEMMA_27B_IT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_MEDGEMMA_27B_IT_PARAMS_TOP_P', 0.95),
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
        'active' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_ACTIVE', true),
        'id' => 'mistral-large-3-675b-instruct-2512',
        'label' => 'GWDG Mistral Large 3 675B Instruct 2512',
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
            'file_upload' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommends near-deterministic temp (<0.1) for typical tasks; top_p=0.95 is the standard vLLM value
            'temp' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TEMP', 0.1),
            'top_p' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_ACTIVE', true),
        'id' => 'openai-gpt-oss-120b',
        'label' => 'GWDG OpenAI GPT OSS 120B',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official sampling recommendations; OpenAI API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TOP_P', 1.0),
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
        'active' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_ACTIVE', true),
        'id' => 'qwen3-coder-30b-a3b-instruct',
        'label' => 'GWDG Qwen 3 Coder 30B A3B Instruct',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Model card best practices: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_PARAMS_TOP_P', 0.8),
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
        'active' => env('MODELS_GWDG_QWEN35_122B_A10B_ACTIVE', true),
        'id' => 'qwen3.5-122b-a10b',
        'label' => 'GWDG Qwen 3.5 122B A10B',
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
            'file_upload' => env('MODELS_GWDG_QWEN35_122B_A10B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN35_122B_A10B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN35_122B_A10B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN35_122B_A10B_PARAMS_TOP_P', 0.95),
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
        'active' => env('MODELS_GWDG_QWEN_3.6_27B_ACTIVE', true),
        'id' => 'qwen3.6-27b',
        'label' => 'GWDG Qwen 3.6 27B',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN_3.6_27B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN_3.6_27B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN_3.6_27B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_QWEN_3.6_27B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN_3.6_35B_A3B_ACTIVE', true),
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
            'file_upload' => env('MODELS_GWDG_QWEN_3.6_35B_A3B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN_3.6_35B_A3B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN_3.6_35B_A3B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_QWEN_3.6_35B_A3B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_ACTIVE', true),
        'id' => 'teuken-7b-instruct-research',
        'label' => 'GWDG Teuken 7B Instruct Research',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Specialized German/multilingual research model; tool calling not supported
            'file_upload' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No specific recommendations available; API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_MISTRAL_7B_INSTRUCT_ACTIVE', true),
        'id' => 'e5-mistral-7b-instruct',
        'label' => 'GWDG E5 Mistral 7B Instruct',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Specialized German/multilingual research model; tool calling not supported
            'file_upload' => env('MODELS_GWDG_MISTRAL_7B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No specific recommendations available; API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_MISTRAL_7B_INSTRUCT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_MISTRAL_7B_INSTRUCT_PARAMS_TOP_P', 1.0),
        ],
    ],
];
