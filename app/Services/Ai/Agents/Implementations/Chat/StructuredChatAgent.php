<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Implementations\Chat;

use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Schema\SchemaNormalizer;

/**
 * The generic chat agent for `json_schema` requests: exposes the client's schema
 * through the SDK's {@see HasStructuredOutput} contract, which every driver family
 * maps into its native dialect (OpenAI Responses `text.format`, OpenAI-compatible
 * `response_format`, Anthropic `output_config`).
 *
 * Only instantiated for non-streaming requests — the formatters reject
 * `stream: true` + `json_schema` with a 400 because the SDK cannot stream structured
 * output.
 */
class StructuredChatAgent extends ChatAgent implements HasStructuredOutput
{
    public function __construct(
        AgentRequestContext $context,
        string $instructions,
        array $messages,
        iterable $tools,
        string|null $promptString = null,
        array|null $attachments = null,
        private readonly ?ResponseFormatConfig $responseFormat = null,
    ) {
        parent::__construct(
            context: $context,
            instructions: $instructions,
            messages: $messages,
            tools: $tools,
            promptString: $promptString,
            attachments: $attachments,
        );
    }

    /**
     * The structured output schema as the SDK expects it: the root object's property
     * map. Raw client subschemas go through the same normalize/deserialize pipeline
     * the SDK uses for MCP tool inputs.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $format = $this->responseFormat;

        if (null === $format || empty($format->jsonSchema['properties'])) {
            return [];
        }

        $properties = [];

        foreach ($format->jsonSchema['properties'] as $name => $property) {
            if (!\is_string($name) || !\is_array($property)) {
                continue;
            }

            $properties[$name] = JsonSchemaFactory::fromArray(SchemaNormalizer::normalize($property));
        }

        return $properties;
    }
}
