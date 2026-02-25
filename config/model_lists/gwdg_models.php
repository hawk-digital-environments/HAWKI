<?php

return [
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
            'file_upload' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Model card best practices: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_QWEN3_CODER_30B_A3B_INSTRUCT_PARAMS_TOP_P', 0.8),
        ],
    ],
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
            'file_upload' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.8, top_p=0.9
            'temp' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_PARAMS_TEMP', 0.8),
            'top_p' => env('MODELS_GWDG_APERTUS_70B_INSTRUCT_2509_PARAMS_TOP_P', 0.9),
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
            'file_upload' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Mistral recommends temp=0.2 for optimal coding performance; top_p=0.95 is the standard vLLM sampling value
            'temp' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_PARAMS_TEMP', 0.2),
            'top_p' => env('MODELS_GWDG_DEVSTRAL_2_123B_INSTRUCT_2512_PARAMS_TOP_P', 0.95),
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
            'file_upload' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TOP_P', 0.95),
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
        'active' => env('MODELS_GWDG_QWEN3_VL_30B_A3B_INSTRUCT_ACTIVE', true),
        'id' => 'qwen3-vl-30b-a3b-instruct',
        'label' => 'GWDG Qwen 3 VL 30B A3B Instruct',
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
            'file_upload' => env('MODELS_GWDG_QWEN3_VL_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_VL_30B_A3B_INSTRUCT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Vision-language variant of Qwen 3 30B; thinking-mode defaults (0.6/0.95) applied per model card
            'temp' => env('MODELS_GWDG_QWEN3_VL_30B_A3B_INSTRUCT_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_VL_30B_A3B_INSTRUCT_PARAMS_TOP_P', 0.95),
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
            'file_upload' => env('MODELS_GWDG_GLM_4_7_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_GLM_4_7_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GLM_4_7_PARAMS_TOP_P', 0.95),
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
            'file_upload' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG recommends near-deterministic temp (<0.1) for typical tasks; top_p=0.95 is the standard vLLM value
            'temp' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TEMP', 0.1),
            'top_p' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TOP_P', 0.95),
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
            'file_upload' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official sampling recommendations from Meta; API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_PARAMS_TOP_P', 1.0),
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
            'file_upload' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official sampling recommendations; OpenAI API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_GEMMA_3_27B_IT_ACTIVE', true),
        'id' => 'gemma-3-27b-it',
        'label' => 'GWDG Gemma 3 27B Instruct',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_GEMMA_3_27B_IT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_GEMMA_3_27B_IT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Google generation_config.json for Gemma 3 27B sets temp=1.0 and top_p=0.96
            'temp' => env('MODELS_GWDG_GEMMA_3_27B_IT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GEMMA_3_27B_IT_PARAMS_TOP_P', 0.96),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_30B_A3B_THINKING_2507_ACTIVE', true),
        'id' => 'qwen3-30b-a3b-thinking-2507',
        'label' => 'GWDG Qwen 3 30B A3B Thinking 2507',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
            'thought'
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_30B_A3B_THINKING_2507_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Thinking mode: model card recommends temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_30B_A3B_THINKING_2507_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_30B_A3B_THINKING_2507_PARAMS_TOP_P', 0.95),
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
            'thought'
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Non-thinking mode: model card recommends temp=0.7 and top_p=0.8
            'temp' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TOP_P', 0.8),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_32B_ACTIVE', true),
        'id' => 'qwen3-32b',
        'label' => 'GWDG Qwen 3 32B',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_32B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Generation config sets temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_32B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_32B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_235B_A22B_ACTIVE', true),
        'id' => 'qwen3-235b-a22b',
        'label' => 'GWDG Qwen 3 235B A22B Thinking 2507',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
            'thought'
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_235B_A22B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Thinking mode: model card recommends temp=0.6 and top_p=0.95; greedy decoding is discouraged
            'temp' => env('MODELS_GWDG_QWEN3_235B_A22B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_235B_A22B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_ACTIVE', true),
        'id' => 'llama-3.3-70b-instruct',
        'label' => 'GWDG Meta Llama 3.3 70B Instruct',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_PARAMS_TOP_P', 0.8),
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
            'thought'
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_MEDGEMMA_27B_IT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_MEDGEMMA_27B_IT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM example config for MedGemma 27B Instruct uses temp=0.7 and top_p=0.95
            'temp' => env('MODELS_GWDG_MEDGEMMA_27B_IT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_MEDGEMMA_27B_IT_PARAMS_TOP_P', 0.95),
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
            'file_upload' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_PARAMS_TOP_P', 0.8),
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
            'file_upload' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No specific recommendations available; API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_ACTIVE', true),
        'id' => 'llama-3.1-sauerkrautlm-70b-instruct',
        'label' => 'GWDG Llama 3.1 SauerkrautLM 70B Instruct',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official values; community chat script for SauerkrautLM uses temp=0.7 and top_p=0.9
            'temp' => env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_PARAMS_TOP_P', 0.9),
        ],
    ],
];
