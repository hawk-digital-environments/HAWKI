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

class ClientSchemaGenerator
{
    /**
     * Database-only enum values not enforced in PHP validation rules.
     * Mirrors SchemaBuilder::DB_ENUMS so the client schema includes them.
     */
    private const DB_ENUMS = [
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
                    ['method' => 'POST',   'path' => '/api/assistants/{id}/actions/favorite'],
                    ['method' => 'DELETE', 'path' => '/api/assistants/{id}/actions/favorite'],
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly Server $server,
        private readonly SchemaBuilder $schemaBuilder,
    ) {}

    public function generate(?Authenticatable $user): array
    {
        $schemaClasses = $this->getSchemaClasses();
        $resources = [];

        foreach ($schemaClasses as $schemaClass) {
            $type = $schemaClass::type();
            $schema = $this->server->schemas()->schemaFor($type);

            $resources[$type] = $this->buildResource($schema, $schemaClass, $user);
        }

        return [
            'version' => '1.0',
            'generatedAt' => now()->toIso8601String(),
            'resources' => $resources,
        ];
    }

    private function buildResource(Schema $schema, string $schemaClass, ?Authenticatable $user): array
    {
        $type = $schema::type();
        $constraints = $this->schemaBuilder->parseValidationConstraints($schema);
        $fieldConstraints = $constraints['constraints'] ?? [];
        $requiredFields = $constraints['required'] ?? [];
        $validatedFields = $constraints['validated'] ?? [];

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

        $resource = [
            'type' => $type,
            'displayName' => $this->deriveDisplayName($type),
        ];

        if ($this->hasApiEndpoint($type)) {
            $resource['endpoints'] = $this->buildEndpoints($type, $schemaClass, $isAuthorizable, $user);
        }

        $resource['attributes'] = $this->buildAttributes($attributeFields, $validatedFields, $actionRoutes, $fieldConstraints, $requiredFields, $type);
        $resource['relationships'] = $this->buildRelationships($relationshipFields, $validatedFields, $actionRoutes, $type);
        $resource['actions'] = $this->buildActions($type, $actionRoutes, $user);
        $resource['filters'] = $this->buildFilters($schema);
        $resource['sortable'] = $this->buildSortable($schema);
        $resource['includable'] = $this->buildIncludable($schema);

        return $resource;
    }

    private function hasApiEndpoint(string $type): bool
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if ($uri === "api/{$type}" || str_starts_with($uri, "api/{$type}/")) {
                return true;
            }
        }

        return false;
    }

    private function buildAttributes(
        array $fields,
        array $validatedFields,
        array $actionRoutes,
        array $fieldConstraints,
        array $requiredFields,
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

            if (in_array($name, $requiredFields, true)) {
                $attr['required'] = true;
            }

            $attr['constraints'] = $this->formatConstraints($mergedConstraints, $openApiType);

            $writableOn = $this->resolveWritableOn($name, $resourceType, $isReadOnly, $validatedFields, context: 'attributes');

            if ($writableOn !== null) {
                $attr['writable_on'] = $writableOn;
            }

            $attributes[$name] = $attr;
        }

        return $attributes;
    }

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

            if ($writableOn !== null) {
                $rel['writable_on'] = $writableOn;
            }

            $rel['endpoints'] = $this->buildRelationshipEndpoints($resourceType, $name);

            $relationships[$name] = $rel;
        }

        return $relationships;
    }

    private function buildActions(string $type, array $actionRoutes, ?Authenticatable $user): array
    {
        $actions = [];

        foreach ($actionRoutes as $actionName => $route) {
            $requestClass = $route['requestClass'];

            $action = [
                'method' => 'POST',
                'url' => "/api/{$type}/{id}/actions/{$actionName}",
                'allowed' => $user !== null,
            ];

            if ($requestClass !== null) {
                $inputSchema = $this->buildActionInputSchema($requestClass);
                if ($inputSchema !== null) {
                    $action['input'] = $inputSchema;
                }
            }

            $actions[$actionName] = $action;
        }

        return $actions;
    }

    private function buildActionInputSchema(string $requestClass): ?array
    {
        try {
            $openApiSchema = $this->schemaBuilder->buildActionRequestSchema($requestClass);
        } catch (\Throwable) {
            return null;
        }

        $dataProps = $openApiSchema['properties']['data']['properties'] ?? [];
        $attrProps = $dataProps['attributes']['properties'] ?? [];

        if (empty($attrProps)) {
            return null;
        }

        return [
            'type' => 'object',
            'properties' => [
                'attributes' => [
                    'type' => 'object',
                    'properties' => $this->simplifyOpenApiProperties($attrProps),
                    'required' => $dataProps['attributes']['required'] ?? [],
                ],
            ],
        ];
    }

    private function simplifyOpenApiProperties(array $properties): array
    {
        $result = [];

        foreach ($properties as $name => $prop) {
            $result[$name] = $this->simplifyOpenApiProperty($prop);
        }

        return $result;
    }

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

        if ($prop['type'] === 'array' && isset($prop['items'])) {
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

        if ($prop['type'] === 'object' && isset($prop['properties'])) {
            $simplified['properties'] = $this->simplifyOpenApiProperties($prop['properties']);
            if (isset($prop['required'])) {
                $simplified['required'] = $prop['required'];
            }
        }

        return $simplified;
    }

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
     * Memoized nested-write routes per resource type, built by scanning
     * registered routes for patterns like POST /assistants/{id}/feedback.
     *
     * @var array<string, array<string, list<array{method:string, path:string}>>>
     */
    private array $nestedWriteCache = [];

    private function resolveWritableOn(
        string $fieldName,
        string $resourceType,
        bool $isReadOnly,
        array $validatedFields,
        string $context = 'attribute',
        bool $isToMany = false,
    ): ?array {
        $writableOn = [];

        // 1. Inline PATCH (standard resource update)
        if (! $isReadOnly && in_array($fieldName, $validatedFields, true)) {
            $writableOn[] = ['method' => 'PATCH', 'path' => "/api/{$resourceType}/{id}"];
        }

        // 2. Relationship endpoint writes (only for to-many; to-one gets PATCH only)
        if ($context === 'relationships' && ! $isReadOnly) {
            $uriName = str_replace('_', '-', $fieldName);
            if ($isToMany) {
                $writableOn[] = ['method' => 'POST', 'path' => "/api/{$resourceType}/{id}/relationships/{$uriName}"];
                $writableOn[] = ['method' => 'DELETE', 'path' => "/api/{$resourceType}/{id}/relationships/{$uriName}"];
            }
            $writableOn[] = ['method' => 'PATCH', 'path' => "/api/{$resourceType}/{id}/relationships/{$uriName}"];
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
     *   POST  /api/{resource}/{id}/{relation}              -> nested create
     *   PATCH /api/{resource}/{id}/{relation}              -> nested update
     *   DELETE /api/{resource}/{id}/{relation}/{childId}   -> nested delete
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

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, "api/{$resourceType}/")) {
                continue;
            }

            $path = preg_replace('#^api/#', '', $uri);
            // Normalize all route params to {id} for client-facing URLs.
            $normalizedPath = preg_replace('#\{[^}]+\}#', '{id}', $path);

            // POST|PATCH /{resource}/{id}/{relation}
            if (preg_match('#^'.preg_quote($resourceType, '#').'/\{[^}]+\}/([^/\{]+)$#', $path, $m)) {
                foreach ($route->methods() as $method) {
                    if ($method === 'POST' || $method === 'PATCH') {
                        $fieldName = str_replace('-', '_', $m[1]);
                        $result[$fieldName][] = [
                            'method' => $method,
                            'path' => '/api/'.$normalizedPath,
                        ];
                    }
                }
            }

            // DELETE /{resource}/{id}/{relation}/{childId}
            if (preg_match('#^'.preg_quote($resourceType, '#').'/\{[^}]+\}/([^/]+)/\{[^}]+\}$#', $path, $m)) {
                foreach ($route->methods() as $method) {
                    if ($method === 'DELETE') {
                        $fieldName = str_replace('-', '_', $m[1]);
                        $result[$fieldName][] = [
                            'method' => $method,
                            'path' => '/api/'.$normalizedPath,
                        ];
                    }
                }
            }
        }

        $this->nestedWriteCache[$resourceType] = $result;

        return $result;
    }

    private function buildEndpoints(string $type, string $schemaClass, bool $isAuthorizable, ?Authenticatable $user): array
    {
        $auth = $user !== null;

        $modelClass = $schemaClass::$model ?? null;

        $createAllowed = $auth;
        $listAllowed = true;

        if ($isAuthorizable && $auth && $modelClass !== null) {
            $createAllowed = Gate::forUser($user)->allows('create', $modelClass);
            $listAllowed = Gate::forUser($user)->allows('viewAny', $modelClass);
        }

        return [
            'list' => ['method' => 'GET', 'url' => "/api/{$type}", 'allowed' => $listAllowed],
            'create' => ['method' => 'POST', 'url' => "/api/{$type}", 'allowed' => $createAllowed],
            'read' => ['method' => 'GET', 'url' => "/api/{$type}/{id}", 'allowed' => $auth],
            'update' => ['method' => 'PATCH', 'url' => "/api/{$type}/{id}", 'allowed' => $auth],
            'delete' => ['method' => 'DELETE', 'url' => "/api/{$type}/{id}", 'allowed' => $auth],
        ];
    }

    private function buildRelationshipEndpoints(string $type, string $relationName): array
    {
        return [
            'fetch' => [
                'method' => 'GET',
                'url' => "/api/{$type}/{id}/{$relationName}",
            ],
        ];
    }

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

    private function buildSortable(Schema $schema): array
    {
        $sortable = [];
        foreach ($schema->fields() as $field) {
            if (method_exists($field, 'isSortable') && $field->isSortable()) {
                $sortable[] = $field->name();
            }
        }

        return $sortable;
    }

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

    private function formatConstraints(array $rawConstraints, array $openApiType): array
    {
        $formatted = [];

        if (isset($rawConstraints['enum'])) {
            $formatted['values'] = $rawConstraints['enum'];
        }
        if (isset($rawConstraints['type']) && $rawConstraints['type'] === 'integer') {
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

    private function discoverActionRoutes(string $type): array
    {
        $actions = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            preg_match('#^api/([^/]+)/\{[^}]+\}/actions/([^/\{]+)#', $uri, $m);
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

    private function resolveActionRequestClass($route): ?string
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

    private function mergeDbEnums(array $constraints, string $resourceType, string $fieldName): array
    {
        if (isset(self::DB_ENUMS[$resourceType][$fieldName])) {
            $constraints['enum'] = self::DB_ENUMS[$resourceType][$fieldName];
        }

        return $constraints;
    }

    private function getSchemaClasses(): array
    {
        $method = new \ReflectionMethod($this->server, 'allSchemas');

        return $method->invoke($this->server);
    }

    private function deriveDisplayName(string $type): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $type));
    }
}
