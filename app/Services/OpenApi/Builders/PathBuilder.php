<?php

namespace App\Services\OpenApi\Builders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;

/**
 * Scans Laravel API routes and generates OpenAPI path operations.
 *
 * Each route URI is classified into one of 5 endpoint types:
 *  - collection:  GET/POST /{resource}
 *  - resource:    GET/PATCH/DELETE /{resource}/{id}
 *  - related:     GET /{resource}/{id}/{relation}
 *  - relationship: GET /{resource}/{id}/relationships/{relation}
 *  - action:      POST /{resource}/{id}/actions/{action}
 *
 * Operations include reusable parameter refs, media-type-level examples,
 * concrete related-resource schemas, and correct toOne/toMany cardinality.
 */
class PathBuilder
{
    /**
     * @param  SchemaBuilder  $schemaBuilder  Used for type-to-class-name conversion and inverse resolution.
     * @param  ExampleBuilder  $exampleBuilder  Used for generating filter, request, and response examples.
     */
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly ExampleBuilder $exampleBuilder,
    ) {}

    /**
     * Build all OpenAPI paths from registered API routes.
     *
     * @param  array  $schemaMap  Map of JSON:API type → schema metadata from OpenApiGenerator.
     * @param  bool  $withExamples  Whether to include media-type-level examples and filter examples from DB.
     * @return array OpenAPI paths array, sorted alphabetically by path.
     */
    public function build(array $schemaMap, bool $withExamples): array
    {
        $paths = [];
        $routes = $this->getApiRoutes();

        foreach ($routes as $route) {
            $info = $this->parseRouteUri($route->uri());
            if ($info === null) {
                continue;
            }

            $normalizedUri = $this->normalizeUri($route->uri());

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $operation = $this->buildOperation(
                    strtolower($method),
                    $info,
                    $schemaMap,
                    $withExamples,
                    $route,
                );
                if ($operation !== null) {
                    $paths[$normalizedUri][strtolower($method)] = $operation;
                }
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Collect all API routes, excluding internal endpoints (user, ai-req, docs).
     *
     * @return array<int, \Illuminate\Routing\Route> Filtered list of API routes.
     */
    private function getApiRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->filter(fn ($route) => ! str_starts_with($route->uri(), 'api/user'))
            ->filter(fn ($route) => ! str_starts_with($route->uri(), 'api/ai-req'))
            ->filter(fn ($route) => ! str_starts_with($route->uri(), 'api/docs'))
            ->values()
            ->all();
    }

    /**
     * Normalize a Laravel route URI to an OpenAPI path string.
     *
     * Strips the "api/" prefix, replaces named parameters with `{id}`,
     * and prepends a leading slash.
     *
     * @param  string  $uri  The Laravel route URI (e.g., "api/assistants/{assistant}").
     * @return string OpenAPI path (e.g., "/assistants/{id}").
     */
    private function normalizeUri(string $uri): string
    {
        $path = preg_replace('#^api/#', '', $uri);
        $path = preg_replace('#\{[^}]+\}#', '{id}', $path);

        return '/'.$path;
    }

    /**
     * Classify a route URI into an endpoint type and extract resource/relation/action parts.
     *
     * @param  string  $uri  The Laravel route URI (without "api/" prefix already stripped).
     * @return array|null Associative array with type, resource, and optionally relation/action, or null if unrecognised.
     */
    private function parseRouteUri(string $uri): ?array
    {
        $path = preg_replace('#^api/#', '', $uri);

        if (preg_match('#^([^/]+)/\{[^}]+\}/actions/([^/]+)$#', $path, $m)) {
            return ['type' => 'action', 'resource' => $m[1], 'action' => $m[2]];
        }
        if (preg_match('#^([^/]+)/\{[^}]+\}/relationships/([^/]+)$#', $path, $m)) {
            return ['type' => 'relationship', 'resource' => $m[1], 'relation' => $m[2]];
        }
        if (preg_match('#^([^/]+)/\{[^}]+\}/([^/]+)$#', $path, $m)) {
            return ['type' => 'related', 'resource' => $m[1], 'relation' => $m[2]];
        }
        if (preg_match('#^([^/]+)/\{[^}]+\}$#', $path, $m)) {
            return ['type' => 'resource', 'resource' => $m[1]];
        }
        if (preg_match('#^([^/]+)/schema$#', $path, $m)) {
            return ['type' => 'meta', 'resource' => $m[1]];
        }
        if (preg_match('#^([^/]+)$#', $path, $m)) {
            return ['type' => 'collection', 'resource' => $m[1]];
        }

        return null;
    }

    /**
     * Dispatch to the appropriate operation builder based on endpoint type.
     *
     * @param  string  $method  HTTP method (get, post, patch, delete).
     * @param  array  $info  Parsed route info from {@see parseRouteUri()}.
     * @param  array  $schemaMap  Resource metadata map.
     * @param  bool  $withExamples  Whether to include examples.
     * @param  mixed  $route  The Laravel route instance.
     * @return array|null OpenAPI operation array, or null if the method is unsupported for this endpoint type.
     */
    private function buildOperation(
        string $method,
        array $info,
        array $schemaMap,
        bool $withExamples,
        $route,
    ): ?array {
        $meta = $schemaMap[$info['resource']] ?? null;
        $className = $meta ? $meta['className'] : $this->schemaBuilder->typeToClassName($info['resource']);
        $tag = $className;

        return match ($info['type']) {
            'collection' => $this->buildCollectionOperation($method, $info['resource'], $className, $tag, $meta, $withExamples),
            'resource' => $this->buildResourceOperation($method, $info['resource'], $className, $tag, $meta),
            'related' => $this->buildRelatedOperation($method, $info['resource'], $className, $tag, $info['relation'], $meta),
            'relationship' => $this->buildRelationshipOperation($method, $info['resource'], $tag, $info['relation'], $meta),
            'action' => $this->buildActionOperation($method, $info['resource'], $tag, $info['action'], $route),
            'meta' => $this->buildMetaOperation($method, $info['resource'], $className, $tag),
            default => null,
        };
    }

    /**
     * Build a collection-level operation (GET list or POST create).
     *
     * GET operations include filter parameters (from schema filters), pagination, sorting,
     * and inclusion parameters. POST operations include a request body with the create schema.
     *
     * @param  string  $method  HTTP method (get or post).
     * @param  string  $resource  JSON:API resource type.
     * @param  string  $className  PascalCase class name for schema refs.
     * @param  string  $tag  Operation tag.
     * @param  array|null  $meta  Schema metadata from schemaMap, or null.
     * @param  bool  $withExamples  Whether to include examples.
     */
    private function buildCollectionOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        ?array $meta,
        bool $withExamples,
    ): ?array {
        if ($method === 'get') {
            $parameters = [];

            if ($meta) {
                $schema = $meta['schema'];
                foreach ($schema->filters() as $filter) {
                    $filterParam = [
                        'name' => "filter[{$filter->key()}]",
                        'in' => 'query',
                        'schema' => ['type' => 'string'],
                    ];
                    if ($withExamples && isset($meta['model'])) {
                        $examples = $this->exampleBuilder->getFilterExamples($schema, $filter);
                        if (! empty($examples)) {
                            $filterParam['example'] = $examples[0];
                        }
                    }
                    $parameters[] = $filterParam;
                }
            }

            $parameters[] = ['$ref' => '#/components/parameters/PageSize'];
            $parameters[] = ['$ref' => '#/components/parameters/PageNumber'];
            $parameters[] = ['$ref' => '#/components/parameters/Sort'];
            $parameters[] = ['$ref' => '#/components/parameters/Include'];

            return [
                'summary' => "List {$resource}",
                'operationId' => "list{$className}",
                'tags' => [$tag],
                'parameters' => $parameters,
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/vnd.api+json' => $this->withExample(
                                "#/components/schemas/{$className}CollectionResponse",
                                $meta ? $this->exampleBuilder->buildCollectionResponseExample($meta['schema'], $resource) : null,
                            ),
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ];
        }

        if ($method === 'post' && $meta) {
            return [
                'summary' => "Create {$resource}",
                'operationId' => "create{$className}",
                'tags' => [$tag],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/vnd.api+json' => $this->withExample(
                            "#/components/schemas/{$className}CreateRequest",
                            $this->exampleBuilder->buildRequestExample($meta['schema'], $resource),
                        ),
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Created',
                        'content' => [
                            'application/vnd.api+json' => $this->withExample(
                                "#/components/schemas/{$className}SingleResponse",
                                $this->exampleBuilder->buildResponseExample($meta['schema'], $resource),
                            ),
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
                ],
            ];
        }

        return null;
    }

    /**
     * Build a resource-level operation (GET, PATCH, or DELETE for a single resource by ID).
     *
     * @param  string  $method  HTTP method (get, patch, delete).
     * @param  string  $resource  JSON:API resource type.
     * @param  string  $className  PascalCase class name for schema refs.
     * @param  string  $tag  Operation tag.
     * @param  array|null  $meta  Schema metadata, or null.
     */
    private function buildResourceOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        ?array $meta,
    ): ?array {
        $pathParam = $this->buildIdParameter($resource);

        if ($method === 'get') {
            return [
                'summary' => "Get {$resource} by ID",
                'operationId' => "get{$className}",
                'tags' => [$tag],
                'parameters' => array_merge(
                    [$pathParam],
                    [['$ref' => '#/components/parameters/Include']],
                ),
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/vnd.api+json' => $this->withExample(
                                "#/components/schemas/{$className}SingleResponse",
                                $meta ? $this->exampleBuilder->buildResponseExample($meta['schema'], $resource) : null,
                            ),
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ];
        }

        if ($method === 'patch' && $meta) {
            return [
                'summary' => "Update {$resource}",
                'operationId' => "update{$className}",
                'tags' => [$tag],
                'parameters' => [$pathParam],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/vnd.api+json' => $this->withExample(
                            "#/components/schemas/{$className}UpdateRequest",
                            $this->exampleBuilder->buildRequestExample($meta['schema'], $resource, forUpdate: true),
                        ),
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Updated',
                        'content' => [
                            'application/vnd.api+json' => $this->withExample(
                                "#/components/schemas/{$className}SingleResponse",
                                $this->exampleBuilder->buildResponseExample($meta['schema'], $resource),
                            ),
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
                ],
            ];
        }

        if ($method === 'delete') {
            return [
                'summary' => "Delete {$resource}",
                'operationId' => "delete{$className}",
                'tags' => [$tag],
                'parameters' => [$pathParam],
                'responses' => [
                    '204' => ['description' => 'Deleted'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ];
        }

        return null;
    }

    /**
     * Build a related-resource operation (GET /{resource}/{id}/{relation}).
     *
     * Resolves the inverse type via {@see SchemaBuilder::typeToClassName()} to produce
     * a concrete resource schema ref instead of a generic ResourceIdentifier.
     * Detects toOne vs toMany cardinality from the field type.
     *
     * @param  string  $method  HTTP method (only GET supported).
     * @param  string  $resource  JSON:API resource type.
     * @param  string  $className  PascalCase class name.
     * @param  string  $tag  Operation tag.
     * @param  string  $relation  The relationship field name.
     * @param  array|null  $meta  Schema metadata, or null.
     */
    private function buildRelatedOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        string $relation,
        ?array $meta,
    ): ?array {
        if ($method !== 'get') {
            return null;
        }

        $isToMany = false;
        $inverseClassName = null;
        if ($meta) {
            $schema = $meta['schema'];
            foreach ($schema->fields() as $field) {
                if ($field->name() === $relation) {
                    $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);
                    try {
                        $inverseClassName = $this->schemaBuilder->typeToClassName($field->inverse());
                    } catch (\Throwable) {
                    }
                    break;
                }
            }
        }

        $resourceRef = $inverseClassName
            ? ['$ref' => "#/components/schemas/{$inverseClassName}Resource"]
            : ['$ref' => '#/components/schemas/ResourceIdentifier'];

        $responseSchema = $isToMany
            ? ['type' => 'array', 'items' => $resourceRef]
            : $resourceRef;

        $relationLabel = str_replace('-', ' ', $relation);

        return [
            'summary' => "Get {$relationLabel} for {$resource}",
            'operationId' => "get{$className}".ucfirst($this->camelize($relation)),
            'tags' => [$tag],
            'parameters' => [
                $this->buildIdParameter($resource),
            ],
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => $responseSchema,
                                    'links' => ['$ref' => '#/components/schemas/JsonApiLinks'],
                                ],
                            ],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    /**
     * Build a relationship links operation (GET /{resource}/{id}/relationships/{relation}).
     *
     * Returns ResourceIdentifier data with RelationshipLinks. Uses generic ResourceIdentifier
     * (not concrete schemas) since relationship endpoints expose linkage, not full resources.
     *
     * @param  string  $method  HTTP method (only GET supported).
     * @param  string  $resource  JSON:API resource type.
     * @param  string  $tag  Operation tag.
     * @param  string  $relation  The relationship field name.
     * @param  array|null  $meta  Schema metadata, or null.
     */
    private function buildRelationshipOperation(
        string $method,
        string $resource,
        string $tag,
        string $relation,
        ?array $meta,
    ): ?array {
        if ($method !== 'get') {
            return null;
        }

        $isToMany = false;
        if ($meta) {
            $schema = $meta['schema'];
            foreach ($schema->fields() as $field) {
                if ($field->name() === $relation) {
                    $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);
                    break;
                }
            }
        }

        $dataSchema = $isToMany
            ? ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ResourceIdentifier']]
            : ['$ref' => '#/components/schemas/ResourceIdentifier'];

        $relationLabel = str_replace('-', ' ', $relation);

        return [
            'summary' => "Get {$relationLabel} relationship links for {$resource}",
            'operationId' => "get{$this->schemaBuilder->typeToClassName($resource)}Relationship".ucfirst($this->camelize($relation)),
            'tags' => [$tag],
            'parameters' => [
                $this->buildIdParameter($resource),
            ],
            'responses' => [
                '200' => [
                    'description' => 'Relationship links',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => $dataSchema,
                                    'links' => ['$ref' => '#/components/schemas/RelationshipLinks'],
                                ],
                            ],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    /**
     * Build a custom action operation (POST /{resource}/{id}/actions/{action}).
     *
     * Discovers the request FormRequest class via controller method reflection.
     * Special-cases the "chat-test" action for SSE streaming responses.
     *
     * @param  string  $method  HTTP method (only POST supported).
     * @param  string  $resource  JSON:API resource type.
     * @param  string  $tag  Operation tag.
     * @param  string  $action  The action name (e.g., "chat-test", "favorite").
     * @param  mixed  $route  The Laravel route instance.
     */
    private function buildActionOperation(
        string $method,
        string $resource,
        string $tag,
        string $action,
        $route,
    ): ?array {
        if ($method !== 'post') {
            return null;
        }

        $operation = [
            'summary' => ucfirst(str_replace('-', ' ', $action))." {$resource}",
            'operationId' => "{$this->camelize($action)}{$this->schemaBuilder->typeToClassName($resource)}",
            'tags' => [$tag],
            'parameters' => [
                $this->buildIdParameter($resource),
            ],
        ];

        $requestClass = $this->resolveActionRequestClass($route, $action);
        $isChatTest = $action === 'chat-test';
        $schemaName = "{$this->schemaBuilder->typeToClassName($resource)}{$this->schemaBuilder->typeToClassName($action)}Request";

        if ($requestClass !== null) {
            $requestExample = $this->exampleBuilder->getActionRequestExample($resource, $action);
            $mediaType = $isChatTest ? 'application/json' : 'application/vnd.api+json';
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    $mediaType => $this->withExample(
                        "#/components/schemas/{$schemaName}",
                        $requestExample,
                    ),
                ],
            ];
        }

        $actionResponseExample = $this->exampleBuilder->getActionResponseExample($resource, $action);

        if ($isChatTest) {
            $sseContent = [
                'schema' => [
                    'type' => 'string',
                    'description' => 'Server-Sent Events stream (OpenAI Responses API compatible). Each event has an "event:" field and a "data:" field (JSON-encoded). Event types: response.created, response.in_progress, response.output_item.added, response.content_part.added, response.output_text.delta, response.output_text.done, response.content_part.done, response.output_item.done, response.function_call_arguments.delta, response.function_call_arguments.done, response.completed, error.',
                    'oneOf' => [
                        ['$ref' => '#/components/schemas/SseResponseCreatedEvent'],
                        ['$ref' => '#/components/schemas/SseResponseInProgressEvent'],
                        ['$ref' => '#/components/schemas/SseOutputItemAddedEvent'],
                        ['$ref' => '#/components/schemas/SseContentPartAddedEvent'],
                        ['$ref' => '#/components/schemas/SseOutputTextDeltaEvent'],
                        ['$ref' => '#/components/schemas/SseOutputTextDoneEvent'],
                        ['$ref' => '#/components/schemas/SseContentPartDoneEvent'],
                        ['$ref' => '#/components/schemas/SseOutputItemDoneEvent'],
                        ['$ref' => '#/components/schemas/SseFunctionCallArgumentsDeltaEvent'],
                        ['$ref' => '#/components/schemas/SseFunctionCallArgumentsDoneEvent'],
                        ['$ref' => '#/components/schemas/SseResponseCompletedEvent'],
                        ['$ref' => '#/components/schemas/SseErrorEvent'],
                    ],
                ],
            ];
            if ($actionResponseExample !== null) {
                $sseContent['example'] = $actionResponseExample;
            }
            $operation['responses'] = [
                '200' => [
                    'description' => 'SSE stream',
                    'content' => [
                        'text/event-stream' => $sseContent,
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ];
        } else {
            $className = $this->schemaBuilder->typeToClassName($resource);
            $operation['responses'] = [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/vnd.api+json' => $this->withExample(
                            "#/components/schemas/{$className}SingleResponse",
                            $actionResponseExample,
                        ),
                    ],
                ],
                '201' => [
                    'description' => 'Created',
                    'content' => [
                        'application/vnd.api+json' => $this->withExample(
                            "#/components/schemas/{$className}SingleResponse",
                            $actionResponseExample,
                        ),
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ];
        }

        return $operation;
    }

    /**
     * Build a meta endpoint operation (GET /{resource}/schema).
     *
     * These endpoints return client-side schemas (form validation info, field types,
     * constraints, permissions) and are not standard JSON:API resource operations.
     */
    private function buildMetaOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
    ): ?array {
        if ($method !== 'get') {
            return null;
        }

        return [
            'summary' => "Get client schema for {$resource}",
            'operationId' => "get{$className}ClientSchema",
            'tags' => [$tag],
            'responses' => [
                '200' => [
                    'description' => 'Client schema with field types, constraints, and permissions',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'description' => "Client-friendly schema for {$resource}. Includes attributes with validation constraints, relationships with cardinatity, writable_on paths, and available actions with their input schemas.",
                            ],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
            ],
        ];
    }

    /**
     * Resolve the FormRequest class for a custom action via controller method reflection.
     *
     * Inspects the controller method's typed parameters for a class extending
     * {@see FormRequest} with a name ending in "Request".
     *
     * @param  mixed  $route  The Laravel route instance.
     * @param  string  $action  The action name (used for context only).
     * @return string|null The FormRequest FQCN, or null if not found.
     */
    private function resolveActionRequestClass($route, string $action): ?string
    {
        $uses = $route->getAction('uses');
        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return null;
        }

        [$controller, $method] = explode('@', $uses);

        try {
            $reflection = new \ReflectionMethod($controller, $method);
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    if (is_subclass_of($typeName, FormRequest::class) && str_ends_with($typeName, 'Request')) {
                        return $typeName;
                    }
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Wrap a schema $ref with an optional media-type-level example.
     *
     * Examples are placed at the media-type level (sibling of `schema`) because
     * most OpenAPI tools (including Bruno) do not resolve `example` from inside $ref'd schemas.
     *
     * @param  string  $schemaRef  The $ref path to the component schema.
     * @param  array|null  $example  The example data, or null to omit.
     * @return array Media-type object with `schema` and optionally `example`.
     */
    private function withExample(string $schemaRef, ?array $example): array
    {
        $mediaType = ['schema' => ['$ref' => $schemaRef]];
        if ($example !== null) {
            $mediaType['example'] = $example;
        }

        return $mediaType;
    }

    /**
     * Build the OpenAPI `id` path parameter for a resource endpoint.
     *
     * @param  string  $resource  JSON:API resource type for the description.
     * @return array OpenAPI parameter definition.
     */
    private function buildIdParameter(string $resource): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
            'description' => ucfirst(str_replace('-', ' ', $resource)).' ID',
        ];
    }

    /**
     * Convert a hyphen/underscore-separated string to camelCase.
     *
     * @param  string  $input  The input string (e.g., "chat-test", "ai_model").
     * @return string The camelCased string (e.g., "chatTest", "aiModel").
     */
    private function camelize(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input))));
    }
}
