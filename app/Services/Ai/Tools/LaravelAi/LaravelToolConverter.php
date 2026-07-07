<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Tools\Contracts\SettingsAwareToolInterface;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\LaravelAi\Events\BeforeCallingMcpToolFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\System\Container\ServiceLocator;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;
use Psr\Log\LoggerInterface;

/**
 * Converts {@see AiTool} database records into Neuron-compatible {@see ToolInterface} instances.
 *
 * Rather than calling {@see McpConnector::tools()} on every message — which would query the MCP
 * server even when no tool is invoked — this class uses {@see LaravelMcpTool} as a custom MCP tool implementation.
 * Actual MCP calls are deferred to {@see HawkiMcpClient}, giving full control over request handling, logging, and plugin hooks
 * via {@see BeforeCallingMcpToolFilterEvent} and {@see McpToolCalledFilterEvent}.
 */
#[Singleton]
readonly class LaravelToolConverter
{
    public function __construct(
        private LoggerInterface   $logger,
        /**
         * @var LazySingletonList<McpServer, HawkiMcpClient>
         */
        #[Give(AiServiceProvider::MCP_CLIENT_LIST)]
        private LazySingletonList $mcpClientList,
        private ServiceLocator    $serviceLocator
    )
    {
    }

    /**
     * Converts an {@see AiTool} record to a Neuron-compatible tool instance.
     *
     * - {@see ToolType::FUNCTION} — resolves the registered PHP class from the container.
     * - {@see ToolType::MCP}      — builds a lazy Neuron tool whose callable delegates to
     *                                  {@see HawkiMcpClient} at invocation time.
     *
     */
    public function convert(AiTool $tool, array $settings = []): ToolInterface
    {
        if ($tool->type === ToolType::MCP) {
            return $this->provideSettingsForTool($this->convertMcpTool($tool), $settings);
        }

        if ($tool->type === ToolType::FUNCTION) {
            return $this->provideSettingsForTool($this->convertFunctionTool($tool), $settings);
        }

        // @phpstan-ignore deadCode.unreachable
        // @todo exception
        throw new \InvalidArgumentException(sprintf('Unsupported tool type %s for tool %s', $tool->type->value, $tool->name));
    }

    /**
     * Resolves a PHP class-based tool from the service container.
     * The class must exist and implement {@see ToolInterface}.
     */
    private function convertFunctionTool(AiTool $tool): ToolInterface
    {
        $toolClass = $tool->class_name;
        if (!class_exists($toolClass)) {
            // @todo exception
            throw new \RuntimeException(sprintf(
                'Tool class %s does not exist for tool %s',
                $toolClass,
                $tool->name));
        }

        if (!is_subclass_of($toolClass, ToolInterface::class)) {
            // @todo exception
            throw new \RuntimeException(sprintf(
                'Tool class %s must implement %s for tool %s',
                $toolClass,
                ToolInterface::class,
                $tool->name));
        }

        return $this->serviceLocator->get($toolClass);
    }

    /**
     * Builds a Neuron tool backed by {@see HawkiMcpClient} for a single MCP tool entry.
     *
     * The tool definition is hydrated from the database-cached `mcp_config` via
     * {@see NeuronMcpTransportDummy}, avoiding a live MCP server round-trip at build time.
     * The actual MCP call is deferred to when the AI agent invokes the tool — at that point
     * the callable fires {@see BeforeCallingMcpToolFilterEvent} (allowing short-circuit), then
     * calls the server, and finally fires {@see McpToolCalledFilterEvent} (allowing post-processing).
     *
     */
    private function convertMcpTool(AiTool $tool): ToolInterface
    {
        $mcpServer = $tool->server;
        if (!$mcpServer) {
            // @todo exception
            throw new \RuntimeException('MCP tool is not linked to an MCP server');
        }

        if (empty($tool->mcp_config)) {
            // @todo exception
            throw new \RuntimeException('MCP tool does not have a config! Did you execute the tool sync?');
        }

        $mcpClient = $this->mcpClientList->get($mcpServer);

        return new LaravelMcpTool(
            logger: $this->logger,
            tool: $tool,
            client: $mcpClient
        );
    }


    private function provideSettingsForTool(ToolInterface $tool, array $settings): ToolInterface
    {
        if ($tool instanceof SettingsAwareToolInterface) {
            $tool->setSettings($settings);
        }
        return $tool;
    }
}
