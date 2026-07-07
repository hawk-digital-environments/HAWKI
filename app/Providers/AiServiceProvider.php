<?php

namespace App\Providers;

use App\Models\Ai\McpServer;
use App\Services\Ai\Agents\AgentRegistry;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Implementations\AbstractAgentFactory;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentForAssistantFactory;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Config\AiConfig;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ConfigFileSync\Syncers\McpServerSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemPromptSyncer;
use App\Services\Ai\Exceptions\InvalidProviderAdapterException;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\Ai\ModelInformation\Enrichment\AiModelInfoEnrichmentPipeline;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmDriverNameProviderNameMapping;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlmApiEnricher;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\StaticDocumentationUrlEnricher;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\StaticGwdgEnricher;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\AiModelFlagRegistry;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Limits\AiModelLimitRegistry;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Pricing\AiModelPricingRegistry;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\Implementations\AnthropicAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\AzureOpenAiAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\DeepseekAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\GeminiAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\GwdgAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\MistralAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\OllamaAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\OpenAiAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\OpenAiLikeAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\OpenRouterAdapter;
use App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry;
use App\Services\Ai\Providers\Adapters\WellKnownAdapterKeys;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\AbstractTool;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\System\Container\ServiceLocator;
use App\Services\System\UsageTypes\UsageContext;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Laravel\Ai\Enums\Lab;

class AiServiceProvider extends ServiceProvider
{
    public const string PROVIDER_ADAPTER_LIST = 'ai.providerAdapter.list';
    public const string MCP_CLIENT_LIST = 'ai.mcpClient.list';
    public const string AGENT_FACTORY_LIST = 'ai.agentFactory.list';

    public function register(): void
    {
        $this->app->tag([
            ModelAndProviderSyncer::class,
            SystemModelSyncer::class,
            SystemPromptSyncer::class,
            McpServerSyncer::class,
        ], ConfigSyncerInterface::class);

        $this->app->tag(
            $this->app->get('config')->get('tools.available_tools'),
            ToolInterface::class
        );

        $this->app->extend(
            PublicConfigRegistry::class,
            function (PublicConfigRegistry $registry) {
                return $registry->declare(AiConfig::class);
            }
        );

        $this->app->extend(
            AiModelSettingRegistry::class,
            fn(AiModelSettingRegistry $registry) => $registry
                ->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS, 5)
                ->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING, 3)
                ->declare(WellKnownModelSettings::FILE_UPLOAD, false)
                ->declare(WellKnownModelSettings::TOOL_CALLING, false)
                ->declare(WellKnownModelSettings::NATIVE_CAPABILITIES, true)
        );

        $this->app->extend(
            AiModelLimitRegistry::class,
            fn(AiModelLimitRegistry $registry) => $registry
                ->declare(WellKnownModelTypes::CHAT, ChatAiModelLimits::class)
        );

        $this->app->extend(
            AiModelPricingRegistry::class,
            fn(AiModelPricingRegistry $registry) => $registry
                ->declare(WellKnownModelTypes::CHAT, ChatAiModelPricing::class)
        );

        $this->app->extend(
            AiModelCapabilityRegistry::class,
            fn(AiModelCapabilityRegistry $registry) => $registry
                ->declare(
                    key: WellKnownCapabilities::WEB_SEARCH,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.webSearch',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.webSearchDescription',
                    iconPath: resource_path('icons/tools/web-search.svg')
                )
                ->declare(
                    key: WellKnownCapabilities::WEB_FETCH,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.webFetch',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.webFetchDescription',
                    iconPath: resource_path('icons/tools/web-fetch.svg')
                )
                ->declare(
                    key: WellKnownCapabilities::KNOWLEDGE_BASE,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBase',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBaseDescription',
                    iconPath: resource_path('icons/tools/knowledge-base.svg')
                )
        );

        $this->app->extend(
            AiModelFlagRegistry::class,
            fn(AiModelFlagRegistry $registry) => $registry
                ->declare(
                    key: WellKnownModelFlags::OPEN_WEIGHTS,
                    titleTranslationLabel: 'ai.model.detail.flag.openWeights',
                    descriptionTranslationLabel: 'ai.model.detail.flag.openWeightsTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::ECO_FRIENDLY,
                    titleTranslationLabel: 'ai.model.detail.flag.ecoFriendly',
                    descriptionTranslationLabel: 'ai.model.detail.flag.ecoFriendlyTooltip',
                    colorCode: AiModelFlagRegistry::COLOR_SUCCESS
                )
                ->declare(
                    key: WellKnownModelFlags::SELF_HOSTED,
                    titleTranslationLabel: 'ai.model.detail.flag.selfHosted',
                    descriptionTranslationLabel: 'ai.model.detail.flag.selfHostedTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::MULTI_MODAL,
                    titleTranslationLabel: 'ai.model.detail.flag.multiModal',
                    descriptionTranslationLabel: 'ai.model.detail.flag.multiModalTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::STRENGTH_CREATIVE_WRITING,
                    titleTranslationLabel: 'ai.model.detail.flag.strengthCreativeWriting',
                    descriptionTranslationLabel: 'ai.model.detail.flag.strengthCreativeWritingTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::STRENGTH_CODE_GENERATION,
                    titleTranslationLabel: 'ai.model.detail.flag.strengthCodeGeneration',
                    descriptionTranslationLabel: 'ai.model.detail.flag.strengthCodeGenerationTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::STRENGTH_MATH,
                    titleTranslationLabel: 'ai.model.detail.flag.strengthMath',
                    descriptionTranslationLabel: 'ai.model.detail.flag.strengthMathTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::STRENGTH_ROLE_PLAYING,
                    titleTranslationLabel: 'ai.model.detail.flag.strengthRolePlaying',
                    descriptionTranslationLabel: 'ai.model.detail.flag.strengthRolePlayingTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::FEATURE_REASONING,
                    titleTranslationLabel: 'ai.model.detail.flag.strengthReasoning',
                    descriptionTranslationLabel: 'ai.model.detail.flag.strengthReasoningTooltip',
                )
        );

        $this->app->extend(
            ProviderAdapterRegistry::class,
            fn(ProviderAdapterRegistry $registry) => $registry
                ->declare(WellKnownAdapterKeys::ANTHROPIC, AnthropicAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI, OpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_AZURE, AzureOpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OLLAMA, OllamaAdapter::class)
                ->declare(WellKnownAdapterKeys::GEMINI, GeminiAdapter::class)
                ->declare(WellKnownAdapterKeys::MISTRAL, MistralAdapter::class)
                ->declare(WellKnownAdapterKeys::HUGGINGFACE, OpenAiLikeAdapter::class)
                ->declare(WellKnownAdapterKeys::DEEPSEEK, DeepseekAdapter::class)
//                ->declare(WellKnownAdapterKeys::AWS_BEDROCK, AwsBedrockAdapter::class)
                ->declare(WellKnownAdapterKeys::GWDG, GwdgAdapter::class)
                ->declare(WellKnownAdapterKeys::OPEN_ROUTER, OpenRouterAdapter::class)
        );

        $this->app->extend(
            LiteLlmDriverNameProviderNameMapping::class,
            fn(LiteLlmDriverNameProviderNameMapping $mapping) => $mapping
                ->declare(Lab::ElevenLabs->value, 'elevenlabs')
                ->declare(Lab::Jina->value, 'jina-ai')
                ->declare(Lab::VoyageAI->value, 'voyage')
        );

        $this->app->extend(
            AiModelInfoEnrichmentPipeline::class,
            fn(AiModelInfoEnrichmentPipeline $pipeline) => $pipeline
                ->register(
                    $this->app->get(LiteLlmApiEnricher::class)
                )
                ->register(
                    $this->app->get(StaticGwdgEnricher::class),
                    after: [LiteLlmApiEnricher::class]
                )
                ->register(
                    $this->app->get(StaticDocumentationUrlEnricher::class),
                    after: [LiteLlmApiEnricher::class, StaticGwdgEnricher::class]
                )
        );

        $this->app->extend(
            AgentRegistry::class,
            fn(AgentRegistry $registry) => $registry
                ->declare(ChatAgentFromLegacyRequestFactory::class)
        );

        $this->app->singleton(
            self::PROVIDER_ADAPTER_LIST,
            /**
             * @return LazySingletonList<array{0: string, 1:class-string<ProviderAdapterInterface>}, ProviderAdapterInterface>
             */
            fn() => new LazySingletonList(
                fn(array $args) => implode('_', $args),
                function (array $args) {
                    [$adapterKey, $providerClass] = $args;
                    $provider = $this->app->get($providerClass);
                    if (!$provider instanceof ProviderAdapterInterface) {
                        throw InvalidProviderAdapterException::forClassNotImplementingInterface(
                            $adapterKey,
                            $providerClass,
                            get_class($provider)
                        );
                    }
                    return $provider;
                }
            )
        );

        $this->app->singleton(
            self::MCP_CLIENT_LIST,
            /**
             * @return LazySingletonList<McpServer, HawkiMcpClient>
             */
            fn() => new LazySingletonList(
                fn(McpServer $server) => 'mcp_client_' . $server->id,
                fn(McpServer $server) => $this->app->get(McpClientFactory::class)->createForServer($server)
            )
        );

        $this->app->singleton(
            self::AGENT_FACTORY_LIST,
            /**
             * @return LazySingletonList<class-string<AgentFactoryInterface>, AgentFactoryInterface>
             */
            fn() => new LazySingletonList(
                fn(string $factoryClassName) => 'agent_factory_' . md5($factoryClassName),
                fn(string $factoryClassName) => $this->app->get($factoryClassName)
            )
        );

        $this->app->afterResolving(
            AbstractTool::class,
            function (AbstractTool $tool) {
                $tool->setServiceLocator($this->app->make(ServiceLocator::class));
            }
        );

        $this->app->afterResolving(
            AbstractAgentFactory::class,
            function (AbstractAgentFactory $factory) {
                $factory->setToolResolver($this->app->make(LaravelToolResolver::class));
                $factory->setProviderProxyResolver($this->app->make(AiProviderProxyResolver::class));
                $factory->setUsageContext($this->app->make(UsageContext::class));
            }
        );

        // Laravel AI service overrides and modifications
        $this->app->extend(
            AiManager::class,
            function (AiManager $manager) {
                return ExtendedAiManager::createDecoratedOf($manager);
            }
        );

    }

    public function boot(): void
    {
    }
}
