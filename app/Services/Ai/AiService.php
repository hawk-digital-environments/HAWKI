<?php
declare(strict_types=1);


namespace App\Services\Ai;


use App\Models\Ai\McpServer;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Agent\AgentRequestFactory;
use App\Services\Ai\Agent\Contracts\AgentRequestFactoryInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestInterface;
use App\Services\Ai\Agent\Contracts\AgentResponseInterface;
use App\Services\Ai\Registries\AgentRegistry;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\Ai\Repositories\McpServerRepository;
use App\Services\Ai\Repositories\SystemModelRepository;
use App\Services\Ai\Repositories\SystemPromptRepository;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
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
        private AgentRequestFactory    $agentRequestFactory,
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

    public function getAgentRequestFactory(): AgentRequestFactoryInterface
    {
        return $this->agentRequestFactory;
    }

    public function sendRequestToAgent(AgentRequestInterface $request): AgentResponseInterface
    {
        return $this->agentRegistry->getAgentForRequest($request)->sendRequest($request);
    }
}
