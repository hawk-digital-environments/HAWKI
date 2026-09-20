<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Tools;

use App\Services\Ai\Chat\Exceptions\ClientToolExecutionException;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Psr\Log\LoggerInterface;

/**
 * A tool the client declared in its request that has no server-side implementation in
 * the HAWKI runtime (e.g. a coding agent's local file tools).
 *
 * The definition (name, description, JSON Schema) is forwarded to the model unchanged,
 * but execution is never attempted server-side: {@see shouldRequestApproval()} always
 * requires approval, which makes the SDK's generation loop pause and surface the
 * model's call as a pending tool call. The proxy then streams the untouched
 * `function_call` to the client, which executes it locally and sends the
 * `function_call_output` back with the next request.
 */
class ClientTool implements Approvable, Tool
{
    public function __construct(
        private readonly ToolDefinition $definition,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return $this->definition->name;
    }

    public function description(): string|\Stringable
    {
        return $this->definition->description;
    }

    /**
     * Builds the tool's parameter schema from the client-supplied JSON Schema.
     *
     * Properties outside the Laravel-supported JSON Schema subset degrade to a
     * described string type (with a log entry) rather than failing the request.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $required = $this->definition->requiredParameters ?? [];
        $properties = $this->definition->parameters['properties'] ?? [];

        if (!\is_array($properties)) {
            return [];
        }

        $types = [];

        foreach ($properties as $propertyName => $propertySchema) {
            if (!\is_string($propertyName) || !\is_array($propertySchema)) {
                continue;
            }

            $type = $this->propertyType($schema, $propertyName, $propertySchema);

            if (\in_array($propertyName, $required, true)) {
                $type->required();
            }

            $types[$propertyName] = $type;
        }

        return $types;
    }

    /**
     * Always requires approval — this is the handoff: the loop pauses instead of
     * executing, and the call is passed through to the client.
     */
    public function shouldRequestApproval(Request $request): ?Approval
    {
        return Approval::required('This tool is executed by the requesting client, not by the HAWKI runtime.');
    }

    public function requireApproval(?string $reason = null): static
    {
        return $this;
    }

    public function withoutApproval(): static
    {
        return $this;
    }

    /**
     * Defensive: unreachable in the normal flow, because the approval gate pauses the
     * generation loop before any tool invocation happens.
     */
    public function handle(Request $request): string|\Stringable
    {
        throw ClientToolExecutionException::forTool($this->definition->name);
    }

    /**
     * @param array<string, mixed> $propertySchema
     */
    private function propertyType(JsonSchema $schema, string $propertyName, array $propertySchema): Type
    {
        try {
            return \Illuminate\JsonSchema\JsonSchema::fromArray($propertySchema);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf(
                'The client tool "%s" declares a parameter "%s" outside the supported JSON Schema subset; degrading it to a string type.',
                $this->definition->name,
                $propertyName,
            ), ['exception' => $e]);

            return $schema->string()->description('Opaque parameter — the exact schema is not representable here; see the tool documentation.');
        }
    }
}
