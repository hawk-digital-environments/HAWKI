<?php

declare(strict_types=1);

namespace App\Services\ClientSchema;

use App\JsonApi\V1\Server;
use App\Services\OpenApi\Builders\SchemaBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Assembles the machine-readable client schema document served by
 * ClientSchemaController: one entry per JSON:API resource, merged from the
 * sources of truth the API itself uses — schema classes, FormRequest
 * validation rules, the route table, Gate policies, Eloquent model
 * defaults, and the static override tables below.
 *
 * Concept and document anatomy:
 * hawki-workflows-doc/CLIENT-SCHEMA-CONCEPT.md.
 */
class ClientSchemaGenerator
{
    /**
     * Database-only enum values not enforced in PHP validation rules.
     * Mirrors SchemaBuilder::DB_ENUMS so the client schema includes them.
     */
    private const DB_ENUMS = [
        'assistants' => [
            'release_stage' => ['draft', 'private', 'organizational', 'federated'],
        ],
        'ai-tools' => [
            'type' => ['mcp', 'function'],
            'status' => ['active', 'inactive'],
        ],
        'ai-model-statuses' => [
            'status' => ['online', 'offline', 'unknown'],
        ],
    ];

    /**
     * Custom write paths that cannot be auto-discovered from schema metadata
     * or route patterns (e.g. self-referential action attributes like favorite).
     */
    private const CUSTOM_WRITE_PATHS = [
        'assistants' => [
            'attributes' => [
                'is_favorite' => [
                    ['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/actions/favorite'],
                    ['method' => 'DELETE', 'path' => '/api/hawki/v1/assistants/{id}/actions/favorite'],
                ],
                'release_stage' => [
                    ['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/actions/release'],
                ],
            ],
        ],
    ];

    /**
     * Memoized nested-write routes per resource type, built by scanning
     * registered routes for patterns like POST /assistants/{id}/actions/favorite.
     *
     * @var array<string, array<string, list<array{method:string, path:string}>>>
     */
    private array $nestedWriteCache = [];

    /**
     * Memoized relationship URI names that expose a GET related (fetch) route,
     * keyed by resource type. Relationships without such a route (write-only
     * relations) are not advertised with a fetch endpoint.
     *
     * @var array<string, list<string>>
     */
    private array $relationshipFetchCache = [];

    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly Server $server,
    ) {
    }

    /**
     * Return the API path prefix used in URLs (leading slash), e.g. "/api/hawki/v1".
     */
    private function urlPrefix(): string
    {
        return '/api' . Server::BASE_URL_PREFIX;
    }

    /**
     * Return the API prefix as it appears in Laravel route URIs (no leading slash), e.g. "api/hawki/v1".
     */
    private function routePrefix(): string
    {
        return ltrim($this->urlPrefix(), '/');
    }

    /**
     * Entry point: build the full document by iterating every schema
     * registered in the JSON:API server. Supports the kernel's cached
     * fetch (ClientSchemaController) and, through it, all frontend
     * consumers (form rendering, write-path lookup, action discovery).
     */
    public function generate(?Authenticatable $user): array
    {
        $schemaClasses = $this->getSchemaClasses();
        $resources = [];

        foreach ($schemaClasses as $schemaClass) {
            $type = $schemaClass::type();
            $schema = $this->server->schemas()->schemaFor($type);

            if (!$schema instanceof Schema) {
                continue;
            }

            $resources[$type] = $this->buildResource($schema, $schemaClass, $user);
        }

        return [
            'version' => '1.0',
            'generatedAt' => now()->toIso8601String(),
            'resources' => $resources,
        ];
    }

    /**
     * Assemble one resource entry: splits the schema fields into attributes
     * and relationships, then fills the endpoints/attributes/schema/
     * relationships/actions/filters/sortable/includable blocks from the
     * shared inputs. Helper for generate().
     */
    private function buildResource(Schema $schema, string $schemaClass, ?Authenticatable $user): array
    {
        $type = $schema::type();
        $constraints = $this->schemaBuilder->parseValidationConstraints($schema);
        $fieldConstraints = $constraints['constraints'];
        $requiredFields = $constraints['required'];
        $validatedFields = $constraints['validated'];

        $attributeFields = [];
        $relationshipFields = [];

        foreach ($schema->fields() as $field) {
            if ($field instanceof ID) {
                continue;
            }

            if (method_exists($field, 'isHidden') && $field->isHidden(null)) {
                continue;
            }

            if ($this->schemaBuilder->isRelation($field)) {
                $relationshipFields[] = $field;
            } else {
                $attributeFields[] = $field;
            }
        }

        $actionRoutes = $this->discoverActionRoutes($type);
        $isAuthorizable = method_exists($schema, 'authorizable') ? $schema->authorizable() : true;
        $defaults = $this->modelAttributeDefaults($schemaClass);

        $resource = [
            'type' => $type,
            'displayName' => $this->deriveDisplayName($type),
        ];

        if ($this->hasApiEndpoint($type)) {
            $resource['endpoints'] = $this->buildEndpoints($type, $schemaClass, $isAuthorizable, $user);
        }

        $resource['attributes'] = $this->buildAttributes($attributeFields, $validatedFields, $actionRoutes, $fieldConstraints, $requiredFields, $defaults, $type);
        $resource['schema'] = $this->buildJsonSchema($attributeFields, $fieldConstraints, $requiredFields, $defaults, $type);
        $resource['relationships'] = $this->buildRelationships($relationshipFields, $validatedFields, $actionRoutes, $type);
        $resource['actions'] = $this->buildActions($type, $actionRoutes, $user);
        $resource['filters'] = $this->buildFilters($schema);
        $resource['sortable'] = $this->buildSortable($schema);
        $resource['includable'] = $this->buildIncludable($schema);

        return $resource;
    }

    /**
     * Decide whether a resource type has standalone CRUD routes at all.
     * Gates the endpoints block: relationship-only resources (registered
     * schema, no own routes — e.g. ai-conv-messages) correctly omit it.
     */
    private function hasApiEndpoint(string $type): bool
    {
        $prefix = $this->routePrefix();
        /** @var iterable<\Illuminate\Routing\Route> $routes */
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            if ($uri === "{$prefix}/{$type}" || str_starts_with($uri, "{$prefix}/{$type}/")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the flat attribute map: simplified type, readOnly, required,
     * formatted constraints, writable_on write paths, and the model-derived
     * default per field. Supports editor form rendering and client-side
     * validation; feeds the attributes block.
     */
    private function buildAttributes(
        array $fields,
        array $validatedFields,
        array $actionRoutes,
        array $fieldConstraints,
        array $requiredFields,
        array $defaults,
        string $resourceType,
    ): array {
        $attributes = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $openApiType = $this->schemaBuilder->mapFieldType($field);

            $mergedConstraints = $this->mergeDbEnums($fieldConstraints[$name] ?? [], $resourceType, $name);

            $attr = [
                'type' => $this->simplifyType($openApiType, $field, $mergedConstraints),
            ];

            $isReadOnly = method_exists($field, 'isReadOnly') && $field->isReadOnly(null);

            if ($isReadOnly) {
                $attr['readOnly'] = true;
            }

            if (\in_array($name, $requiredFields, true)) {
                $attr['required'] = true;
            }

            $attr['constraints'] = $this->formatConstraints($mergedConstraints, $openApiType);

            $writableOn = $this->resolveWritableOn($name, $resourceType, $isReadOnly, $validatedFields, context: 'attributes');

            if (null !== $writableOn) {
                $attr['writable_on'] = $writableOn;
            }

            if (\array_key_exists($name, $defaults)) {
                $attr['default'] = $defaults[$name];
            }

            $attributes[$name] = $attr;
        }

        return $attributes;
    }

    /**
     * Assemble the per-resource JSON-Schema document used for form rendering: the
     * attribute contract expressed with JSON-Schema keywords (type, enum, bounds,
     * maxLength) plus the required list and model-derived defaults. Attributes
     * only — relationships keep their resource-identifier write shape and are
     * described by the relationships map.
     *
     * @param list<object> $fields
     * @param array<string, array> $fieldConstraints
     * @param list<string> $requiredFields
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    private function buildJsonSchema(
        array $fields,
        array $fieldConstraints,
        array $requiredFields,
        array $defaults,
        string $resourceType,
    ): array {
        $properties = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $mergedConstraints = $this->mergeDbEnums($fieldConstraints[$name] ?? [], $resourceType, $name);
            $property = $this->jsonSchemaProperty($field, $mergedConstraints);

            if (\array_key_exists($name, $defaults)) {
                $property['default'] = $defaults[$name];
            }

            $properties[$name] = $property;
        }

        $required = array_values(array_intersect($requiredFields, array_keys($properties)));

        $schema = ['type' => 'object', 'properties' => $properties];

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $constraints
     *
     * @return array<string, mixed>
     */
    private function jsonSchemaProperty(object $field, array $constraints): array
    {
        $property = [];

        if ($field instanceof DateTime) {
            $property['type'] = 'string';
            $property['format'] = 'date-time';
        } elseif ($field instanceof Boolean) {
            $property['type'] = 'boolean';
        } elseif ($field instanceof Number) {
            $property['type'] = 'integer' === ($constraints['type'] ?? null) ? 'integer' : 'number';
        } else {
            $openApiType = $this->schemaBuilder->mapFieldType($field);
            $property['type'] = $openApiType['type'] ?? 'string';
        }

        if (isset($constraints['enum'])) {
            $property['enum'] = $constraints['enum'];
        }

        if (isset($constraints['minimum'])) {
            $property['minimum'] = $constraints['minimum'];
        }

        if (isset($constraints['maximum'])) {
            $property['maximum'] = $constraints['maximum'];
        }

        if (isset($constraints['maxLength'])) {
            $property['maxLength'] = $constraints['maxLength'];
        }

        return $property;
    }

    /**
     * Raw attribute defaults a fresh model instance carries, so editors can
     * pre-fill create forms. Models without declared attribute defaults — and
     * schema classes without a model — yield an empty map.
     *
     * @param class-string $schemaClass
     *
     * @return array<string, mixed>
     */
    private function modelAttributeDefaults(string $schemaClass): array
    {
        $modelClass = $schemaClass::$model ?? null;

        if (null === $modelClass || !class_exists($modelClass)) {
            return [];
        }

        try {
            return (new $modelClass())->getAttributes();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build the relationships map: cardinality, inverse resource type,
     * readOnly, writable_on write paths, and a fetch endpoint only when a
     * GET related route is registered. Supports resource-identifier write
     * shaping and include decisions in the client.
     */
    private function buildRelationships(
        array $fields,
        array $validatedFields,
        array $actionRoutes,
        string $resourceType,
    ): array {
        $relationships = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $isReadOnly = method_exists($field, 'isReadOnly') && $field->isReadOnly(null);

            $cardinality = 'toOne';

            if ($field instanceof HasMany || $field instanceof BelongsToMany) {
                $cardinality = 'toMany';
            }

            $rel = [
                'cardinality' => $cardinality,
            ];

            try {
                $rel['type'] = $field->inverse();
            } catch (\Throwable) {
                $rel['type'] = null;
            }

            if ($isReadOnly) {
                $rel['readOnly'] = true;
            }

            $isToMany = $field instanceof HasMany || $field instanceof BelongsToMany;

            $writableOn = $this->resolveWritableOn($name, $resourceType, $isReadOnly, $validatedFields, context: 'relationships', isToMany: $isToMany);

            if (null !== $writableOn) {
                $rel['writable_on'] = $writableOn;
            }

            // Advertise a fetch endpoint only when a GET related route is actually
            // registered for this relationship (write-only relations have none).
            $uriName = method_exists($field, 'uriName') ? $field->uriName() : $name;

            if (\in_array($uriName, $this->relationshipFetchRelations($resourceType), true)) {
                $rel['endpoints'] = $this->buildRelationshipEndpoints($resourceType, $name);
            }

            $relationships[$name] = $rel;
        }

        return $relationships;
    }

    /**
     * Build the actions map from the discovered action routes: method,
     * client-facing URL, allowed flag, and the simplified input contract
     * when the action's FormRequest describes one. Supports action button
     * rendering and request construction in the client.
     */
    private function buildActions(string $type, array $actionRoutes, ?Authenticatable $user): array
    {
        $actions = [];
        $prefix = $this->urlPrefix();

        foreach ($actionRoutes as $actionName => $route) {
            $requestClass = $route['requestClass'];

            $action = [
                'method' => 'POST',
                'url' => "{$prefix}/{$type}/{id}/actions/{$actionName}",
                'allowed' => null !== $user,
            ];

            if (null !== $requestClass) {
                $inputSchema = $this->buildActionInputSchema($requestClass);

                if (null !== $inputSchema) {
                    $action['input'] = $inputSchema;
                }
            }

            $actions[$actionName] = $action;
        }

        return $actions;
    }

    /**
     * Derive an action's input contract from its FormRequest via the
     * SchemaBuilder OpenAPI machinery, simplified into the lean client
     * format. Only requests following the JSON:API data.attributes.*
     * shape yield a schema. Helper for buildActions().
     */
    private function buildActionInputSchema(string $requestClass): ?array
    {
        try {
            $openApiSchema = $this->schemaBuilder->buildActionRequestSchema($requestClass);
        } catch (\Throwable) {
            return null;
        }

        // Action requests only contribute an `input` contract when they follow
        // the JSON:API document shape (data.attributes.*). Body-less, multipart,
        // or flat-keyed action requests carry no describable attributes, so the
        // schema is walked level by level to avoid accessing offsets on null or
        // stdClass (the latter is emitted by parseRulesToSchema for empty rules).
        $properties = $openApiSchema['properties'] ?? null;
        if (!\is_array($properties)) {
            return null;
        }

        $dataSchema = $properties['data'] ?? null;
        if (!\is_array($dataSchema)) {
            return null;
        }

        $dataProperties = $dataSchema['properties'] ?? null;
        if (!\is_array($dataProperties)) {
            return null;
        }

        $attributes = $dataProperties['attributes'] ?? null;
        if (!\is_array($attributes)) {
            return null;
        }

        $attrProps = $attributes['properties'] ?? null;
        if (!\is_array($attrProps) || [] === $attrProps) {
            return null;
        }

        return [
            'type' => 'object',
            'properties' => [
                'attributes' => [
                    'type' => 'object',
                    'properties' => $this->simplifyOpenApiProperties($attrProps),
                    'required' => $attributes['required'] ?? [],
                ],
            ],
        ];
    }

    /**
     * Simplify a property map from the SchemaBuilder OpenAPI output into
     * the lean client format. Helper for buildActionInputSchema().
     */
    private function simplifyOpenApiProperties(array $properties): array
    {
        $result = [];

        foreach ($properties as $name => $prop) {
            $result[$name] = $this->simplifyOpenApiProperty($prop);
        }

        return $result;
    }

    /**
     * Simplify a single OpenAPI property into the client format
     * (type/enum/constraints/items/properties), recursing into nested
     * arrays and objects. Helper for simplifyOpenApiProperties().
     */
    private function simplifyOpenApiProperty(array $prop): array
    {
        $simplified = ['type' => $this->simplifyOpenApiType($prop['type'] ?? 'string')];

        if (isset($prop['enum'])) {
            $simplified['type'] = 'enum';
            $simplified['constraints'] = ['values' => $prop['enum']];
        }

        if (isset($prop['minimum'])) {
            $simplified['constraints'] ??= [];
            $simplified['constraints']['minimum'] = $prop['minimum'];
        }

        if (isset($prop['maximum'])) {
            $simplified['constraints'] ??= [];
            $simplified['constraints']['maximum'] = $prop['maximum'];
        }

        if (isset($prop['maxLength'])) {
            $simplified['constraints'] ??= [];
            $simplified['constraints']['maxLength'] = $prop['maxLength'];
        }

        if ('array' === $prop['type'] && isset($prop['items'])) {
            if (isset($prop['items']['properties'])) {
                $simplified['items'] = [
                    'type' => 'object',
                    'properties' => $this->simplifyOpenApiProperties($prop['items']['properties']),
                ];

                if (isset($prop['items']['required'])) {
                    $simplified['items']['required'] = $prop['items']['required'];
                }
            } elseif (isset($prop['items']['type'])) {
                $simplified['items'] = $this->simplifyOpenApiProperty($prop['items']);
            }
        }

        if (isset($prop['minItems'])) {
            $simplified['constraints'] ??= [];
            $simplified['constraints']['minItems'] = $prop['minItems'];
        }

        if ('object' === $prop['type'] && isset($prop['properties'])) {
            $simplified['properties'] = $this->simplifyOpenApiProperties($prop['properties']);

            if (isset($prop['required'])) {
                $simplified['required'] = $prop['required'];
            }
        }

        return $simplified;
    }

    /**
     * Fold OpenAPI scalar types into the client's reduced type set
     * (integer becomes number). Helper for simplifyOpenApiProperty().
     */
    private function simplifyOpenApiType(string $type): string
    {
        return match ($type) {
            'integer' => 'number',
            'number' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    /**
     * Resolve all write paths for one field by merging the four layers:
     * inline PATCH (validated fields), relationship routes, route-scanned
     * nested writes, and the CUSTOM_WRITE_PATHS fallback. Supports the
     * writable_on entries in attributes and relationships.
     */
    private function resolveWritableOn(
        string $fieldName,
        string $resourceType,
        bool $isReadOnly,
        array $validatedFields,
        string $context = 'attribute',
        bool $isToMany = false,
    ): ?array {
        $writableOn = [];
        $prefix = $this->urlPrefix();

        // 1. Inline PATCH (standard resource update)
        if (!$isReadOnly && \in_array($fieldName, $validatedFields, true)) {
            $writableOn[] = ['method' => 'PATCH', 'path' => "{$prefix}/{$resourceType}/{id}"];
        }

        // 2. Relationship endpoint writes (only for to-many; to-one gets PATCH only)
        if ('relationships' === $context && !$isReadOnly) {
            $uriName = str_replace('_', '-', $fieldName);

            if ($isToMany) {
                $writableOn[] = ['method' => 'POST', 'path' => "{$prefix}/{$resourceType}/{id}/relationships/{$uriName}"];
                $writableOn[] = ['method' => 'DELETE', 'path' => "{$prefix}/{$resourceType}/{id}/relationships/{$uriName}"];
            }

            $writableOn[] = ['method' => 'PATCH', 'path' => "{$prefix}/{$resourceType}/{id}/relationships/{$uriName}"];
        }

        // 3. Nested sub-resource writes (POST/PATCH/DELETE on /{resource}/{id}/{relation})
        $nested = $this->discoverNestedWrites($resourceType);

        foreach ($nested[$fieldName] ?? [] as $entry) {
            $writableOn[] = $entry;
        }

        // 4. Hardcoded custom paths (for self-referential endpoints like favorite)
        $customPaths = self::CUSTOM_WRITE_PATHS[$resourceType][$context][$fieldName] ?? [];

        foreach ($customPaths as $entry) {
            $writableOn[] = $entry;
        }

        if (empty($writableOn)) {
            return $isReadOnly ? null : [];
        }

        return $writableOn;
    }

    /**
     * Scan routes for nested sub-resource write operations and map them
     * to schema field names (relation dashes -> underscores).
     *
     * Patterns detected:
     *   POST  /{resource}/{id}/{relation}              -> nested create
     *   PATCH /{resource}/{id}/{relation}              -> nested update
     *   DELETE /{resource}/{id}/{relation}/{childId}   -> nested delete
     *
     * Returns a map of fieldName -> [{method, path}] for a given resource type.
     *
     * @return array<string, list<array{method:string, path:string}>>
     */
    private function discoverNestedWrites(string $resourceType): array
    {
        if (isset($this->nestedWriteCache[$resourceType])) {
            return $this->nestedWriteCache[$resourceType];
        }

        $result = [];
        $routePrefix = $this->routePrefix();
        $urlPrefix = $this->urlPrefix();

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (!str_starts_with($uri, "{$routePrefix}/{$resourceType}/")) {
                continue;
            }

            // Strip the route prefix (e.g. "api/hawki/v1/") so we can reason about the resource-relative path.
            $path = substr($uri, strlen($routePrefix) + 1);
            // Normalize all route params to {id} for client-facing URLs.
            $normalizedPath = preg_replace('#\{[^}]+\}#', '{id}', $path);

            // POST|PATCH /{resource}/{id}/{relation}
            if (preg_match('#^' . preg_quote($resourceType, '#') . '/\{[^}]+\}/([^/\{]+)$#', $path, $m)) {
                foreach ($route->methods() as $method) {
                    if ('POST' === $method || 'PATCH' === $method) {
                        $fieldName = str_replace('-', '_', $m[1]);
                        $result[$fieldName][] = [
                            'method' => $method,
                            'path' => $urlPrefix . '/' . $normalizedPath,
                        ];
                    }
                }
            }

            // DELETE /{resource}/{id}/{relation}/{childId}
            if (preg_match('#^' . preg_quote($resourceType, '#') . '/\{[^}]+\}/([^/]+)/\{[^}]+\}$#', $path, $m)) {
                foreach ($route->methods() as $method) {
                    if ('DELETE' === $method) {
                        $fieldName = str_replace('-', '_', $m[1]);
                        $result[$fieldName][] = [
                            'method' => $method,
                            'path' => $urlPrefix . '/' . $normalizedPath,
                        ];
                    }
                }
            }
        }

        $this->nestedWriteCache[$resourceType] = $result;

        return $result;
    }

    /**
     * Return the relationship URI names (kebab-case) that expose a GET related
     * route for the given resource type, e.g. GET /assistants/{id}/shared-users.
     *
     * Built by scanning registered routes, mirroring {@see discoverNestedWrites()}.
     * Write-only relationships (no related route) are absent from the result, so
     * the client schema does not advertise a fetch endpoint for them.
     *
     * @return list<string>
     */
    private function relationshipFetchRelations(string $resourceType): array
    {
        if (isset($this->relationshipFetchCache[$resourceType])) {
            return $this->relationshipFetchCache[$resourceType];
        }

        $prefix = $this->routePrefix();
        // Matches "{prefix}/{resourceType}/{id}/{relation}" with a single segment
        // for {relation>, excluding "/relationships/{x}" and "/actions/{x}".
        $pattern = '#^' . preg_quote("{$prefix}/{$resourceType}", '#') . '/\{[^}]+\}/([^/]+)$#';

        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!preg_match($pattern, $route->uri(), $m)) {
                continue;
            }

            if (\in_array('GET', $route->methods(), true) && !\in_array($m[1], $found, true)) {
                $found[] = $m[1];
            }
        }

        return $this->relationshipFetchCache[$resourceType] = $found;
    }

    /**
     * Build the five CRUD endpoint descriptors with normalized URLs and
     * allowed flags (Gate-checked viewAny/create, auth-checked otherwise).
     * Supports the endpoints block; only reached when hasApiEndpoint()
     * confirms standalone routes exist.
     */
    private function buildEndpoints(string $type, string $schemaClass, bool $isAuthorizable, ?Authenticatable $user): array
    {
        $auth = null !== $user;

        $modelClass = $schemaClass::$model ?? null;

        $createAllowed = $auth;
        $listAllowed = true;

        if ($isAuthorizable && $auth && null !== $modelClass) {
            $createAllowed = Gate::forUser($user)->allows('create', $modelClass);
            $listAllowed = Gate::forUser($user)->allows('viewAny', $modelClass);
        }

        $prefix = $this->urlPrefix();

        return [
            'list' => ['method' => 'GET', 'url' => "{$prefix}/{$type}", 'allowed' => $listAllowed],
            'create' => ['method' => 'POST', 'url' => "{$prefix}/{$type}", 'allowed' => $createAllowed],
            'read' => ['method' => 'GET', 'url' => "{$prefix}/{$type}/{id}", 'allowed' => $auth],
            'update' => ['method' => 'PATCH', 'url' => "{$prefix}/{$type}/{id}", 'allowed' => $auth],
            'delete' => ['method' => 'DELETE', 'url' => "{$prefix}/{$type}/{id}", 'allowed' => $auth],
        ];
    }

    /**
     * Build the fetch endpoint descriptor for one relationship (GET
     * related). Helper for buildRelationships().
     */
    private function buildRelationshipEndpoints(string $type, string $relationName): array
    {
        $prefix = $this->urlPrefix();

        return [
            'fetch' => [
                'method' => 'GET',
                'url' => "{$prefix}/{$type}/{id}/{$relationName}",
            ],
        ];
    }

    /**
     * List the schema's filters as filter[...] query parameter descriptors.
     * Supports list-view filtering in the client; feeds the filters block.
     */
    private function buildFilters(Schema $schema): array
    {
        $filters = [];

        foreach ($schema->filters() as $filter) {
            $filters[] = [
                'name' => "filter[{$filter->key()}]",
                'type' => 'string',
            ];
        }

        return $filters;
    }

    /**
     * Collect the sortable field names from the schema. Supports sort
     * parameter construction in list views; feeds the sortable block.
     */
    private function buildSortable(Schema $schema): array
    {
        $sortable = [];

        foreach ($schema->fields() as $field) {
            if (!\is_object($field)) {
                continue;
            }

            if (method_exists($field, 'isSortable') && $field->isSortable()) {
                $sortable[] = $field->name();
            }
        }

        return $sortable;
    }

    /**
     * Collect the includable relationship names from the schema.
     * Supports ?include= parameter construction; feeds the includable block.
     */
    private function buildIncludable(Schema $schema): array
    {
        $includable = [];

        foreach ($schema->fields() as $field) {
            if ($this->schemaBuilder->isRelation($field)) {
                $includable[] = $field->name();
            }
        }

        return $includable;
    }

    /**
     * Reduce a field to the client's display type (enum/datetime/number/
     * boolean/string). Helper for buildAttributes().
     */
    private function simplifyType(array $openApiType, $field, array $constraints): string
    {
        if (isset($constraints['enum'])) {
            return 'enum';
        }

        if ($field instanceof DateTime) {
            return 'datetime';
        }

        if ($field instanceof Number) {
            return 'number';
        }

        if ($field instanceof Boolean) {
            return 'boolean';
        }

        return $openApiType['type'] ?? 'string';
    }

    /**
     * Rename the raw parsed constraints into the client's constraint keys
     * (values/integer/minimum/maximum/maxLength). Helper for
     * buildAttributes().
     */
    private function formatConstraints(array $rawConstraints, array $openApiType): array
    {
        $formatted = [];

        if (isset($rawConstraints['enum'])) {
            $formatted['values'] = $rawConstraints['enum'];
        }

        if (isset($rawConstraints['type']) && 'integer' === $rawConstraints['type']) {
            $formatted['integer'] = true;
        }

        if (isset($rawConstraints['minimum'])) {
            $formatted['minimum'] = $rawConstraints['minimum'];
        }

        if (isset($rawConstraints['maximum'])) {
            $formatted['maximum'] = $rawConstraints['maximum'];
        }

        if (isset($rawConstraints['maxLength'])) {
            $formatted['maxLength'] = $rawConstraints['maxLength'];
        }

        return $formatted;
    }

    /**
     * Scan the route table for action routes of a resource type
     * (/{type}/{id}/actions/{name}) and resolve each action's FormRequest
     * class via reflection. Supports the actions block and the
     * writable_on custom layers; feeds buildActions().
     */
    private function discoverActionRoutes(string $type): array
    {
        $actions = [];
        $prefix = $this->routePrefix();
        $pattern = '#^' . preg_quote($prefix, '#') . '/([^/]+)/\{[^}]+\}/actions/([^/\{]+)#';

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (!str_starts_with($uri, $prefix . '/')) {
                continue;
            }

            preg_match($pattern, $uri, $m);

            if (empty($m) || $m[1] !== $type) {
                continue;
            }

            $actionName = $m[2];
            $requestClass = $this->resolveActionRequestClass($route);

            $actions[$actionName] = [
                'uri' => $uri,
                'requestClass' => $requestClass,
                'route' => $route,
            ];
        }

        return $actions;
    }

    /**
     * Reflect the controller method behind a route and return its
     * FormRequest parameter class, if any. Helper for
     * discoverActionRoutes().
     */
    private function resolveActionRequestClass($route): ?string
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

                if ($type instanceof \ReflectionNamedType
                    && is_subclass_of($type->getName(), FormRequest::class)
                    && str_ends_with($type->getName(), 'Request')
                ) {
                    return $type->getName();
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Overlay the DB_ENUMS values onto parsed constraints for fields whose
     * enum lives only in the database. Helper for buildAttributes() and
     * buildJsonSchema().
     */
    private function mergeDbEnums(array $constraints, string $resourceType, string $fieldName): array
    {
        if (isset(self::DB_ENUMS[$resourceType][$fieldName])) {
            $constraints['enum'] = self::DB_ENUMS[$resourceType][$fieldName];
        }

        return $constraints;
    }

    /**
     * Reflect the JSON:API server's registered schema classes (the
     * document's resource list). Helper for generate().
     */
    private function getSchemaClasses(): array
    {
        $method = new \ReflectionMethod($this->server, 'allSchemas');

        return $method->invoke($this->server);
    }

    /**
     * Derive a human-readable display name from the resource type
     * ("assistant-tags" -> "Assistant Tags"). Helper for buildResource().
     */
    private function deriveDisplayName(string $type): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $type));
    }
}
