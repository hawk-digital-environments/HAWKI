<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Ai\McpServer;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Chat\Factories\AbstractChatAgentFactory;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
use App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface;
use App\Services\Ai\Chat\Factories\Implementations\ChatAgentFactory;
use App\Services\Ai\Config\AiConfig;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ConfigFileSync\Syncers\McpServerSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemPromptSyncer;
use App\Services\Ai\Embeddings\Factories\Contracts\VectorizerFactoryInterface;
use App\Services\Ai\Embeddings\Factories\Implementations\DefaultVectorizerFactory;
use App\Services\Ai\Embeddings\Factories\VectorizerRegistry;
use App\Services\Ai\Exceptions\InvalidProviderAdapterException;
use App\Services\Ai\Formatters\Contracts\FormatterInterface;
use App\Services\Ai\Formatters\Embeddings\Contracts\EmbeddingFormatterInterface;
use App\Services\Ai\Formatters\Embeddings\EmbeddingFormatterRegistry;
use App\Services\Ai\Formatters\Embeddings\Implementations\OpenAi\OpenAiEmbeddingsFormatter;
use App\Services\Ai\Formatters\FormatterRegistry;
use App\Services\Ai\Formatters\Models\Contracts\ModelsFormatterInterface;
use App\Services\Ai\Formatters\Models\ModelsFormatterRegistry;
use App\Services\Ai\Formatters\Models\Implementations\OpenAi\OpenAiModelsFormatter;
use App\Services\Ai\Formatters\Models\Implementations\OpenResponses\OpenResponsesModelsFormatter;
use App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions\OpenAiChatCompletionsFormatter;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesFormatter;
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
use App\Services\Ai\Providers\Adapters\Implementations\AwsBedrockAdapter;
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
    public const string CHAT_AGENT_FACTORY_LIST = 'ai.chatAgentFactory.list';
    public const string FORMATTER_LIST = 'ai.formatter.list';
    public const string EMBEDDING_VECTORIZER_FACTORY_LIST = 'ai.embeddingVectorizerFactory.list';
    public const string EMBEDDING_FORMATTER_LIST = 'ai.embeddingFormatter.list';
    public const string MODEL_FORMATTER_LIST = 'ai.modelFormatter.list';

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
            ToolInterface::class,
        );

        $this->app->extend(
            PublicConfigRegistry::class,
            static function (PublicConfigRegistry $registry) {
                return $registry->declare(AiConfig::class);
            },
        );

        $this->app->extend(
            AiModelSettingRegistry::class,
            static fn (AiModelSettingRegistry $registry) => $registry
                ->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS, 5)
                ->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING, 3)
                ->declare(WellKnownModelSettings::FILE_UPLOAD, false)
                ->declare(WellKnownModelSettings::TOOL_CALLING, false)
                ->declare(WellKnownModelSettings::NATIVE_CAPABILITIES, true),
        );

        $this->app->extend(
            AiModelLimitRegistry::class,
            static fn (AiModelLimitRegistry $registry) => $registry
                ->declare(WellKnownModelTypes::CHAT, ChatAiModelLimits::class),
        );

        $this->app->extend(
            AiModelPricingRegistry::class,
            static fn (AiModelPricingRegistry $registry) => $registry
                ->declare(WellKnownModelTypes::CHAT, ChatAiModelPricing::class),
        );

        $this->app->extend(
            AiModelCapabilityRegistry::class,
            static fn (AiModelCapabilityRegistry $registry) => $registry
                ->declare(
                    key: WellKnownCapabilities::WEB_SEARCH,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.webSearch',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.webSearchDescription',
                    iconPath: resource_path('icons/tools/web-search.svg'),
                )
                ->declare(
                    key: WellKnownCapabilities::WEB_FETCH,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.webFetch',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.webFetchDescription',
                    iconPath: resource_path('icons/tools/web-fetch.svg'),
                )
                ->declare(
                    key: WellKnownCapabilities::KNOWLEDGE_BASE,
                    titleTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBase',
                    descriptionTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBaseDescription',
                    iconPath: resource_path('icons/tools/knowledge-base.svg'),
                ),
        );

        $this->app->extend(
            AiModelFlagRegistry::class,
            static fn (AiModelFlagRegistry $registry) => $registry
                ->declare(
                    key: WellKnownModelFlags::OPEN_WEIGHTS,
                    titleTranslationLabel: 'ai.model.detail.flag.openWeights',
                    descriptionTranslationLabel: 'ai.model.detail.flag.openWeightsTooltip',
                )
                ->declare(
                    key: WellKnownModelFlags::ECO_FRIENDLY,
                    titleTranslationLabel: 'ai.model.detail.flag.ecoFriendly',
                    descriptionTranslationLabel: 'ai.model.detail.flag.ecoFriendlyTooltip',
                    colorCode: AiModelFlagRegistry::COLOR_SUCCESS,
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
                ),
        );

        $this->app->extend(
            ProviderAdapterRegistry::class,
            static fn (ProviderAdapterRegistry $registry) => $registry
                ->declare(WellKnownAdapterKeys::ANTHROPIC, AnthropicAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI, OpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_AZURE, AzureOpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OLLAMA, OllamaAdapter::class)
                ->declare(WellKnownAdapterKeys::GEMINI, GeminiAdapter::class)
                ->declare(WellKnownAdapterKeys::MISTRAL, MistralAdapter::class)
                ->declare(WellKnownAdapterKeys::HUGGINGFACE, OpenAiLikeAdapter::class)
                ->declare(WellKnownAdapterKeys::DEEPSEEK, DeepseekAdapter::class)
                ->declare(WellKnownAdapterKeys::AWS_BEDROCK, AwsBedrockAdapter::class)
                ->declare(WellKnownAdapterKeys::GWDG, GwdgAdapter::class)
                ->declare(WellKnownAdapterKeys::OPEN_ROUTER, OpenRouterAdapter::class),
        );

        $this->app->extend(
            LiteLlmDriverNameProviderNameMapping::class,
            static fn (LiteLlmDriverNameProviderNameMapping $mapping) => $mapping
                ->declare(Lab::ElevenLabs->value, 'elevenlabs')
                ->declare(Lab::Jina->value, 'jina-ai')
                ->declare(Lab::VoyageAI->value, 'voyage'),
        );

        $this->app->extend(
            AiModelInfoEnrichmentPipeline::class,
            fn (AiModelInfoEnrichmentPipeline $pipeline) => $pipeline
                ->register($this->app->get(LiteLlmApiEnricher::class))
                ->register(
                    $this->app->get(StaticGwdgEnricher::class),
                    after: [LiteLlmApiEnricher::class],
                )
                ->register(
                    $this->app->get(StaticDocumentationUrlEnricher::class),
                    after: [LiteLlmApiEnricher::class, StaticGwdgEnricher::class],
                ),
        );

        $this->app->extend(
            ChatAgentRegistry::class,
            static fn (ChatAgentRegistry $registry) => $registry
                ->declare(ChatAgentFactory::class),
        );

        $this->app->extend(
            FormatterRegistry::class,
            static fn (FormatterRegistry $registry) => $registry
                ->declare(OpenResponsesFormatter::KEY, OpenResponsesFormatter::class)
                ->declare(OpenAiChatCompletionsFormatter::KEY, OpenAiChatCompletionsFormatter::class)
                ->declare(LegacyFormatter::KEY, LegacyFormatter::class),
        );

        $this->app->extend(
            VectorizerRegistry::class,
            static fn (VectorizerRegistry $registry) => $registry
                ->declare(DefaultVectorizerFactory::class),
        );

        $this->app->extend(
            EmbeddingFormatterRegistry::class,
            static fn (EmbeddingFormatterRegistry $registry) => $registry
                ->declare(OpenAiEmbeddingsFormatter::KEY, OpenAiEmbeddingsFormatter::class),
        );

        $this->app->extend(
            ModelsFormatterRegistry::class,
            static fn (ModelsFormatterRegistry $registry) => $registry
                ->declare(OpenAiModelsFormatter::KEY, OpenAiModelsFormatter::class)
                ->declare(OpenResponsesModelsFormatter::KEY, OpenResponsesModelsFormatter::class),
        );

        $this->app->singleton(
            self::PROVIDER_ADAPTER_LIST,
            /**
             * @return LazySingletonList<array{0: string, 1:class-string<ProviderAdapterInterface>}, ProviderAdapterInterface>
             */
            fn () => new LazySingletonList(
                static fn (array $args) => implode('_', $args),
                function (array $args) {
                    [$adapterKey, $providerClass] = $args;
                    $provider = $this->app->get($providerClass);

                    if (!$provider instanceof ProviderAdapterInterface) {
                        throw InvalidProviderAdapterException::forClassNotImplementingInterface(
                            $adapterKey,
                            $providerClass,
                            $provider::class,
                        );
                    }

                    return $provider;
                },
            ),
        );

        $this->app->singleton(
            self::MCP_CLIENT_LIST,
            /**
             * @return LazySingletonList<McpServer, HawkiMcpClient>
             */
            fn () => new LazySingletonList(
                static fn (McpServer $server) => 'mcp_client_' . $server->id,
                fn (McpServer $server) => $this->app->get(McpClientFactory::class)->createForServer($server),
            ),
        );

        $this->app->singleton(
            self::CHAT_AGENT_FACTORY_LIST,
            /**
             * @return LazySingletonList<class-string<ChatAgentFactoryInterface>, ChatAgentFactoryInterface>
             */
            fn () => new LazySingletonList(
                static fn (string $factoryClassName) => 'chat_agent_factory_' . md5($factoryClassName),
                fn (string $factoryClassName) => $this->app->get($factoryClassName),
            ),
        );

        $this->app->singleton(
            self::FORMATTER_LIST,
            /**
             * @return LazySingletonList<class-string<FormatterInterface>, FormatterInterface>
             */
            fn () => new LazySingletonList(
                static fn (string $formatterClassName) => 'formatter_' . md5($formatterClassName),
                fn (string $formatterClassName) => $this->app->get($formatterClassName),
            ),
        );

        $this->app->singleton(
            self::EMBEDDING_VECTORIZER_FACTORY_LIST,
            /**
             * @return LazySingletonList<class-string<VectorizerFactoryInterface>, VectorizerFactoryInterface>
             */
            fn () => new LazySingletonList(
                static fn (string $factoryClassName) => 'embedding_vectorizer_factory_' . md5($factoryClassName),
                fn (string $factoryClassName) => $this->app->get($factoryClassName),
            ),
        );

        $this->app->singleton(
            self::EMBEDDING_FORMATTER_LIST,
            /**
             * @return LazySingletonList<class-string<EmbeddingFormatterInterface>, EmbeddingFormatterInterface>
             */
            fn () => new LazySingletonList(
                static fn (string $formatterClassName) => 'embedding_formatter_' . md5($formatterClassName),
                fn (string $formatterClassName) => $this->app->get($formatterClassName),
            ),
        );

        $this->app->singleton(
            self::MODEL_FORMATTER_LIST,
            /**
             * @return LazySingletonList<class-string<ModelsFormatterInterface>, ModelsFormatterInterface>
             */
            fn () => new LazySingletonList(
                static fn (string $formatterClassName) => 'model_formatter_' . md5($formatterClassName),
                fn (string $formatterClassName) => $this->app->get($formatterClassName),
            ),
        );

        $this->app->afterResolving(
            AbstractTool::class,
            function (AbstractTool $tool): void {
                $tool->setServiceLocator($this->app->make(ServiceLocator::class));
            },
        );

        $this->app->afterResolving(
            AbstractChatAgentFactory::class,
            function (AbstractChatAgentFactory $factory): void {
                $factory->setToolResolver($this->app->make(LaravelToolResolver::class));
                $factory->setProviderProxyResolver($this->app->make(AiProviderProxyResolver::class));
                $factory->setUsageContext($this->app->make(UsageContext::class));
            },
        );

        // Laravel AI service overrides and modifications
        $this->app->extend(
            AiManager::class,
            static function (AiManager $manager) {
                return ExtendedAiManager::createDecoratedOf($manager);
            },
        );
    }

    public function boot(): void
    {
    }
}
