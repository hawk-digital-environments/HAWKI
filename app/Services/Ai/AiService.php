<?php
declare(strict_types=1);


namespace App\Services\Ai;


use App\Models\Ai\McpServer;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Agents\AgentRegistry;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\SystemModelRepository;
use App\Services\Ai\SystemPrompts\SystemPromptRepository;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Repositories\McpServerRepository;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central access point for the AI domain.
 *
 * Aggregates the primary repositories and registries needed to work with AI
 * models, providers, system prompts, MCP servers and agents. Controllers and
 * other callers should depend on this service rather than injecting each
 * repository individually.
 *
 * Bound as a singleton by {@see \App\Providers\AiServiceProvider}.
 *
 * Example:
 * ```php
 * // Resolve an agent for the current HTTP request and stream its response
 * $agent  = $this->aiService->getAgent($request->validated());
 * $stream = $agent->sendStreaming();
 *
 * // Look up a model by its string identifier
 * $model = $this->aiService->getModels()->findOneOrFail('gpt-4o');
 *
 * // Obtain a cached MCP client for a server record
 * $client = $this->aiService->getMcpClient($mcpServer);
 * ```
 *
 * @api
 */
#[Singleton]
readonly class AiService
{
    public function __construct(
        /**
         * @var LazySingletonList<McpServer, HawkiMcpClient>
         */
        #[Give(AiServiceProvider::MCP_CLIENT_LIST)]
        private LazySingletonList      $mcpClientList,
        private AgentRegistry          $agentRegistry,
        private AiModelRepository      $aiModelRepository,
        private SystemModelRepository  $systemModelRepository,
        private SystemPromptRepository $systemPromptRepository,
        private McpServerRepository    $mcpServerRepository,
    )
    {
    }

    /** Returns the repository for all registered AI models and providers. */
    public function getModels(): AiModelRepository
    {
        return $this->aiModelRepository;
    }

    /** Returns the repository for system prompts (default, summary, title-generation, etc.). */
    public function getSystemPrompts(): SystemPromptRepository
    {
        return $this->systemPromptRepository;
    }

    /** Returns the repository for system-model assignments (default model, title-generator, etc.). */
    public function getSystemModels(): SystemModelRepository
    {
        return $this->systemModelRepository;
    }

    /** Returns the repository for MCP server records. */
    public function getMcpServers(): McpServerRepository
    {
        return $this->mcpServerRepository;
    }

    /**
     * Returns the singleton MCP client for the given server record.
     *
     * Clients are instantiated lazily on first access and cached for the lifetime
     * of the request so repeated calls for the same server are cheap.
     */
    public function getMcpClient(McpServer $server): HawkiMcpClient
    {
        return $this->mcpClientList->get($server);
    }

    /**
     * Resolves and returns an agent capable of handling the given request.
     *
     * Iterates the registered {@see AgentRegistry} factories in priority order and
     * returns the first agent that accepts the request.
     *
     * @throws \App\Services\Ai\Agents\Exceptions\AgentNotResolvedException when no factory produces an agent.
     */
    public function getAgent(mixed $request): AgentInterface
    {
        return $this->agentRegistry->getAgent($request);
    }

    /**
     * Like {@see getAgent()}, but returns null instead of throwing when no agent matches.
     */
    public function tryToGetAgent(mixed $request): AgentInterface|null
    {
        return $this->agentRegistry->tryToGetAgent($request);
    }
}
