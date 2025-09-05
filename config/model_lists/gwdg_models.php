<?php

return [
    [
        'active'=> env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_ACTIVE', true),
        "id"=> "meta-llama-3.1-8b-instruct",
        "label"=> "GWDG Meta Llama 3.1 8B Instruct",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_GEMMA_3_27B_IT_ACTIVE', true),
        "id"=> "gemma-3-27b-it",
        "label"=> "GWDG Gemma 3 27B Instruct",
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_GEMMA_3_27B_IT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_GEMMA_3_27B_IT_TOOLS_VISION', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_QWEN3_32B_ACTIVE', true),
        "id"=> "qwen3-32b",
        "label"=> "GWDG Qwen 3 32B",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text",
            "thought"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_32B_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_QWEN3_235B_A22B_ACTIVE', true),
        "id"=> "qwen3-235b-a22b",
        "label"=> "GWDG Qwen 3 235B A22B",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text",
            "thought"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_235B_A22B_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_ACTIVE', true),
        "id"=> "llama-3.3-70b-instruct",
        "label"=> "GWDG Meta Llama 3.3 70B Instruct",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_LLAMA_3_3_70B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_QWEN2_5_VL_72B_INSTRUCT_ACTIVE', true),
        "id"=> "qwen2.5-vl-72b-instruct",
        "label"=> "GWDG Qwen 2.5 VL 72B Instruct",
        "input"=> [
            "text",
            "image",
            "video"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN2_5_VL_72B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN2_5_VL_72B_INSTRUCT_TOOLS_VISION', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_MEDGEMMA_27B_IT_ACTIVE', true),
        "id"=> "medgemma-27b-it",
        "label"=> "GWDG MedGemma 27B Instruct",
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_MEDGEMMA_27B_IT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_MEDGEMMA_27B_IT_TOOLS_VISION', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_QWQ_32B_ACTIVE', true),
        "id"=> "qwq-32b",
        "label"=> "GWDG Qwen QwQ 32B",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text",
            "thought"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWQ_32B_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_DEEPSEEK_R1_ACTIVE', true),
        "id"=> "deepseek-r1",
        "label"=> "GWDG DeepSeek R1",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text",
            "thought"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_DEEPSEEK_R1_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_ACTIVE', true),
        "id"=> "deepseek-r1-distill-llama-70b",
        "label"=> "GWDG DeepSeek R1 Distill Llama 70B",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text",
            "thought"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_MISTRAL_LARGE_INSTRUCT_ACTIVE', true),
        "id"=> "mistral-large-instruct",
        "label"=> "GWDG Mistral Large Instruct",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_MISTRAL_LARGE_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_QWEN2_5_CODER_32B_INSTRUCT_ACTIVE', true),
        "id"=> "qwen2.5-coder-32b-instruct",
        "label"=> "GWDG Qwen 2.5 Coder 32B Instruct",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_QWEN2_5_CODER_32B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_INTERNVL2_5_8B_ACTIVE', true),
        "id"=> "internvl2.5-8b",
        "label"=> "GWDG InternVL2.5 8B MPO",
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_INTERNVL2_5_8B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_INTERNVL2_5_8B_TOOLS_VISION', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_ACTIVE', true),
        "id"=> "teuken-7b-instruct-research",
        "label"=> "GWDG Teuken 7B Instruct Research",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_TEUKEN_7B_INSTRUCT_RESEARCH_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_CODESTRAL_22B_ACTIVE', true),
        "id"=> "codestral-22b",
        "label"=> "GWDG Codestral 22B",
        "input"=> [
            "text"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_CODESTRAL_22B_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_ACTIVE', true),
        "id"=> "llama-3.1-sauerkrautlm-70b-instruct",
        "label"=> "GWDG Llama 3.1 SauerkrautLM 70B Instruct",
        "input"=> [
            "text",
            "arcana"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_LLAMA_3_1_SAUERKRAUTLM_70B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
        ],
    ],
    [
        'active'=> env('MODELS_GWDG_META_LLAMA_3_1_8B_RAG_ACTIVE', true),
        "id"=> "meta-llama-3.1-8b-rag",
        "label"=> "GWDG Meta Llama 3.1 8B RAG",
        "input"=> [
            "text",
            "arcana"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'file_upload' => env('MODELS_GWDG_META_LLAMA_3_1_8B_RAG_TOOLS_FILE_UPLOAD', true),
        ],
    ],
];
