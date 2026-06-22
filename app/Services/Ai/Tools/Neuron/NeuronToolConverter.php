<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Neuron;


use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Contracts\ToolInterface as HawkiToolInterface;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Neuron\Events\BeforeCallingMcpToolFilterEvent;
use App\Services\Ai\Tools\Neuron\Events\McpToolCalledFilterEvent;
use App\Services\Ai\Values\ToolType;
use App\Services\System\Container\ServiceLocatorTrait;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;
use NeuronAI\MCP\McpConnector;
use NeuronAI\MCP\McpException;
use NeuronAI\Tools\ToolInterface as NeuronToolInterface;
use Psr\Log\LoggerInterface;

/**
 * Converts {@see AiTool} database records into Neuron-compatible {@see ToolInterface} instances.
 *
 * Rather than calling {@see McpConnector::tools()} on every message — which would query the MCP
 * server even when no tool is invoked — this class uses {@see NeuronMcpTransportDummy} to build
 * the tool definition from the database-cached MCP config. Actual MCP calls are deferred to
 * {@see HawkiMcpClient}, giving full control over request handling, logging, and plugin hooks
 * via {@see BeforeCallingMcpToolFilterEvent} and {@see McpToolCalledFilterEvent}.
 *
 * @internal This class is not meant to be used outside of {@see NeuronToolProvider} which is the offical bridge between our AiTool system and
 * Neuron's ToolInterface. It relies on internal details of how we sync and store MCP tools, and how Neuron's MCP connector works,
 * so it may need to be updated if those systems change.
 */
#[Singleton]
class NeuronToolConverter
{
    use ServiceLocatorTrait;

    private McpConnector|null $connector = null;

    public function __construct(
        private readonly LoggerInterface         $logger,
        /**
         * @var LazySingletonList<McpServer, HawkiMcpClient>
         */
        #[Give(AiServiceProvider::MCP_CLIENT_LIST)]
        private readonly LazySingletonList       $mcpClientList,
        private readonly NeuronMcpTransportDummy $transportDummy
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
     * @throws McpException when the tool type is unsupported, the target class is missing
     *         or does not implement {@see HawkiToolInterface}, or the MCP config is incomplete.
     */
    public function convert(AiTool $tool): NeuronToolInterface
    {
        if ($tool->type === ToolType::MCP) {
            return $this->convertMcpTool($tool);
        }

        if ($tool->type === ToolType::FUNCTION) {
            return $this->convertFunctionTool($tool);
        }

        // @phpstan-ignore deadCode.unreachable
        throw new McpException(sprintf('Unsupported tool type %s for tool %s', $tool->type->value, $tool->name));
    }

    /**
     * Resolves a PHP class-based tool from the service container.
     * The class must exist and implement {@see HawkiToolInterface}.
     *
     * @throws McpException when the class is absent or does not implement the required interface.
     */
    private function convertFunctionTool(AiTool $tool): NeuronToolInterface
    {
        $toolClass = $tool->class_name;
        if (!class_exists($toolClass)) {
            throw new McpException(sprintf(
                'Tool class %s does not exist for tool %s',
                $toolClass,
                $tool->name));
        }

        if (!is_subclass_of($toolClass, HawkiToolInterface::class)) {
            throw new McpException(sprintf(
                'Tool class %s must implement %s for tool %s',
                $toolClass,
                HawkiToolInterface::class,
                $tool->name));
        }

        return $this->getService($toolClass);
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
     * @throws McpException when the tool has no linked server, an empty config, or Neuron
     *         fails to produce a tool from the config.
     */
    private function convertMcpTool(AiTool $tool): NeuronToolInterface
    {
        $mcpServer = $tool->server;
        if (!$mcpServer) {
            throw new McpException('MCP tool is not linked to an MCP server');
        }

        if (empty($tool->mcp_config)) {
            throw new McpException('MCP tool does not have a config! Did you execute the tool sync?');
        }

        $this->transportDummy->setTool($tool);

        $neuronTool = $this->getNeuronMcpConnector()->tools()[0] ?? null;

        if ($neuronTool === null) {
            throw new McpException('Failed to convert MCP tool to Neuron tool! No tools were created by the connector.');
        }

        $mcpClient = $this->mcpClientList->get($mcpServer);

        // Link our MCP client to the tool so it can make requests
        $neuronTool->setCallable(function (array $arguments) use ($tool, $mcpClient) {
            $this->logger->info(sprintf('Calling MCP tool %s', $tool->name));

            $result = BeforeCallingMcpToolFilterEvent::dispatch(null, $arguments, $tool, $mcpClient)->getResult();

            if ($result === null) {
                try {
                    $response = $mcpClient->callTool($tool->name, $arguments);
                } catch (\Throwable $e) {
                    throw new McpException('Error calling MCP tool: ' . $e->getMessage(), previous: $e);
                }

                $result = json_encode($response);
            }

            return McpToolCalledFilterEvent::dispatch($result, $arguments, $tool, $mcpClient)->getResult();
        });

        return $neuronTool;
    }

    private function getNeuronMcpConnector(): McpConnector
    {
        return $this->connector ??= McpConnector::make([
            'transport' => $this->transportDummy
        ]);
    }
}
