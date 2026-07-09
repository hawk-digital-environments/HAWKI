<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\AbstractTool;
use App\Services\Ai\Tools\LaravelAi\Events\BeforeCallingMcpToolFilterEvent;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Laravel\Ai\Schema\SchemaNormalizer;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * A Neuron-compatible tool wrapper around a single MCP tool database record.
 *
 * Instances are constructed by {@see LaravelToolConverter::convertMcpTool()} and are never
 * registered in the container — each one is scoped to a specific {@see AiTool} row.
 *
 * The tool schema is derived from the `mcp_config.inputSchema` stored in the database
 * (populated by {@see McpToolSyncer}) rather than fetched live from the MCP server, keeping
 * the Neuron tool-building path free of network round-trips.
 *
 * When the AI model invokes the tool via `__invoke()`:
 *  1. Returns an error immediately if the backing MCP server is marked OFFLINE.
 *  2. Fires {@see BeforeCallingMcpToolFilterEvent}, allowing listeners to short-circuit the
 *     call and inject a synthetic result.
 *  3. Calls the MCP server via {@see HawkiMcpClient::callTool()} if no short-circuit occurred.
 *  4. Fires {@see McpToolCalledFilterEvent}, giving listeners a chance to post-process the result.
 */
class LaravelMcpTool extends AbstractTool
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AiTool          $tool,
        private readonly HawkiMcpClient  $client
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->tool->name;
    }

    /**
     * @inheritDoc
     */
    public function description(): Stringable|string
    {
        return $this->tool->description;
    }

    /**
     * Derives the tool's JSON Schema from the `mcp_config.inputSchema` stored in the database.
     *
     * The raw MCP input schema is normalised via {@see SchemaNormalizer} and converted to a
     * Laravel JsonSchema object. The object's `properties` map is then extracted via a bound
     * closure — the only way to read the protected field without reflection — and returned
     * as the array format expected by Neuron. Returns an empty array when the schema is
     * absent, not an object type, or cannot be parsed.
     *
     * @inheritDoc
     * @noinspection PhpUndefinedFieldInspection
     */
    public function schema(JsonSchema $schema): array
    {
        $input = $this->tool->mcp_config['inputSchema'] ?? [];

        if (!is_array($input) || ($input['type'] ?? 'object') !== 'object') {
            return [];
        }

        try {
            $type = JsonSchemaFactory::fromArray(SchemaNormalizer::normalize($input));
        } catch (Throwable) {
            return [];
        }

        // This is black magic to get the properties of the object type (which have protected visibility) without using reflection.
        // It works because the closure is bound to the object type instance, allowing access to its protected properties.
        return $type instanceof ObjectType
            ? (fn(): array => $this->properties)->call($type)
            : [];
    }

    public function __invoke(): string
    {
        $this->logger->info(sprintf('Calling MCP tool %s', $this->tool->name));

        $arguments = $this->getArguments();

        if ($this->tool->server->status === OnlineStatus::OFFLINE) {
            $this->logger->warning(sprintf('MCP tool %s is offline, returning error response', $this->tool->name));
            return $this->errorResponse('This tool is currently offline. Do not retry to use it in this session.');
        }

        $result = BeforeCallingMcpToolFilterEvent::dispatch(null, $arguments, $this->tool, $this->client)->getResult();

        if ($result === null) {
            try {
                $response = $this->client->callTool($this->tool->mcp_name, $arguments);
            } catch (\Throwable $e) {
                return $this->errorResponse($e);
            }

            $result = json_encode($response);
        }

        return McpToolCalledFilterEvent::dispatch($result, $arguments, $this->tool, $this->client)->getResult();
    }

}
