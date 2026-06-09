<?php

namespace App\Services\OpenApi;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\ExampleBuilder;
use App\Services\OpenApi\Builders\PathBuilder;
use App\Services\OpenApi\Builders\SchemaBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

class OpenApiGenerator
{
    /**
     * @param  Server  $server  The JSON:API v1 server, injected via DI.
     * @param  SchemaBuilder  $schemaBuilder  Maps JSON:API field definitions to OpenAPI types and parses validation rules.
     * @param  PathBuilder  $pathBuilder  Scans Laravel routes and builds OpenAPI path operations.
     * @param  ExampleBuilder  $exampleBuilder  Generates example values for request/response bodies and filter parameters.
     */
    public function __construct(
        private readonly Server $server,
        private readonly SchemaBuilder $schemaBuilder,
        private readonly PathBuilder $pathBuilder,
        private readonly ExampleBuilder $exampleBuilder,
    ) {}

    /**
     * Build the complete OpenAPI 3.0.3 specification array.
     *
     * Pipeline:
     *  1. Instantiate the JSON:API Server and discover all registered schema classes via reflection.
     *  2. For each schema: build attributes, relationships, resource objects, request/response schemas.
     *  3. Scan API routes and generate path operations.
     *  4. Build action request schemas from controller reflection.
     *  5. Assemble shared components (parameters, responses, SSE events, IncludedResource).
     *
     * @param  bool  $withExamples  Whether to include database-derived filter examples and media-type-level request/response examples.
     * @return array The complete OpenAPI specification as a PHP array ready for YAML/JSON serialization.
     */
    public function generate(bool $withExamples = true): array
    {
        $schemaClasses = $this->getSchemaClasses($this->server);
        $schemaMap = [];
        $componentSchemas = $this->buildSharedSchemas();
        $actionRequestSchemas = [];

        foreach ($schemaClasses as $schemaClass) {
            $schema = new $schemaClass($this->server);
            $type = $schemaClass::type();
            $className = $this->schemaBuilder->typeToClassName($type);

            $attrs = $this->schemaBuilder->buildAttributes($schema);
            $rels = $this->schemaBuilder->buildRelationships($schema);

            $componentSchemas["{$className}Attributes"] = $attrs;
            if (! empty($rels)) {
                $componentSchemas["{$className}Relationships"] = $rels;
            }

            $relRef = ! empty($rels) ? ['$ref' => "#/components/schemas/{$className}Relationships"] : [];
            $componentSchemas["{$className}Resource"] = $this->schemaBuilder->buildResourceObject(
                $type,
                ['$ref' => "#/components/schemas/{$className}Attributes"],
                $relRef,
            );

            $componentSchemas["{$className}SingleResponse"] = $this->buildSingleResponse($className);
            $componentSchemas["{$className}CollectionResponse"] = $this->buildCollectionResponse($className);

            $writableAttrs = $this->schemaBuilder->buildAttributes($schema, writableOnly: true);
            if ($this->hasProperties($writableAttrs)) {
                $componentSchemas["{$className}CreateRequest"] = $this->schemaBuilder->buildCreateRequestSchema($type, $schema);
                $componentSchemas["{$className}UpdateRequest"] = $this->schemaBuilder->buildUpdateRequestSchema($type, $schema);
            }

            $schemaMap[$type] = [
                'schema' => $schema,
                'class' => $schemaClass,
                'className' => $className,
                'model' => $schema::$model,
            ];
        }

        $paths = $this->pathBuilder->build($schemaMap, $withExamples);

        $actionRequestSchemas = $this->buildActionRequestSchemas();
        $componentSchemas = array_merge($componentSchemas, $actionRequestSchemas);

        $resourceNames = [];
        foreach ($schemaMap as $meta) {
            $resourceNames[] = $meta['className'].'Resource';
        }
        $componentSchemas['IncludedResource'] = [
            'oneOf' => array_map(fn ($name) => ['$ref' => "#/components/schemas/{$name}"], $resourceNames),
        ];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'HAWKI API',
                'version' => '1.0.0',
                'description' => 'HAWKI AI Platform API documentation. All endpoints use JSON:API v1.0 specification.',
            ],
            'servers' => [
                ['url' => '/api', 'description' => 'Current server (relative)'],
            ],
            'security' => [
                ['BearerAuth' => []],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'parameters' => $this->buildSharedParameters(),
                'schemas' => $componentSchemas,
                'responses' => $this->buildSharedResponses(),
            ],
        ];
    }

    /**
     * Serialize the spec array to a pretty-printed JSON string.
     *
     * @param  array  $spec  The OpenAPI specification array.
     * @return string JSON-formatted OpenAPI specification with pretty-printing and unescaped slashes.
     */
    public function toJson(array $spec): string
    {
        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Retrieve all schema classes registered in the JSON:API Server.
     *
     * Uses reflection to access the protected {@see Server::allSchemas()} method,
     * which returns the full list of schema classes for the v1 API.
     *
     * @param  Server  $server  The instantiated JSON:API v1 server.
     * @return array<string> List of schema class FQCNs.
     */
    private function getSchemaClasses(Server $server): array
    {
        $method = new \ReflectionMethod($server, 'allSchemas');

        return $method->invoke($server);
    }

    /**
     * Build shared reusable component schemas that are not resource-specific.
     *
     * Includes: ResourceIdentifier, RelationshipLinks, JsonApiLinks, JsonApiMeta,
     * ErrorObject, and SSE event schemas for the chat-test streaming endpoint
     * (OpenAI Responses API compatible).
     *
     * @return array<string, array> Map of schema name to OpenAPI schema definition.
     */
    private function buildSharedSchemas(): array
    {
        return [
            'ResourceIdentifier' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                ],
                'required' => ['type', 'id'],
            ],
            'RelationshipLinks' => [
                'type' => 'object',
                'properties' => [
                    'self' => ['type' => 'string', 'description' => 'Link to the relationship itself'],
                    'related' => ['type' => 'string', 'description' => 'Link to the related resource(s)'],
                ],
            ],
            'JsonApiLinks' => [
                'type' => 'object',
                'properties' => [
                    'self' => ['type' => 'string'],
                    'first' => ['type' => 'string'],
                    'last' => ['type' => 'string'],
                    'prev' => ['type' => 'string'],
                    'next' => ['type' => 'string'],
                ],
            ],
            'JsonApiMeta' => [
                'type' => 'object',
                'properties' => [
                    'page' => [
                        'type' => 'object',
                        'properties' => [
                            'currentPage' => ['type' => 'integer'],
                            'from' => ['type' => 'integer'],
                            'lastPage' => ['type' => 'integer'],
                            'perPage' => ['type' => 'integer'],
                            'to' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'ErrorObject' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'description' => 'HTTP status code'],
                    'title' => ['type' => 'string', 'description' => 'Error title'],
                    'detail' => ['type' => 'string', 'description' => 'Detailed error message'],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'pointer' => ['type' => 'string', 'description' => 'JSON pointer to the source of the error'],
                        ],
                    ],
                ],
            ],
            'ResponseUsage' => [
                'description' => 'Token usage information for the response.',
                'type' => 'object',
                'properties' => [
                    'input_tokens' => ['type' => 'integer', 'description' => 'Number of input tokens'],
                    'output_tokens' => ['type' => 'integer', 'description' => 'Number of output tokens'],
                    'total_tokens' => ['type' => 'integer', 'description' => 'Total tokens used'],
                ],
                'required' => ['input_tokens', 'output_tokens', 'total_tokens'],
            ],
            'OutputTextContent' => [
                'description' => 'A text content part within an output message.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['output_text']],
                    'text' => ['type' => 'string'],
                    'annotations' => ['type' => 'array', 'items' => ['type' => 'object']],
                ],
                'required' => ['type', 'text', 'annotations'],
            ],
            'OutputMessage' => [
                'description' => 'An assistant message output item.',
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Message item ID (msg_*)'],
                    'type' => ['type' => 'string', 'enum' => ['message']],
                    'role' => ['type' => 'string', 'enum' => ['assistant']],
                    'content' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/OutputTextContent'],
                    ],
                    'status' => ['type' => 'string', 'enum' => ['in_progress', 'completed']],
                ],
                'required' => ['id', 'type', 'role', 'content', 'status'],
            ],
            'FunctionCallItem' => [
                'description' => 'A function call output item when the model invokes a tool.',
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Function call item ID (fc_*)'],
                    'type' => ['type' => 'string', 'enum' => ['function_call']],
                    'call_id' => ['type' => 'string', 'description' => 'Unique call identifier'],
                    'name' => ['type' => 'string', 'description' => 'Function name'],
                    'arguments' => ['type' => 'string', 'description' => 'JSON-encoded arguments'],
                    'result' => ['description' => 'Tool execution result (present when completed)', 'oneOf' => [['type' => 'string'], ['type' => 'object']]],
                    'status' => ['type' => 'string', 'enum' => ['in_progress', 'completed']],
                ],
                'required' => ['id', 'type', 'call_id', 'name', 'arguments', 'status'],
            ],
            'ResponseObject' => [
                'description' => 'The top-level response object (OpenAI Responses API compatible).',
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Response ID (resp_*)'],
                    'object' => ['type' => 'string', 'enum' => ['response']],
                    'status' => ['type' => 'string', 'enum' => ['in_progress', 'completed', 'failed']],
                    'model' => ['type' => 'string', 'description' => 'AI model identifier'],
                    'output' => [
                        'type' => 'array',
                        'items' => [
                            'oneOf' => [
                                ['$ref' => '#/components/schemas/OutputMessage'],
                                ['$ref' => '#/components/schemas/FunctionCallItem'],
                            ],
                        ],
                    ],
                    'usage' => ['$ref' => '#/components/schemas/ResponseUsage'],
                    'created_at' => ['type' => 'integer', 'description' => 'Unix timestamp'],
                ],
                'required' => ['id', 'object', 'status', 'model', 'output', 'usage', 'created_at'],
            ],
            'SseResponseCreatedEvent' => [
                'description' => 'Emitted when the response is created. Always the first event.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.created']],
                    'response' => ['$ref' => '#/components/schemas/ResponseObject'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'response', 'sequence_number'],
            ],
            'SseResponseInProgressEvent' => [
                'description' => 'Emitted when the response starts processing.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.in_progress']],
                    'response' => ['$ref' => '#/components/schemas/ResponseObject'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'response', 'sequence_number'],
            ],
            'SseOutputItemAddedEvent' => [
                'description' => 'Emitted when a new output item (message or function_call) is added.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.output_item.added']],
                    'output_index' => ['type' => 'integer'],
                    'item' => [
                        'oneOf' => [
                            ['$ref' => '#/components/schemas/OutputMessage'],
                            ['$ref' => '#/components/schemas/FunctionCallItem'],
                        ],
                    ],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'output_index', 'item', 'sequence_number'],
            ],
            'SseContentPartAddedEvent' => [
                'description' => 'Emitted when a new content part is added to a message output item.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.content_part.added']],
                    'item_id' => ['type' => 'string'],
                    'output_index' => ['type' => 'integer'],
                    'content_index' => ['type' => 'integer'],
                    'part' => ['$ref' => '#/components/schemas/OutputTextContent'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'output_index', 'content_index', 'part', 'sequence_number'],
            ],
            'SseOutputTextDeltaEvent' => [
                'description' => 'A fragment of the assistant text response. 0 or more per stream.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.output_text.delta']],
                    'item_id' => ['type' => 'string'],
                    'output_index' => ['type' => 'integer'],
                    'content_index' => ['type' => 'integer'],
                    'delta' => ['type' => 'string', 'description' => 'Text fragment'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'output_index', 'content_index', 'delta', 'sequence_number'],
            ],
            'SseOutputTextDoneEvent' => [
                'description' => 'Emitted when the full text content is finalized.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.output_text.done']],
                    'item_id' => ['type' => 'string'],
                    'output_index' => ['type' => 'integer'],
                    'content_index' => ['type' => 'integer'],
                    'text' => ['type' => 'string', 'description' => 'Complete accumulated text'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'output_index', 'content_index', 'text', 'sequence_number'],
            ],
            'SseContentPartDoneEvent' => [
                'description' => 'Emitted when a content part is completed.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.content_part.done']],
                    'item_id' => ['type' => 'string'],
                    'output_index' => ['type' => 'integer'],
                    'content_index' => ['type' => 'integer'],
                    'part' => ['$ref' => '#/components/schemas/OutputTextContent'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'output_index', 'content_index', 'part', 'sequence_number'],
            ],
            'SseOutputItemDoneEvent' => [
                'description' => 'Emitted when an output item is completed.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.output_item.done']],
                    'output_index' => ['type' => 'integer'],
                    'item' => [
                        'oneOf' => [
                            ['$ref' => '#/components/schemas/OutputMessage'],
                            ['$ref' => '#/components/schemas/FunctionCallItem'],
                        ],
                    ],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'output_index', 'item', 'sequence_number'],
            ],
            'SseFunctionCallArgumentsDeltaEvent' => [
                'description' => 'A fragment of function call arguments (JSON string).',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.function_call_arguments.delta']],
                    'item_id' => ['type' => 'string'],
                    'output_index' => ['type' => 'integer'],
                    'delta' => ['type' => 'string', 'description' => 'Partial JSON arguments'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'output_index', 'delta', 'sequence_number'],
            ],
            'SseFunctionCallArgumentsDoneEvent' => [
                'description' => 'Emitted when function call arguments are complete.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.function_call_arguments.done']],
                    'item_id' => ['type' => 'string'],
                    'name' => ['type' => 'string', 'description' => 'Function name'],
                    'output_index' => ['type' => 'integer'],
                    'arguments' => ['type' => 'string', 'description' => 'Complete JSON arguments'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'item_id', 'name', 'output_index', 'arguments', 'sequence_number'],
            ],
            'SseResponseCompletedEvent' => [
                'description' => 'Final event on success. Contains the full response with all output items.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['response.completed']],
                    'response' => ['$ref' => '#/components/schemas/ResponseObject'],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'response', 'sequence_number'],
            ],
            'SseErrorEvent' => [
                'description' => 'Error event. Replaces response.completed on failure.',
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['error']],
                    'code' => ['type' => 'string', 'nullable' => true],
                    'message' => ['type' => 'string', 'description' => 'Error message'],
                    'param' => ['type' => 'string', 'nullable' => true],
                    'sequence_number' => ['type' => 'integer'],
                ],
                'required' => ['type', 'code', 'message', 'param', 'sequence_number'],
            ],
        ];
    }

    /**
     * Build shared error response components for common HTTP status codes.
     *
     * Generates response definitions for 401 (Unauthorized), 403 (Forbidden),
     * 404 (Not Found), and 422 (Unprocessable Entity) with example error objects.
     *
     * @return array<string, array> Map of response name to OpenAPI response definition.
     */
    private function buildSharedResponses(): array
    {
        $errorSchema = function (string $status, string $title, string $detail, bool $withSource = false): array {
            $example = [
                'status' => $status,
                'title' => $title,
                'detail' => $detail,
            ];
            if ($withSource) {
                $example['source'] = ['pointer' => '/data/attributes/name'];
            }

            return [
                'type' => 'object',
                'properties' => [
                    'errors' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ErrorObject'],
                        'example' => [$example],
                    ],
                ],
            ];
        };

        return [
            'Unauthorized' => [
                'description' => 'Unauthorized - Authentication required',
                'content' => [
                    'application/vnd.api+json' => ['schema' => $errorSchema('401', 'Unauthorized', 'Unauthenticated.')],
                ],
            ],
            'Forbidden' => [
                'description' => 'Forbidden - Insufficient permissions',
                'content' => [
                    'application/vnd.api+json' => ['schema' => $errorSchema('403', 'Forbidden', 'This action is unauthorized.')],
                ],
            ],
            'NotFound' => [
                'description' => 'Not found',
                'content' => [
                    'application/vnd.api+json' => ['schema' => $errorSchema('404', 'Not Found', 'The requested resource does not exist.')],
                ],
            ],
            'UnprocessableEntity' => [
                'description' => 'Validation error',
                'content' => [
                    'application/vnd.api+json' => ['schema' => $errorSchema('422', 'Validation Error', 'The given data was invalid.', true)],
                ],
            ],
        ];
    }

    /**
     * Build shared query parameter components for pagination, sorting, and inclusion.
     *
     * Extracts PageSize, PageNumber, Sort, and Include into reusable parameter refs
     * that are referenced via {@see $ref} throughout the spec.
     *
     * @return array<string, array> Map of parameter name to OpenAPI parameter definition.
     */
    private function buildSharedParameters(): array
    {
        return [
            'PageSize' => [
                'name' => 'page[size]',
                'in' => 'query',
                'schema' => ['type' => 'integer'],
                'description' => 'Number of items per page',
            ],
            'PageNumber' => [
                'name' => 'page[number]',
                'in' => 'query',
                'schema' => ['type' => 'integer'],
                'description' => 'Page number',
            ],
            'Sort' => [
                'name' => 'sort',
                'in' => 'query',
                'description' => 'Sort by field. Prefix with - for descending.',
                'schema' => ['type' => 'string'],
            ],
            'Include' => [
                'name' => 'include',
                'in' => 'query',
                'description' => 'Comma-separated list of relationships to include.',
                'schema' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Build a single-resource response wrapper with data, included, links, and meta.
     *
     * @param  string  $className  The PascalCase class name (e.g., "Assistant").
     * @return array OpenAPI schema for a single-resource JSON:API response.
     */
    private function buildSingleResponse(string $className): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => ['$ref' => "#/components/schemas/{$className}Resource"],
                'included' => [
                    'type' => 'array',
                    'description' => 'Included related resources (when using ?include=)',
                    'items' => ['$ref' => '#/components/schemas/IncludedResource'],
                ],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
        ];
    }

    /**
     * Build a collection response wrapper with data array, included, links, and meta.
     *
     * @param  string  $className  The PascalCase class name (e.g., "Assistant").
     * @return array OpenAPI schema for a collection JSON:API response.
     */
    private function buildCollectionResponse(string $className): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => ['$ref' => "#/components/schemas/{$className}Resource"],
                ],
                'included' => [
                    'type' => 'array',
                    'description' => 'Included related resources (when using ?include=)',
                    'items' => ['$ref' => '#/components/schemas/IncludedResource'],
                ],
                'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                'meta' => ['$ref' => '#/components/schemas/JsonApiMeta'],
            ],
        ];
    }

    /**
     * Check whether a schema object has any defined properties.
     *
     * @param  array  $schema  An OpenAPI schema array.
     * @return bool True if the schema has at least one property.
     */
    private function hasProperties(array $schema): bool
    {
        return ! empty((array) ($schema['properties'] ?? []));
    }

    /**
     * Discover and build OpenAPI schemas for custom action request bodies.
     *
     * Scans routes containing `/actions/` and uses controller method reflection
     * to find typed FormRequest parameters. Each discovered request class is
     * parsed into an OpenAPI schema via {@see SchemaBuilder::buildActionRequestSchema()}.
     *
     * @return array<string, array> Map of schema name to OpenAPI schema definition.
     */
    private function buildActionRequestSchemas(): array
    {
        $schemas = [];
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->filter(fn ($route) => str_contains($route->uri(), '/actions/'));

        foreach ($routes as $route) {
            $uses = $route->getAction('uses');
            if (! is_string($uses) || ! str_contains($uses, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $uses);

            try {
                $reflection = new \ReflectionMethod($controller, $method);
            } catch (\Throwable) {
                continue;
            }

            $requestClass = null;
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class) && str_ends_with($type->getName(), 'Request')) {
                    $requestClass = $type->getName();
                    break;
                }
            }

            if ($requestClass === null) {
                continue;
            }

            $uri = $route->uri();
            preg_match('#api/([^/]+)/\{[^}]+\}/actions/([^/]+)#', $uri, $m);
            if (empty($m)) {
                continue;
            }

            $resource = $m[1];
            $action = $m[2];
            $schemaName = $this->schemaBuilder->typeToClassName($resource)
                .$this->schemaBuilder->typeToClassName($action)
                .'Request';

            if (! isset($schemas[$schemaName])) {
                $schemas[$schemaName] = $this->schemaBuilder->buildActionRequestSchema($requestClass);
            }
        }

        return $schemas;
    }
}
