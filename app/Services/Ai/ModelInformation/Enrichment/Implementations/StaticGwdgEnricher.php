<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\WellKnownModelParams;
use App\Services\Ai\Providers\Adapters\Implementations\GwdgAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\Container\ServiceLocatorTrait;
use App\Services\Translation\LocaleService;
use App\Utils\JobMetrics;

/**
 * Enricher that applies handcrafted metadata to open-weight models hosted by GWDG.
 *
 * GWDG (Gesellschaft für wissenschaftliche Datenverarbeitung) self-hosts a curated set of
 * open-weight models on university infrastructure. These models are not in the LiteLLM
 * catalog, so their metadata (description, flags, default parameters, token limits,
 * documentation URL) is maintained manually in the static DATA array, keyed by model ID.
 *
 * Only models whose `model_id` appears in DATA are affected; all others are returned
 * unchanged. A model-specific documentation URL is only applied when the model still has
 * the provider-level default URL ({@see GwdgAdapter::DEFAULT_DOCUMENTATION_URL}).
 */
class StaticGwdgEnricher implements ModelInfoEnricherInterface
{
    use ModelInfoEnrichingTrait;
    use ServiceLocatorTrait;

    private const array DATA = [
        'apertus-70b-instruct-2509' => [
            'max_tokens' => 65000,
            'documentation_url' => 'https://huggingface.co/RedHatAI/Apertus-70B-Instruct-2509-FP8-dynamic',
            'description' => 'Apertus is a fully open language model designed to push the boundaries of transparent and compliant AI. It supports over 1,800 languages and a context window size of up to 65,536 tokens, using only fully compliant and open training data. The model achieves comparable performance to closed-source models while respecting opt-out consent of data owners. It was pretrained on 15T tokens with a staged curriculum of web, code, and math data.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.8,
                WellKnownModelParams::TOP_P => 0.9,
            ],
        ],
        'deepseek-r1-distill-llama-70b' => [
            'max_tokens' => 32000,
            'documentation_url' => 'https://huggingface.co/deepseek-ai/DeepSeek-R1-Distill-Llama-70B',
            'description' => 'Developed by the Chinese company DeepSeek (深度求索), DeepSeek R1 Distill Llama 70B is a dense model distilled from DeepSeek-R1 but based on LLama 3.3 70B, in order to fit the capabilities and performance of R1 into a 70B parameter-size model.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_REASONING,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.7,
                WellKnownModelParams::TOP_P => 0.8,
            ],
        ],
        'devstral-2-123b-instruct-2512' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/mistralai/Devstral-2-123B-Instruct-2512',
            'description' => 'Developed by mistralai, Devstral 2 is an agentic LLM designed for software engineering and coding tasks. It is capable of exploring codebases, working with multiple files, and powering software engineering agents.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'gemma-4-31b-it' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/google/gemma-4-31B-it',
            'description' => 'Gemma 4 models offer frontier-level performance, well-suited for reasoning, agentic workflows, coding, and multimodal understanding.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_REASONING,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
        ],
        'glm-4.7' => [
            'max_tokens' => 200000,
            'documentation_url' => 'https://huggingface.co/zai-org/GLM-4.7-FP8',
            'description' => 'GLM-4.7 is a coding-focused model that delivers significant improvements over its predecessor in multilingual agentic coding and terminal-based tasks. It achieves strong performance on SWE-bench, SWE-bench Multilingual, and Terminal Bench 2.0. GLM-4.7 also excels at tool use, web browsing, and mathematical reasoning, with notable gains on benchmarks like HLE and τ²-Bench.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 1.0,
                WellKnownModelParams::TOP_P => 0.95,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING
            ],
        ],
        'internvl3.5-30b-a3b' => [
            'max_tokens' => 40000,
            'documentation_url' => 'https://huggingface.co/OpenGVLab/InternVL3_5-30B-A3B-HF',
            'description' => 'InternVL 3.5 30B-A3B is a lightweight, fast and powerful multimodal model developed by OpenGVLab. It significantly advances versatility, reasoning capability, and efficiency, by featuring a Visual Resolution Router (ViR) for dynamic visual token adjustment and Decoupled Vision-Language Deployment (DvD) for efficient GPU load balancing, achieving up to 4× inference speedup compared to its predecessor. The model excels at multimodal reasoning, OCR, document understanding, multi-image comprehension, video understanding, GUI tasks, and embodied agency.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
        ],
        'medgemma-27b-it' => [
            'max_tokens' => 128000,
            'documentation_url' => 'https://huggingface.co/google/medgemma-27b-it',
            'description' => 'MedGemma 27B Instruct is a variant of Gemma 3 suitable for medical text and image comprehension. It has been trained on a variety of medical image data, including chest X-rays, dermatology images, ophthalmology images, and histopathology slides, as well as medical text, such as medical question-answer pairs, and FHIR-based electronic health record data. MedGemma variants have been evaluated on a range of clinically relevant benchmarks to illustrate their baseline performance.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
        ],
        'meta-llama-3.1-8b-instruct' => [
            'max_tokens' => 128000,
            'documentation_url' => 'https://huggingface.co/nvidia/Llama-3.1-8B-Instruct-FP8',
            'description' => 'The standard model we recommend. It is the most lightweight with the fastest performance and good results across all benchmarks. It is sufficient for general conversations and assistance.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'mistral-large-3-675b-instruct-2512' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/mistralai/Mistral-Large-3-675B-Instruct-2512-NVFP4',
            'description' => 'Developed by Mistral AI, Mistral Large 3 is a general-purpose multimodal MoE model with 675B total and 41B active parameters. This model is fine-tuned for instruction tasks, ideal for chat, agentic and instruction based use cases. It supports dozens of languages, including English, French, Spanish, German, Italian, Portuguese, Dutch, Chinese, Japanese, Korean, Arabic, and has a large 256K context window size.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'openai-gpt-oss-120b' => [
            'max_tokens' => 128000,
            'documentation_url' => 'https://huggingface.co/openai/gpt-oss-120b',
            'description' => 'In August 2025, OpenAI released the gpt-oss model series, consisting of two open-weight LLMs that are optimized for faster inference with state-of-the-art performance across many domains, including reasoning and tool use. According to OpenAI, the gpt-oss-120b model achieves near-parity with OpenAI o4-mini on core reasoning benchmarks.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_REASONING,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3-30b-a3b-instruct-2507' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3-30B-A3B-Instruct-2507-FP8',
            'description' => 'This MoE model features 30.5B total parameters with 3.3B activated parameters for efficient inference. It delivers significant improvements in instruction following, logical reasoning, text comprehension, mathematics, science, coding, and tool usage, with better alignment for subjective and open-ended tasks. The model supports a 256K native context length and operates in non-thinking mode, achieving strong performance across knowledge, reasoning, coding, and multilingual benchmarks.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.6,
                WellKnownModelParams::TOP_P => 0.95,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3-coder-30b-a3b-instruct' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3-Coder-30B-A3B-Instruct-FP8',
            'description' => 'Qwen 3 Coder 30B A3B Instruct is a specialized coding model that achieves strong performance on agentic coding, browser-use, and other foundational coding tasks among open models.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.7,
                WellKnownModelParams::TOP_P => 0.8,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING
            ]
        ],
        'qwen3-coder-next' => [
            'max_tokens' => 256000,
            // TODO(reference missing): no verified Hugging Face/GWDG model card found (released Feb 2026). Fill documentation_url once published.
            // TODO(reference missing): description unavailable pending the official model card.
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            // Reference lists sampling as "default"; no explicit temperature/top_p recommendation, so parameters are intentionally omitted.
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3-omni-30b-a3b-instruct' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3-Omni-30B-A3B-Instruct',
            'description' => 'Qwen3 Omni is a natively multilingual omni-modal foundation model that processes text, images, audio, and video. It achieves state-of-the-art performance on many audio/video benchmarks with ASR, audio understanding, and voice conversation performance comparable to Gemini 2.5 Pro. The model features a novel MoE-based Thinker–Talker architecture with AuT pretraining, supports 119 text languages, 19 speech input languages, and enables low-latency interaction with flexible control via system prompts.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3.5-122b-a10b' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3.5-122B-A10B-GPTQ-Int4',
            'description' => 'Qwen 3.5 122B A10B is a powerful language model developed by Alibaba Cloud. With 122 billion parameters it delivers strong performance across reasoning, coding, and general tasks. The model supports vision capabilities for multimodal applications.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.6,
                WellKnownModelParams::TOP_P => 0.95,
            ],
        ],
        'qwen3.5-397b-a17b' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3.5-397B-A17B-GPTQ-Int4',
            'description' => 'Qwen 3.5 397B A17B is a MoE model with 397 billion total parameters and 17 billion activated parameters. It represents one of the most powerful open-weight models available, delivering exceptional performance across reasoning, coding, mathematics, and general tasks. The model supports vision capabilities, and provides state-of-the-art performance among open models.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 0.6,
                WellKnownModelParams::TOP_P => 0.95,
            ],
        ],
        'qwen3.6-27b' => [
            'max_tokens' => 262000,
            // TODO(reference missing): no verified Hugging Face/GWDG model card found (released Apr 2026; likely https://huggingface.co/Qwen/Qwen3.6-27B-FP8, unverified). Fill documentation_url once confirmed.
            // TODO(reference missing): description unavailable pending the official model card.
            // NOTE: GWDG's model table lists this model as "Vision". If confirmed multi-modal, add WellKnownModelFlags::MULTI_MODAL here and 'image' to its input in config/model_lists/gwdg_models.php (currently 'text' only, though the config still carries a 'vision' tool flag).
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 1.0,
                WellKnownModelParams::TOP_P => 0.95,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3.6-35b-a3b' => [
            'max_tokens' => 262000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3.6-35B-A3B-FP8',
            'description' => 'Qwen 3.6 35B A3B is an MoE model with 35 billion total parameters and 3 billion activated parameters for efficient inference. Built on direct feedback from the community, Qwen 3.6 prioritizes stability, real-world utility, including for coding and agentic tasks.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::MULTI_MODAL,
                WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 1.0,
                WellKnownModelParams::TOP_P => 0.95,
            ],
        ],
        'teuken-7b-instruct-research' => [
            'max_tokens' => 128000,
            'documentation_url' => 'https://huggingface.co/openGPT-X/Teuken-7B-instruct-research-v0.4',
            'description' => 'OpenGPT-X is a research project funded by the German Federal Ministry of Economics and Climate Protection (BMWK) and led by Fraunhofer, Forschungszentrum Jülich, TU Dresden, and DFKI. Teuken 7B Instruct Research v0.4 is an instruction-tuned 7B parameter multilingual LLM pre-trained with 4T tokens, focusing on covering all 24 EU languages and reflecting European values.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
        ],
        'e5-mistral-7b-instruct' => [
            'max_tokens' => 4096,
            'documentation_url' => 'https://huggingface.co/intfloat/e5-mistral-7b-instruct',
            'description' => 'E5 Mistral 7B Instruct is a text-embedding model developed by intfloat, initialized from Mistral 7B and fine-tuned to produce high-quality embeddings for retrieval, semantic similarity, and clustering across roughly 100 languages. It is an embedding model exposed via API only, not a conversational chat model.',
            // NOTE: This is an embedding ("API Only") model. WellKnownModelFlags has no embeddings-specific flag, so only OPEN_WEIGHTS is applied. Confirm whether an embedding model belongs in the chat-model enricher at all.
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
            ],
        ],
    ];

    /**
     * Looks up the model's ID in the static DATA table and applies any available metadata.
     *
     * Returns the model unchanged when its ID is not in DATA.
     */
    public function enrichModelInfo(AiModel $modelInfo, AiProviderProxy $provider, JobMetrics $jobMetrics): AiModel
    {
        $modelData = self::DATA[$modelInfo->model_id] ?? null;
        if ($modelData === null) {
            return $modelInfo;
        }

        $this->attachDescription(
            $modelInfo,
            $this->getService(LocaleService::class)->getLocale('en'),
            $modelData['description'] ?? null
        );

        $this->attachFlags($modelInfo, $modelData['flags'] ?? null);

        $this->enrichParameters($modelInfo, $modelData['parameters'] ?? null);

        $this->enrichChatLimits($modelInfo, maxInputTokens: $modelData['max_tokens'] ?? null);

        $this->enrichNativeCapabilities($modelInfo, $modelData['native_capabilities'] ?? null);

        if (!empty($modelData['documentation_url']) && $modelInfo->documentation_url === GwdgAdapter::DEFAULT_DOCUMENTATION_URL) {
            $modelInfo->documentation_url = $modelData['documentation_url'];
        }

        return $modelInfo;
    }
}
