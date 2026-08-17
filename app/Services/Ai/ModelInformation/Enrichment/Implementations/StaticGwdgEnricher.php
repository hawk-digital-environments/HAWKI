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
        'deepseek-v4-flash-0731' => [
            'max_tokens' => 1000000,
            'documentation_url' => 'https://huggingface.co/deepseek-ai/DeepSeek-V4-Flash-0731',
            'description' => 'DeepSeek V4 Flash is a strong Mixture-of-Experts (MoE) model with 284B parameters (13B activated) and a context window size of one million tokens. With a high reasoning effort, it achieves comparable reasoning performance to DeepSeek V4 Pro, but lacks the deep knowledge and most complex agentic capabilities of state-of-the-art models.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
                WellKnownModelFlags::FEATURE_REASONING,
                WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS,
                WellKnownModelFlags::FEATURE_STREAMING,
            ],
            'parameters' => [
                WellKnownModelParams::TEMPERATURE => 1.0,
                WellKnownModelParams::TOP_P => 1.0,
            ],
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
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
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
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
        'mistral-medium-3.5-128b' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/mistralai/Mistral-Medium-3.5-128B',
            'description' => 'Mistral Medium 3.5 128B is a dense model with 128B parameters and a 256k context window, combining instruction-following, reasoning, and coding capabilities in a single model. Therefore, this model replaces its predecessors in Le Chat and the coding agent Vibe, and produces better results for a variety of tasks including instruct, reasoning, and coding, compared to previous models.',
            'flags' => [
                WellKnownModelFlags::OPEN_WEIGHTS,
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
        'qwen3-coder-next' => [
            'max_tokens' => 256000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3-Coder-Next-FP8',
            'description' => 'Qwen3-Coder-Next-FP8 is an open-weight coding foundation model optimized for software engineering, code generation, and autonomous coding agents. It uses a Mixture-of-Experts (MoE) architecture with 80B total parameters and 3B active parameters, supports a native 256K-token context window, advanced tool use, and long-context reasoning over large codebases. The FP8 quantized version reduces memory usage and improves inference efficiency while maintaining strong coding performance.',
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
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
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
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
            ],
        ],
        'qwen3.6-27b' => [
            'max_tokens' => 262000,
            'documentation_url' => 'https://huggingface.co/Qwen/Qwen3.6-27B-FP8',
            'description' => 'Qwen3.6-27B-FP8 is an open-weight text foundation model with strong performance in coding, reasoning, and agentic workflows. It features a 27B-parameter dense architecture, a native 262K-token context window (extendable beyond 1M tokens), and advanced tool calling. The FP8 quantized version reduces memory usage and improves inference efficiency while maintaining performance close to the original model.',
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
            'native_capabilities' => [
                WellKnownCapabilities::TOOL_CALLING,
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
            $modelData['description']
        );

        $this->attachFlags($modelInfo, $modelData['flags']);

        $this->enrichParameters($modelInfo, $modelData['parameters'] ?? null);

        $this->enrichChatLimits($modelInfo, maxInputTokens: $modelData['max_tokens']);

        $this->enrichNativeCapabilities($modelInfo, $modelData['native_capabilities'] ?? null);

        if ($modelInfo->documentation_url === GwdgAdapter::DEFAULT_DOCUMENTATION_URL) {
            $modelInfo->documentation_url = $modelData['documentation_url'];
        }

        return $modelInfo;
    }
}
