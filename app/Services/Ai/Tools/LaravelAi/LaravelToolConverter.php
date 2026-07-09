<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Tools\Contracts\SettingsAwareToolInterface;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\Exceptions\InvalidToolConfigurationException;
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
 * Rather than calling the MCP connector's tool-listing endpoint on every agent message — which
 * would hit the MCP server even when no tool is invoked — this class builds tool objects from
 * the database-cached metadata. Actual MCP calls are deferred to invocation time via
 * {@see HawkiMcpClient}, giving HAWKI full control over request handling, logging, and plugin
 * hooks ({@see BeforeCallingMcpToolFilterEvent} / {@see McpToolCalledFilterEvent}).
 *
 * Two tool types are supported:
 *  - {@see ToolType::FUNCTION}: resolves the PHP class from the service container.
 *  - {@see ToolType::MCP}: constructs a {@see LaravelMcpTool} backed by a {@see HawkiMcpClient}.
 *
 * The MCP client instances are managed as lazy singletons per {@see McpServer} via the
 * `AiServiceProvider::MCP_CLIENT_LIST` binding, so each server gets exactly one persistent
 * connection across all tool invocations in a request.
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
        throw InvalidToolConfigurationException::forUnsupportedToolType($tool->type->value, $tool->name);
    }

    /**
     * Resolves a PHP class-based tool from the service container.
     * The class must exist and implement {@see ToolInterface}.
     */
    private function convertFunctionTool(AiTool $tool): ToolInterface
    {
        $toolClass = $tool->class_name;
        if (!class_exists($toolClass)) {
            throw InvalidToolConfigurationException::forClassNotFound($toolClass, $tool->name);
        }

        if (!is_subclass_of($toolClass, ToolInterface::class)) {
            throw InvalidToolConfigurationException::forClassNotImplementingInterface($toolClass, $tool->name);
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
            throw InvalidToolConfigurationException::forMcpToolNotLinkedToServer($tool->name);
        }

        if (empty($tool->mcp_config)) {
            throw InvalidToolConfigurationException::forMcpToolMissingConfig($tool->name);
        }

        $mcpClient = $this->mcpClientList->get($mcpServer);

        return new LaravelMcpTool(
            logger: $this->logger,
            tool: $tool,
            client: $mcpClient
        );
    }


    /**
     * Passes caller-supplied settings to the tool when it implements {@see SettingsAwareToolInterface}.
     * Tools that do not implement the interface receive no settings and are returned unchanged.
     */
    private function provideSettingsForTool(ToolInterface $tool, array $settings): ToolInterface
    {
        if ($tool instanceof SettingsAwareToolInterface) {
            $tool->setSettings($settings);
        }
        return $tool;
    }
}
