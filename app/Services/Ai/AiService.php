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

    public function getModels(): AiModelRepository
    {
        return $this->aiModelRepository;
    }

    public function getSystemPrompts(): SystemPromptRepository
    {
        return $this->systemPromptRepository;
    }

    public function getSystemModels(): SystemModelRepository
    {
        return $this->systemModelRepository;
    }

    public function getMcpServers(): McpServerRepository
    {
        return $this->mcpServerRepository;
    }

    public function getMcpClient(McpServer $server): HawkiMcpClient
    {
        return $this->mcpClientList->get($server);
    }

    public function getAgent(mixed $request): AgentInterface
    {
        return $this->agentRegistry->getAgent($request);
    }

    public function tryToGetAgent(mixed $request): AgentInterface|null
    {
        return $this->agentRegistry->tryToGetAgent($request);
    }
}
