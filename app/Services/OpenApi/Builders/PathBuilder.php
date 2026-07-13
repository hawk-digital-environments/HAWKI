<?php

declare(strict_types=1);

namespace App\Services\OpenApi\Builders;

use App\JsonApi\V1\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * @param SchemaBuilder  $schemaBuilder  used for type-to-class-name conversion and inverse resolution
     * @param ExampleBuilder $exampleBuilder used for generating filter, request, and response examples
     */
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly ExampleBuilder $exampleBuilder,
    ) {
    }

    /**
     * Build all OpenAPI paths from registered API routes.
     *
     * @param array $schemaMap    map of JSON:API type → schema metadata from OpenApiGenerator
     * @param bool  $withExamples whether to include media-type-level examples and filter examples from DB
     *
     * @return array openAPI paths array, sorted alphabetically by path
     */
    public function build(array $schemaMap, bool $withExamples): array
    {
        $paths = [];
        $routes = $this->getApiRoutes();

        foreach ($routes as $route) {
            $info = $this->parseRouteUri($route->uri());

            if (null === $info) {
                continue;
            }

            if (!$this->controllerActionExists($route)) {
                continue;
            }

            $normalizedUri = $this->normalizeUri($route->uri());

            foreach ($route->methods() as $method) {
                if ('HEAD' === $method) {
                    continue;
                }

                $operation = $this->buildOperation(
                    mb_strtolower($method),
                    $info,
                    $schemaMap,
                    $withExamples,
                    $route,
                );

                if (null !== $operation) {
                    $paths[$normalizedUri][mb_strtolower($method)] = $operation;
                }
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * A route is only documentable if its controller method actually exists.
     *
     * JSON:API registers related/relationship routes from the ->relationships()
     * declaration regardless of which Actions\* traits the controller uses, so a
     * route can be registered (and thus spec'd) without a backing handler. The base
     * controller has no __call() fallback, so method_exists() is a complete check.
     *
     * Non Class@method actions (closures, etc.) are left for the existing logic.
     *
     * @param mixed $route the Laravel route instance
     */
    private function controllerActionExists($route): bool
    {
        $uses = $route->getAction('uses');

        if (!\is_string($uses) || !str_contains($uses, '@')) {
            return true;
        }

        [$class, $method] = explode('@', $uses, 2);

        return \class_exists($class) && \method_exists($class, $method);
    }

    /**
     * Collect all API routes, excluding internal endpoints (user, ai-req, docs).
     *
     * @return array<int, \Illuminate\Routing\Route> filtered list of API routes
     */
    private function getApiRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->filter(static fn ($route) => !str_starts_with($route->uri(), 'api/user'))
            ->filter(static fn ($route) => !str_starts_with($route->uri(), 'api/ai-req'))
            ->filter(static fn ($route) => !str_starts_with($route->uri(), 'api/docs'))
            ->filter(static fn ($route) => !str_ends_with($route->uri(), '/openapi.json'))
            ->values()
            ->all();
    }

    /**
     * Normalize a Laravel route URI to an OpenAPI path string.
     *
     * Strips the "api/" prefix, replaces named parameters with `{id}`,
     * and prepends a leading slash.
     *
     * @param string $uri The Laravel route URI (e.g., "api/assistants/{assistant}").
     *
     * @return string OpenAPI path (e.g., "/assistants/{id}").
     */
    private function normalizeUri(string $uri): string
    {
        $path = preg_replace('#^api/#', '', $uri);
        $path = preg_replace('#\{[^}]+\}#', '{id}', $path);

        return '/' . $path;
    }

    /**
     * Classify a route URI into an endpoint type and extract resource/relation/action parts.
     *
     * @param string $uri the Laravel route URI (without "api/" prefix already stripped)
     *
     * @return null|array associative array with type, resource, and optionally relation/action, or null if unrecognised
     */
    private function parseRouteUri(string $uri): ?array
    {
        $path = preg_replace('#^api/#', '', $uri);

        $prefix = ltrim(Server::BASE_URL_PREFIX, '/');

        if (str_starts_with($path, $prefix . '/')) {
            $path = substr($path, strlen($prefix) + 1);
        }

        if (preg_match('#^([^/]+)/\{[^}]+\}/([^/]+)/\{[^}]+\}$#', $path, $m)) {
            return ['type' => 'nestedItem', 'resource' => $m[1], 'relation' => $m[2]];
        }

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

        if (preg_match('#^([^/]+)/(?:[^/]+/)?([^/]+)$#', $path, $m)) {
            return ['type' => 'custom', 'resource' => $m[1], 'action' => $m[2]];
        }

        if (preg_match('#^([^/]+)$#', $path, $m)) {
            return ['type' => 'collection', 'resource' => $m[1]];
        }

        return null;
    }

    /**
     * Dispatch to the appropriate operation builder based on endpoint type.
     *
     * @param string $method       HTTP method (get, post, patch, delete)
     * @param array  $info         parsed route info from {@see parseRouteUri()}
     * @param array  $schemaMap    resource metadata map
     * @param bool   $withExamples whether to include examples
     * @param mixed  $route        the Laravel route instance
     *
     * @return null|array openAPI operation array, or null if the method is unsupported for this endpoint type
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
            'related' => $this->buildRelatedOperation($method, $info['resource'], $className, $tag, $info['relation'], $meta, $schemaMap),
            'relationship' => $this->buildRelationshipOperation($method, $info['resource'], $tag, $info['relation'], $meta),
            'nestedItem' => $this->buildNestedItemOperation($method, $info['resource'], $className, $tag, $info['relation'], $meta),
            'action' => $this->buildActionOperation($method, $info['resource'], $tag, $info['action'], $route),
            'custom' => $this->buildCustomOperation($method, $info['resource'], $info['action'], $route),
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
     * @param string     $method       HTTP method (get or post)
     * @param string     $resource     JSON:API resource type
     * @param string     $className    pascalCase class name for schema refs
     * @param string     $tag          operation tag
     * @param null|array $meta         schema metadata from schemaMap, or null
     * @param bool       $withExamples whether to include examples
     */
    private function buildCollectionOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        ?array $meta,
        bool $withExamples,
    ): ?array {
        if ('get' === $method) {
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

                        if (!empty($examples)) {
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

        if ('post' === $method && $meta) {
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
     * @param string     $method    HTTP method (get, patch, delete)
     * @param string     $resource  JSON:API resource type
     * @param string     $className pascalCase class name for schema refs
     * @param string     $tag       operation tag
     * @param null|array $meta      schema metadata, or null
     */
    private function buildResourceOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        ?array $meta,
    ): ?array {
        $pathParam = $this->buildIdParameter($resource);

        if ('get' === $method) {
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

        if ('patch' === $method && $meta) {
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

        if ('delete' === $method) {
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
     * @param string     $method    HTTP method (only GET supported)
     * @param string     $resource  JSON:API resource type
     * @param string     $className pascalCase class name
     * @param string     $tag       operation tag
     * @param string     $relation  the relationship field name
     * @param null|array $meta      schema metadata, or null
     */
    private function buildRelatedOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        string $relation,
        ?array $meta,
        array $schemaMap = [],
    ): ?array {
        $inverseClassName = null;
        $inverseType = $resource;

        if ($meta) {
            $schema = $meta['schema'];
            $normalizedName = str_replace('-', '_', $relation);

            foreach ($schema->fields() as $field) {
                if ($field->name() === $normalizedName) {
                    try {
                        $inverseType = $field->inverse();
                        $inverseClassName = $this->schemaBuilder->typeToClassName($inverseType);
                    } catch (\Throwable) {
                    }

                    break;
                }
            }
        }

        $relationLabel = str_replace('-', ' ', $relation);

        if ('post' === $method) {
            $relationMeta = $schemaMap[$inverseType] ?? null;
            $hasRelation = $inverseType !== $resource;

            if ($hasRelation && $relationMeta) {
                $requestExample = $this->exampleBuilder->buildRequestExample($relationMeta['schema'], $inverseType);
            } else {
                $requestExample = null;
            }

            $summary = $hasRelation
                ? "Create {$relationLabel} for {$resource}"
                : ucfirst(str_replace('-', ' ', $relation)) . " {$resource}";

            $operation = [
                'summary' => $summary,
                'operationId' => "create{$className}" . ucfirst($this->camelize($relation)),
                'tags' => [$tag],
                'parameters' => [$this->buildIdParameter($resource)],
                'responses' => [
                    '200' => [
                        'description' => 'Successful',
                        'content' => [
                            'application/vnd.api+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => ['$ref' => '#/components/schemas/AssistantsResource'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ];

            if ($hasRelation) {
                $mediaType = ['schema' => ['$ref' => "#/components/schemas/{$inverseClassName}CreateRequest"]];

                if (null !== $requestExample) {
                    $mediaType['example'] = $requestExample;
                }

                $operation['requestBody'] = [
                    'required' => true,
                    'content' => ['application/vnd.api+json' => $mediaType],
                ];
                $operation['responses']['201'] = [
                    'description' => 'Created',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => ['$ref' => "#/components/schemas/{$inverseClassName}Resource"],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            return $operation;
        }

        if ('patch' === $method) {
            // Update a nested sub-resource (e.g. PATCH /assistants/{id}/assistant-review).
            $relationMeta = $schemaMap[$inverseType] ?? null;
            $requestExample = $relationMeta
                ? $this->exampleBuilder->buildRequestExample($relationMeta['schema'], $inverseType)
                : null;

            $mediaType = ['schema' => ['$ref' => "#/components/schemas/{$inverseClassName}UpdateRequest"]];

            if (null !== $requestExample) {
                $mediaType['example'] = $requestExample;
            }

            return [
                'summary' => "Update {$relationLabel} for {$resource}",
                'operationId' => "update{$className}" . ucfirst($this->camelize($relation)),
                'tags' => [$tag],
                'parameters' => [$this->buildIdParameter($resource)],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/vnd.api+json' => $mediaType],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Updated',
                        'content' => [
                            'application/vnd.api+json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => ['$ref' => "#/components/schemas/{$inverseClassName}Resource"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
                ],
            ];
        }

        if ('delete' === $method) {
            return [
                'summary' => "Delete {$relationLabel} for {$resource}",
                'operationId' => "delete{$className}" . ucfirst($this->camelize($relation)),
                'tags' => [$tag],
                'parameters' => [$this->buildIdParameter($resource)],
                'responses' => [
                    '204' => ['description' => 'Removed'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ];
        }

        if ('get' !== $method) {
            return null;
        }

        $isToMany = false;

        if ($meta) {
            $schema = $meta['schema'];
            $normalizedName = str_replace('-', '_', $relation);

            foreach ($schema->fields() as $field) {
                if ($field->name() === $normalizedName) {
                    $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);

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

        return [
            'summary' => "Get {$relationLabel} for {$resource}",
            'operationId' => "get{$className}" . ucfirst($this->camelize($relation)),
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
     * Build a nested sub-resource item operation (e.g. DELETE /{resource}/{id}/{relation}/{relationId}).
     *
     * @param string     $method    HTTP method (only DELETE supported)
     * @param string     $resource  JSON:API resource type
     * @param string     $className pascalCase class name
     * @param string     $tag       operation tag
     * @param string     $relation  the relationship field name
     * @param null|array $meta      schema metadata, or null
     */
    private function buildNestedItemOperation(
        string $method,
        string $resource,
        string $className,
        string $tag,
        string $relation,
        ?array $meta,
    ): ?array {
        if ('delete' !== $method) {
            return null;
        }

        $relationLabel = str_replace('-', ' ', $relation);

        return [
            'summary' => "Delete {$relationLabel} for {$resource}",
            'operationId' => "delete{$className}" . ucfirst($this->camelize($relation)),
            'tags' => [$tag],
            'parameters' => [
                $this->buildIdParameter($resource),
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => ucfirst($relationLabel) . ' ID',
                ],
            ],
            'responses' => [
                '204' => ['description' => 'Deleted'],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
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
     * @param string     $method   HTTP method (only GET supported)
     * @param string     $resource JSON:API resource type
     * @param string     $tag      operation tag
     * @param string     $relation the relationship field name
     * @param null|array $meta     schema metadata, or null
     */
    private function buildRelationshipOperation(
        string $method,
        string $resource,
        string $tag,
        string $relation,
        ?array $meta,
    ): ?array {
        $isToMany = false;
        $inverseType = $resource;

        if ($meta) {
            $schema = $meta['schema'];
            $normalizedName = str_replace('-', '_', $relation);

            foreach ($schema->fields() as $field) {
                if ($field->name() === $normalizedName) {
                    $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);

                    try {
                        $inverseType = $field->inverse();
                    } catch (\Throwable) {
                    }

                    break;
                }
            }
        }

        $dataSchema = $isToMany
            ? ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ResourceIdentifier']]
            : ['$ref' => '#/components/schemas/ResourceIdentifier'];

        $relationLabel = str_replace('-', ' ', $relation);
        $className = $this->schemaBuilder->typeToClassName($resource);
        $pathParam = $this->buildIdParameter($resource);

        if ('get' === $method) {
            return [
                'summary' => "Get {$relationLabel} relationship links for {$resource}",
                'operationId' => "get{$className}Relationship" . ucfirst($this->camelize($relation)),
                'tags' => [$tag],
                'parameters' => [$pathParam],
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

        // Write operations (attach/replace/detach) only exist for writable relationships.
        $identifier = ['type' => $inverseType, 'id' => '1'];
        $requestExample = ['data' => $isToMany ? [$identifier] : $identifier];

        $descriptions = [
            'post' => "Attach items to the {$relationLabel} relationship for {$resource}",
            'patch' => "Replace the {$relationLabel} relationship for {$resource}",
            'delete' => "Detach items from the {$relationLabel} relationship for {$resource}",
        ];

        if (!isset($descriptions[$method])) {
            return null;
        }

        $operationId = match ($method) {
            'post' => "attach{$className}Relationship" . ucfirst($this->camelize($relation)),
            'patch' => "replace{$className}Relationship" . ucfirst($this->camelize($relation)),
            'delete' => "detach{$className}Relationship" . ucfirst($this->camelize($relation)),
        };

        return [
            'summary' => $descriptions[$method],
            'operationId' => $operationId,
            'tags' => [$tag],
            'parameters' => [$pathParam],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/vnd.api+json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => $dataSchema,
                            ],
                        ],
                        'example' => $requestExample,
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Updated relationship links',
                    'content' => [
                        'application/vnd.api+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => $dataSchema,
                                    'links' => ['$ref' => '#/components/schemas/RelationshipLinks'],
                                ],
                            ],
                            'example' => $requestExample,
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
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
     * @param string $method   HTTP method (only POST supported)
     * @param string $resource JSON:API resource type
     * @param string $tag      operation tag
     * @param string $action   The action name (e.g., "chat-test", "favorite").
     * @param mixed  $route    the Laravel route instance
     */
    private function buildActionOperation(
        string $method,
        string $resource,
        string $tag,
        string $action,
        $route,
    ): ?array {
        if ('post' !== $method) {
            return null;
        }

        $operation = [
            'summary' => ucfirst(str_replace('-', ' ', $action)) . " {$resource}",
            'operationId' => "{$this->camelize($action)}{$this->schemaBuilder->typeToClassName($resource)}",
            'tags' => [$tag],
            'parameters' => [
                $this->buildIdParameter($resource),
            ],
        ];

        $requestClass = $this->resolveActionRequestClass($route, $action);
        $isChatTest = 'chat-test' === $action;
        $schemaName = "{$this->schemaBuilder->typeToClassName($resource)}{$this->schemaBuilder->typeToClassName($action)}Request";

        if (null !== $requestClass) {
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
            $operation['responses'] = [
                '200' => [
                    'description' => 'SSE stream',
                    'content' => [
                        'text/event-stream' => $this->buildSseResponseContent($actionResponseExample),
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
     * Build the media-type object describing the SSE response body.
     *
     * Reused by the chat-test action and any custom streaming endpoint. References
     * the shared SSE event schemas assembled by {@see \App\Services\OpenApi\OpenApiGenerator::buildSharedSchemas()}.
     *
     * @param null|array|string $example an optional media-type-level example (raw SSE text)
     *
     * @return array<string, mixed>
     */
    private function buildSseResponseContent(null|array|string $example = null): array
    {
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

        if (null !== $example) {
            $sseContent['example'] = $example;
        }

        return $sseContent;
    }

    /**
     * Build an operation for a custom (non JSON:API) endpoint with two or three
     * literal path segments, e.g. POST /{resource}/{action} like /chat/responses
     * or POST /{resource}/{version}/{action} like /openai/v1/responses.
     *
     * Discovers the request FormRequest class via controller method reflection
     * (supports both named methods and invokable {@see __invoke} controllers).
     * When the controller method declares a {@see StreamedResponse} return type,
     * the operation is documented as an SSE streaming response.
     *
     * Returns null for endpoints that have neither a request body nor a streaming
     * response, so unrelated two-segment routes are not spec'd by accident.
     *
     * @param string $method HTTP method
     * @param string $resource the first path segment (e.g. "chat")
     * @param string $action the second path segment (e.g. "responses")
     * @param mixed $route the Laravel route instance
     */
    private function buildCustomOperation(
        string $method,
        string $resource,
        string $action,
        $route,
    ): ?array {
        $requestClass = $this->resolveActionRequestClass($route, $action);
        $isStreaming = $this->returnsStreamedResponse($route);

        if (null === $requestClass && !$isStreaming) {
            return null;
        }

        $tag = $this->schemaBuilder->typeToClassName($resource);
        $schemaName = $tag . $this->schemaBuilder->typeToClassName($action) . 'Request';

        $operation = [
            'summary' => ucfirst(str_replace('-', ' ', $action)) . " {$resource}",
            'operationId' => $this->camelize($action) . $tag,
            'tags' => [$tag],
            'parameters' => [],
        ];

        if (null !== $requestClass) {
            $requestExample = $this->exampleBuilder->getActionRequestExample($resource, $action);
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => $this->withExample(
                        "#/components/schemas/{$schemaName}",
                        $requestExample,
                    ),
                ],
            ];
        }

        $responseExample = $this->exampleBuilder->getActionResponseExample($resource, $action);

        if ($isStreaming) {
            $operation['responses'] = [
                '200' => [
                    'description' => 'SSE stream',
                    'content' => [
                        'text/event-stream' => $this->buildSseResponseContent($responseExample),
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
                '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
            ];
        } else {
            $operation['responses'] = [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
            ];
        }

        return $operation;
    }

    /**
     * Determine whether the route's controller method returns a streamed response.
     *
     * Used to decide whether a custom endpoint should be documented with the
     * SSE media type. Works for both named methods and invokable controllers
     * (whose action is normalised to "Controller@__invoke" by Laravel).
     *
     * @param mixed $route the Laravel route instance
     */
    private function returnsStreamedResponse($route): bool
    {
        $uses = $route->getAction('uses');

        if (!\is_string($uses) || !str_contains($uses, '@')) {
            return false;
        }

        [$controller, $method] = explode('@', $uses, 2);

        try {
            $reflection = new \ReflectionMethod($controller, $method);
            $type = $reflection->getReturnType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $name = $type->getName();

                return is_a($name, StreamedResponse::class, true);
            }
        } catch (\Throwable) {
        }

        return false;
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
        if ('get' !== $method) {
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
                                'description' => "Client-friendly schema for {$resource}. Includes attributes with validation constraints, relationships with cardinality and type references, JSON Pointer paths for writable fields, and available actions with their input schemas.",
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
     * @param mixed  $route  the Laravel route instance
     * @param string $action the action name (used for context only)
     *
     * @return null|string the FormRequest FQCN, or null if not found
     */
    private function resolveActionRequestClass($route, string $action): ?string
    {
        $uses = $route->getAction('uses');

        if (!\is_string($uses) || !str_contains($uses, '@')) {
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
     * @param string     $schemaRef the $ref path to the component schema
     * @param null|array $example   the example data, or null to omit
     *
     * @return array media-type object with `schema` and optionally `example`
     */
    private function withExample(string $schemaRef, ?array $example): array
    {
        $mediaType = ['schema' => ['$ref' => $schemaRef]];

        if (null !== $example) {
            $mediaType['example'] = $example;
        }

        return $mediaType;
    }

    /**
     * Build the OpenAPI `id` path parameter for a resource endpoint.
     *
     * @param string $resource JSON:API resource type for the description
     *
     * @return array openAPI parameter definition
     */
    private function buildIdParameter(string $resource): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
            'description' => ucfirst(str_replace('-', ' ', $resource)) . ' ID',
        ];
    }

    /**
     * Convert a hyphen/underscore-separated string to camelCase.
     *
     * @param string $input The input string (e.g., "chat-test", "ai_model").
     *
     * @return string The camelCased string (e.g., "chatTest", "aiModel").
     */
    private function camelize(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input))));
    }
}
