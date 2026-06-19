<?php

namespace App\Providers;

use App\Models\Ai\McpServer;
use App\Services\Ai\Agent\Chat\ChatAgent;
use App\Services\Ai\Agent\Chat\ChatRequestFactory;
use App\Services\Ai\Agent\Chat\Values\ChatRequest;
use App\Services\Ai\Agent\Contracts\AgentInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestFactoryInterface;
use App\Services\Ai\Config\AiConfig;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ConfigFileSync\Syncers\McpServerSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemPromptSyncer;
use App\Services\Ai\Contracts\ToolInterface;
use App\Services\Ai\Exceptions\InvalidProviderAdapterException;
use App\Services\Ai\ProviderAdapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\ProviderAdapters\Implementations\AnthropicAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\AwsBedrockAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\AzureOpenAiAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\CohereAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\DeepseekAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\GeminiAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\GwdgAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\HuggingfaceAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\MistralAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OllamaAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OpenAiAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OpenAiLikeAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OpenAiLikeResponsesAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OpenAiResponsesAdapter;
use App\Services\Ai\ProviderAdapters\Implementations\OpenRouterAdapter;
use App\Services\Ai\ProviderAdapters\WellKnownAdapterKeys;
use App\Services\Ai\Registries\AgentRegistry;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\Ai\Registries\AiModelSettingRegistry;
use App\Services\Ai\Registries\ProviderAdapterRegistry;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\WellKnownAgents;
use App\Services\Ai\Values\WellKnownCapabilities;
use App\Services\Ai\Values\WellKnownModelSettings;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public const string PROVIDER_ADAPTER_LIST = 'ai.providerAdapter.list';
    public const string MCP_CLIENT_LIST = 'ai.mcpClient.list';
    public const string AGENT_LIST = 'ai.agent.list';
    public const string AGENT_REQUEST_FACTORY_LIST = 'ai.agentRequestFactory.list';

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
        );

        $this->app->extend(
            AiModelCapabilityRegistry::class,
            fn(AiModelCapabilityRegistry $registry) => $registry
                ->declare(
                    key: WellKnownCapabilities::WEB_SEARCH,
                    defaultValue: ModelCapabilityValueType::NO,
                    titleTranslationLabel: 'Tool_web_search',
                    iconPath: resource_path('icons/tool_web_search.svg')
                )
                ->declare(
                    key: WellKnownCapabilities::KNOWLEDGE_BASE,
                    defaultValue: ModelCapabilityValueType::NO,
                    titleTranslationLabel: 'Tool_knowledge_base',
                    iconPath: resource_path('icons/tool_knowledge_base.svg')
                )
        );

        $this->app->extend(
            ProviderAdapterRegistry::class,
            fn(ProviderAdapterRegistry $registry) => $registry
                ->declare(WellKnownAdapterKeys::ANTHROPIC, AnthropicAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI, OpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_RESPONSES, OpenAiResponsesAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_AZURE, AzureOpenAiAdapter::class)
                ->declare(WellKnownAdapterKeys::OLLAMA, OllamaAdapter::class)
                ->declare(WellKnownAdapterKeys::GEMINI, GeminiAdapter::class)
                ->declare(WellKnownAdapterKeys::MISTRAL, MistralAdapter::class)
                ->declare(WellKnownAdapterKeys::HUGGINGFACE, HuggingfaceAdapter::class)
                ->declare(WellKnownAdapterKeys::DEEPSEEK, DeepseekAdapter::class)
                ->declare(WellKnownAdapterKeys::AWS_BEDROCK, AwsBedrockAdapter::class)
                ->declare(WellKnownAdapterKeys::COHERE, CohereAdapter::class)
                ->declare(WellKnownAdapterKeys::GWDG, GwdgAdapter::class)
                ->declare(WellKnownAdapterKeys::OPEN_ROUTER, OpenRouterAdapter::class)
                ->declare(WellKnownAdapterKeys::OPEN_WEB_UI, OpenAiLikeAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_LIKE, OpenAiLikeAdapter::class)
                ->declare(WellKnownAdapterKeys::OPENAI_LIKE_RESPONSES, OpenAiLikeResponsesAdapter::class)
        );

        $this->app->extend(
            AgentRegistry::class,
            fn(AgentRegistry $registry) => $registry
                ->declare(
                    agentKey: WellKnownAgents::CHAT,
                    agentClass: ChatAgent::class,
                    agentRequestClass: ChatRequest::class,
                    agentRequestFactoryClass: ChatRequestFactory::class
                )
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
            self::AGENT_LIST,
            /**
             * @return LazySingletonList<string, AgentInterface>
             */
            fn() => new LazySingletonList(
                fn(string $agentClass) => $agentClass,
                fn(string $agentClass) => $this->app->get($agentClass)
            )
        );

        $this->app->singleton(
            self::AGENT_REQUEST_FACTORY_LIST,
            /**
             * @return LazySingletonList<string, AgentRequestFactoryInterface>
             */
            fn() => new LazySingletonList(
                fn(string $factoryClass) => $factoryClass,
                fn(string $factoryClass) => $this->app->get($factoryClass)
            )
        );
    }

    public function boot(): void
    {
    }
}
