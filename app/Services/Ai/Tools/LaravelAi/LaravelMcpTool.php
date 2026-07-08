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
