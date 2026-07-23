<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;

/**
 * Enricher that sets a provider documentation URL when no URL has been set yet.
 *
 * The static MAPPING table maps LiteLLM provider names to their official documentation
 * URLs. Commented-out entries indicate known providers for which no documentation URL
 * is available. This enricher is registered last in the default pipeline so that
 * more specific sources (LiteLLM API, GWDG static data) have first opportunity to set
 * a model-specific URL.
 */
class StaticDocumentationUrlEnricher implements ModelInfoEnricherInterface
{
    private const array MAPPING = [
        'bedrock' => 'https://docs.aws.amazon.com/bedrock',
        'openai' => 'https://platform.openai.com/docs',
        // 'iml' => '',
        'bedrock_converse' => 'https://docs.aws.amazon.com/bedrock',
        // 'nyscale' => '',
        // 'assemblyai' => '',
        'azure' => 'https://learn.microsoft.com/en-us/azure/ai-services/openai/',
        'azure_ai' => 'https://learn.microsoft.com/en-us/azure/ai-services/openai/',
        'azure_text' => 'https://learn.microsoft.com/en-us/azure/ai-services/openai/',
        'text-completion-openai' => 'https://platform.openai.com/docs',
        'black_forest_labs' => 'https://platform.blackforest.ai/docs',
        'cerebras' => 'https://inference-docs.cerebras.ai',
        // 'nlp_cloud' => '',
        'anthropic' => 'https://docs.anthropic.com',
        'cloudflare' => 'https://developers.cloudflare.com/workers-ai',
        'codestral' => 'https://docs.mistral.ai',
        'cohere' => 'https://docs.cohere.com',
        'cohere_chat' => 'https://docs.cohere.com',
        'deepseek' => 'https://api-docs.deepseek.com',
        'dashscope' => 'https://dashscope.console.aliyun.com',
        'databricks' => 'https://docs.databricks.com',
        'dataforseo' => 'https://dataforseo.com/docs',
        'deepgram' => 'https://developers.deepgram.com',
        'deepinfra' => 'https://docs.deepinfra.com',
        // 'volcengine' => '',
        'exa_ai' => 'https://docs.exa.ai',
        'firecrawl' => 'https://docs.firecrawl.dev',
        'perplexity' => 'https://docs.perplexity.ai',
        // 'searxng' => '',
        'serper' => 'https://serper.dev',
        // 'apiserpent' => '',
        'elevenlabs' => 'https://elevenlabs.io/docs',
        'fal_ai' => 'https://fal.ai/docs',
        'featherless_ai' => 'https://featherless.ai/docs',
        'fireworks_ai' => 'https://docs.fireworks.ai',
        'fireworks_ai-embedding-models' => 'https://docs.fireworks.ai',
        'friendliai' => 'https://friendli.ai/docs',
        'vertex_ai-language-models' => 'https://cloud.google.com/vertex-ai',
        'gemini' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-embedding-models' => 'https://cloud.google.com/vertex-ai',
        // 'github_copilot' => '',
        'chatgpt' => 'https://platform.openai.com/docs',
        'gigachat' => 'https://developers.sber.ru/docs',
        // 'gmi' => '',
        'baseten' => 'https://docs.baseten.co',
        // 'google_pse' => '',
        // 'gradient_ai' => '',
        // 'lemonade' => '',
        'amazon_nova' => 'https://aws.amazon.com/bedrock/nova',
        'groq' => 'https://console.groq.com/docs',
        // 'heroku' => '',
        // 'hyperbolic' => '',
        // 'ai21' => '',
        'jina_ai' => 'https://docs.jina.ai',
        'crusoe' => 'https://docs.crusoecloud.ai',
        // 'inception' => '',
        // 'text-completion-inception' => '',
        'lambda_ai' => 'https://lambdalabs.com/docs',
        // 'meta_llama' => '',
        'minimax' => 'https://www.minimax.io/en/docs',
        'mistral' => 'https://docs.mistral.ai',
        'moonshot' => 'https://platform.moonshot.cn/docs',
        // 'morph' => '',
        // 'nscale' => '',
        'nebius' => 'https://studio.nebius.ai/docs',
        // 'oci' => '',
        'ollama' => 'https://ollama.com/docs',
        'openrouter' => 'https://openrouter.ai/models',
        // 'ovhcloud' => '',
        // 'palm' => '',
        // 'parallel_ai' => '',
        // 'publicai' => '',
        // 'reducto' => '',
        'recraft' => 'https://recraft.ai/docs',
        'replicate' => 'https://replicate.com/docs',
        'nvidia_nim' => 'https://docs.api.nvidia.com/nim',
        // 'sagemaker' => '',
        'sambanova' => 'https://docs.sambanova.ai',
        // 'snowflake' => '',
        'stability' => 'https://docs.stability.ai',
        'linkup' => 'https://docs.linkup.ai',
        'tavily' => 'https://docs.tavily.ai',
        'text-completion-codestral' => 'https://docs.mistral.ai',
        'vertex_ai-text-models' => 'https://cloud.google.com/vertex-ai',
        'together_ai' => 'https://docs.together.ai',
        // 'aws_polly' => '',
        // 'v0' => '',
        'vercel_ai_gateway' => 'https://sdk.vercel.ai',
        'vertex_ai-anthropic_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-mistral_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-deepseek_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-image-models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-ai21_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-llama_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-minimax_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-moonshot_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-zai_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-openai_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-qwen_models' => 'https://cloud.google.com/vertex-ai',
        'vertex_ai-video-models' => 'https://cloud.google.com/vertex-ai',
        'voyage' => 'https://docs.voyageai.com',
        // 'wandb' => '',
        'watsonx' => 'https://cloud.ibm.com/docs/watsonx',
        'xai' => 'https://docs.x.ai',
        'zai' => 'https://docs.z.ai/guides/llm',
        'runwayml' => 'https://docs.dev.runwayml.com',
        'novita' => 'https://novita.ai/docs',
        'llamagate' => 'https://llamagate.com/docs',
        // 'libertai' => '',
        'sarvam' => 'https://docs.sarvam.ai',
        'duckduckgo' => 'https://api.duckduckgo.com/ai',
        'bedrock_mantle' => 'https://docs.aws.amazon.com/bedrock',
        'soniox' => 'https://soniox.com/docs',
        'tensormesh' => 'https://tensormesh.ai/docs',
        // 'darkbloom' => ''
    ];

    /**
     * Sets `documentation_url` on the model when it is empty and the provider name is in the mapping.
     */
    public function enrichModelInfo(AiModel $modelInfo, AiProviderProxy $provider, JobMetrics $jobMetrics): AiModel
    {
        if (empty($modelInfo->documentation_url) && isset(self::MAPPING[$provider->name])) {
            $modelInfo->documentation_url = self::MAPPING[$provider->name];
        }

        return $modelInfo;
    }
}
