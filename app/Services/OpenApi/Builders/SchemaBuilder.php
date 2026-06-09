<?php

namespace App\Services\OpenApi\Builders;

use Illuminate\Validation\Rules\Enum as EnumRule;
use LaravelJsonApi\Contracts\Auth\Authorizer;
use LaravelJsonApi\Contracts\Routing\Route as RouteContract;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Contracts\Schema\Schema as SchemaContract;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

/**
 * Maps JSON:API Schema field definitions to OpenAPI property schemas and parses
 * Laravel FormRequest validation rules into OpenAPI constraints.
 *
 * This builder is responsible for:
 *  - Converting field types (Str, Number, Boolean, DateTime, ArrayList, ArrayHash) to OpenAPI types
 *  - Building attribute schemas (full and writable-only variants)
 *  - Building relationship schemas with correct toOne/toMany cardinality
 *  - Parsing validation rules (min, max, enum, required) into OpenAPI constraints
 *  - Applying database-only enum values not present in PHP validation
 */
class SchemaBuilder
{
    /**
     * Database-only enum values that are not enforced in PHP validation rules.
     * Keys are JSON:API resource types; values map field names to allowed values.
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
     * Build an OpenAPI object schema for a resource's attributes.
     *
     * Iterates all fields on the schema, excluding ID, relations, hidden fields,
     * and (when writable-only) read-only fields. For writable schemas, validation
     * constraints and required fields are parsed from the matching ResourceRequest class.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  bool  $writableOnly  If true, exclude read-only fields and merge validation constraints.
     * @return array OpenAPI object schema with `properties` and optionally `required`.
     */
    public function buildAttributes(Schema $schema, bool $writableOnly = false): array
    {
        $properties = [];
        $required = [];
        foreach ($schema->fields() as $field) {
            if ($field instanceof ID) {
                continue;
            }
            if ($this->isRelation($field)) {
                continue;
            }
            if (method_exists($field, 'isHidden') && $field->isHidden(null)) {
                continue;
            }
            if ($writableOnly && method_exists($field, 'isReadOnly') && $field->isReadOnly(null)) {
                continue;
            }
            $properties[$field->name()] = $this->mapFieldType($field);
            if (! $writableOnly) {
                $required[] = $field->name();
            }
        }

        if ($writableOnly) {
            ['constraints' => $constraints, 'required' => $required] = $this->parseValidationConstraints($schema);
            foreach ($constraints as $field => $fieldConstraints) {
                if (isset($properties[$field])) {
                    $properties[$field] = array_merge($properties[$field], $fieldConstraints);
                }
            }
        }

        $type = $schema::type();
        if (isset(self::DB_ENUMS[$type])) {
            foreach (self::DB_ENUMS[$type] as $field => $values) {
                if (isset($properties[$field])) {
                    $properties[$field]['enum'] = $values;
                    $properties[$field]['example'] = $values[0];
                }
            }
        }

        $result = [
            'type' => 'object',
            'properties' => empty($properties) ? new \stdClass : $properties,
        ];
        if (! empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Build an OpenAPI object schema for a resource's relationships.
     *
     * Each relationship is represented as an object with `links` (RelationshipLinks ref)
     * and `data` (ResourceIdentifier ref or array of refs for toMany relations).
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  bool  $writableOnly  If true, exclude read-only relationships.
     * @return array OpenAPI object schema, or empty array if no relationships exist.
     */
    public function buildRelationships(Schema $schema, bool $writableOnly = false): array
    {
        $properties = [];
        foreach ($schema->fields() as $field) {
            if (! $this->isRelation($field)) {
                continue;
            }
            if ($writableOnly && method_exists($field, 'isReadOnly') && $field->isReadOnly(null)) {
                continue;
            }
            $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);
            $properties[$field->name()] = $this->buildRelationProperty($isToMany);
        }

        if (empty($properties)) {
            return [];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Build a JSON:API resource object schema (type + id + attributes + optional relationships).
     *
     * @param  string  $type  The JSON:API resource type string (e.g., "assistants").
     * @param  array  $attrRef  A `$ref` array pointing to the attributes schema.
     * @param  array  $relRef  A `$ref` array pointing to the relationships schema, or empty.
     * @return array OpenAPI object schema representing a JSON:API resource object.
     */
    public function buildResourceObject(string $type, array $attrRef, array $relRef): array
    {
        $props = [
            'type' => ['type' => 'string', 'example' => $type],
            'id' => ['type' => 'string'],
            'attributes' => $attrRef,
        ];
        if (! empty($relRef)) {
            $props['relationships'] = $relRef;
        }

        return [
            'type' => 'object',
            'properties' => $props,
            'required' => ['type', 'id'],
        ];
    }

    /**
     * Build an OpenAPI schema for a JSON:API create request body.
     *
     * The `data.type` field is constrained to the resource type via `enum`.
     * Writable attributes and relationships are included; read-only fields are excluded.
     *
     * @param  string  $type  The JSON:API resource type string.
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array OpenAPI schema for a POST request body.
     */
    public function buildCreateRequestSchema(string $type, Schema $schema): array
    {
        $dataProps = [
            'type' => ['type' => 'string', 'enum' => [$type]],
        ];

        $writableAttrs = $this->buildWritableAttributes($schema);
        if ($this->hasProperties($writableAttrs)) {
            $dataProps['attributes'] = $writableAttrs;
        }

        $writableRels = $this->buildRelationships($schema, writableOnly: true);
        if (! empty($writableRels)) {
            $dataProps['relationships'] = $writableRels;
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'required' => ['type'],
                    'properties' => $dataProps,
                ],
            ],
        ];
    }

    /**
     * Build an OpenAPI schema for a JSON:API update (PATCH) request body.
     *
     * Similar to create but includes `data.id` as a required field.
     *
     * @param  string  $type  The JSON:API resource type string.
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array OpenAPI schema for a PATCH request body.
     */
    public function buildUpdateRequestSchema(string $type, Schema $schema): array
    {
        $dataProps = [
            'type' => ['type' => 'string', 'enum' => [$type]],
            'id' => ['type' => 'string'],
        ];

        $writableAttrs = $this->buildWritableAttributes($schema);
        if ($this->hasProperties($writableAttrs)) {
            $dataProps['attributes'] = $writableAttrs;
        }

        $writableRels = $this->buildRelationships($schema, writableOnly: true);
        if (! empty($writableRels)) {
            $dataProps['relationships'] = $writableRels;
        }

        return [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'required' => ['type', 'id'],
                    'properties' => $dataProps,
                ],
            ],
        ];
    }

    /**
     * Build an OpenAPI schema from a custom action FormRequest's validation rules.
     *
     * Parses the rules array recursively to handle nested fields and array wildcards,
     * producing a structured OpenAPI object schema.
     *
     * @param  string  $requestClass  FQCN of the FormRequest class (e.g., ChatTestRequest).
     * @return array OpenAPI object schema derived from the request's validation rules.
     */
    public function buildActionRequestSchema(string $requestClass): array
    {
        $request = new $requestClass;
        $rules = $request->rules();

        return $this->parseRulesToSchema($rules);
    }

    /**
     * Convert a JSON:API resource type string to a PascalCase class name.
     *
     * Example: "ai-model-statuses" → "AiModelStatuses"
     *
     * @param  string  $type  The JSON:API resource type.
     * @return string The PascalCase class name component.
     */
    public function typeToClassName(string $type): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $type)));
    }

    /**
     * Extract validation-derived example values for all writable fields of a schema.
     *
     * Returns a map of field name → example value sourced from enum case values,
     * numeric range midpoints, or constraint-derived defaults. Fields without
     * constraint-derived examples are omitted from the returned map.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array<string, mixed> Map of field name → constraint-derived example value.
     */
    public function getConstraintExamples(Schema $schema): array
    {
        ['constraints' => $constraints] = $this->parseValidationConstraints($schema);

        $examples = [];
        foreach ($constraints as $field => $fieldConstraints) {
            if (isset($fieldConstraints['example'])) {
                $examples[$field] = $fieldConstraints['example'];
            }
        }

        $type = $schema::type();
        if (isset(self::DB_ENUMS[$type])) {
            foreach (self::DB_ENUMS[$type] as $field => $values) {
                if (isset($values[0])) {
                    $examples[$field] = $values[0];
                }
            }
        }

        return $examples;
    }

    /**
     * Build writable-only attributes with validation constraints and required fields.
     *
     * Unlike {@see buildAttributes()}, this excludes read-only fields and merges
     * constraints (min, max, enum, etc.) from the matching ResourceRequest class.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array OpenAPI object schema with writable properties, constraints, and required fields.
     */
    public function buildWritableAttributes(Schema $schema): array
    {
        $properties = [];
        foreach ($schema->fields() as $field) {
            if ($field instanceof ID) {
                continue;
            }
            if ($this->isRelation($field)) {
                continue;
            }
            if (method_exists($field, 'isHidden') && $field->isHidden(null)) {
                continue;
            }
            if (method_exists($field, 'isReadOnly') && $field->isReadOnly(null)) {
                continue;
            }
            $properties[$field->name()] = $this->mapFieldType($field);
        }

        ['constraints' => $constraints, 'required' => $required] = $this->parseValidationConstraints($schema);
        foreach ($constraints as $field => $fieldConstraints) {
            if (isset($properties[$field])) {
                $properties[$field] = array_merge($properties[$field], $fieldConstraints);
            }
        }

        $result = [
            'type' => 'object',
            'properties' => empty($properties) ? new \stdClass : $properties,
        ];
        if (! empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    /**
     * Resolve the ResourceRequest class for a given schema by naming convention.
     *
     * Looks for a class named `{ResourceName}Request` in the same namespace as the schema.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return string|null The Request class FQCN, or null if not found.
     */
    private function resolveRequestClass(Schema $schema): ?string
    {
        $schemaClass = $schema::class;
        $namespace = dirname(str_replace('\\', '/', $schemaClass));
        $baseName = (new \ReflectionClass($schema))->getShortName();

        if (! str_ends_with($baseName, 'Schema')) {
            return null;
        }

        $requestName = substr($baseName, 0, -6).'Request';
        $requestClass = str_replace('/', '\\', $namespace).'\\'.$requestName;

        if (class_exists($requestClass) && is_subclass_of($requestClass, ResourceRequest::class)) {
            return $requestClass;
        }

        return null;
    }

    /**
     * Parse validation rules from the schema's matching ResourceRequest class.
     *
     * Binds a mock {@see RouteContract} to the container so that JsonApiRule
     * methods (toOne, toMany) can resolve the schema. Extracts OpenAPI constraints
     * and required field names from the rules array.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array{constraints: array<string, array>, required: array<string>} Parsed constraints and required field names.
     */
    private function parseValidationConstraints(Schema $schema): array
    {
        $requestClass = $this->resolveRequestClass($schema);
        if ($requestClass === null) {
            return ['constraints' => [], 'required' => []];
        }

        try {
            $this->bindMockRoute($schema);
            $request = new $requestClass;
            $rules = $request->rules();
        } catch (\Throwable) {
            return ['constraints' => [], 'required' => []];
        }

        $constraints = [];
        $required = [];
        $schemaFields = [];
        foreach ($schema->fields() as $field) {
            if ($field instanceof ID) {
                continue;
            }
            if (! $this->isRelation($field)) {
                $schemaFields[$field->name()] = true;
            }
        }

        foreach ($rules as $field => $fieldRules) {
            if (! isset($schemaFields[$field])) {
                continue;
            }

            $fieldRules = (array) $fieldRules;
            if (in_array('required', $fieldRules)) {
                $required[] = $field;
            }
            $parsed = $this->extractConstraints($fieldRules);
            if (! empty($parsed)) {
                $constraints[$field] = $parsed;
            }
        }

        return ['constraints' => $constraints, 'required' => $required];
    }

    /**
     * Extract OpenAPI constraints from an array of Laravel validation rules.
     *
     * Handles: integer, numeric, min, max, maxLength, and {@see EnumRule}.
     * Derives example values from numeric ranges when present.
     *
     * @param  array  $rules  Array of Laravel validation rule strings and objects.
     * @return array OpenAPI constraints (type, minimum, maximum, maxLength, enum, example).
     */
    private function extractConstraints(array $rules): array
    {
        $constraints = [];
        $isInteger = false;
        $isNumeric = false;

        foreach ($rules as $rule) {
            if ($rule === 'integer') {
                $constraints['type'] = 'integer';
                $isInteger = true;
            } elseif ($rule === 'numeric') {
                $isNumeric = true;
            } elseif (is_string($rule)) {
                if (str_starts_with($rule, 'min:')) {
                    $value = $isInteger ? (int) substr($rule, 4) : (float) substr($rule, 4);
                    $constraints['minimum'] = $value;
                } elseif (str_starts_with($rule, 'max:')) {
                    if ($isNumeric || $isInteger) {
                        $constraints['maximum'] = $isInteger ? (int) substr($rule, 4) : (float) substr($rule, 4);
                    } else {
                        $constraints['maxLength'] = (int) substr($rule, 4);
                    }
                }
            } elseif ($rule instanceof EnumRule) {
                $enumClass = $this->getEnumClass($rule);
                if ($enumClass !== null && enum_exists($enumClass)) {
                    $values = array_map(fn ($case) => $case->value, $enumClass::cases());
                    $constraints['enum'] = $values;
                    $constraints['example'] = $values[0] ?? null;
                }
            }
        }

        if (isset($constraints['minimum']) || isset($constraints['maximum'])) {
            $constraints['example'] = $this->deriveExample($constraints, $isInteger);
        }

        return array_filter($constraints, fn ($v) => $v !== null);
    }

    /**
     * Derive an example numeric value from constraint bounds.
     *
     * @param  array  $constraints  Extracted constraints with optional `minimum` and `maximum`.
     * @param  bool  $isInteger  Whether the field is an integer type.
     * @return int|float A representative example value within the constraint range.
     */
    private function deriveExample(array $constraints, bool $isInteger): int|float
    {
        $min = $constraints['minimum'] ?? 0;
        $max = $constraints['maximum'] ?? null;

        if ($max !== null) {
            $mid = ($min + $max) / 2;

            return $isInteger ? (int) round($mid) : round($mid, 1);
        }

        if ($min > 0) {
            return $isInteger ? (int) $min : round($min + 0.1, 1);
        }

        return $isInteger ? 1 : 0.5;
    }

    /**
     * Extract the backing enum class name from a Laravel {@see EnumRule} instance via reflection.
     *
     * @param  EnumRule  $rule  The Enum validation rule.
     * @return string|null The enum class FQCN, or null if reflection fails.
     */
    private function getEnumClass(EnumRule $rule): ?string
    {
        try {
            $ref = new \ReflectionProperty($rule, 'type');

            return $ref->getValue($rule);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Bind a mock {@see RouteContract} into the container for JsonApiRule resolution.
     *
     * Many ResourceRequest::rules() methods call JsonApiRule::toOne() or toMany(),
     * which internally resolve the current route to get the schema. Outside an HTTP
     * request context, this fails. The mock route returns the schema instance from
     * its {@see RouteContract::schema()} method.
     *
     * Only binds if not already bound to avoid conflicts in test environments.
     *
     * @param  Schema  $schema  The JSON:API schema instance to return from the mock route.
     */
    private function bindMockRoute(Schema $schema): void
    {
        if (app()->bound(RouteContract::class)) {
            return;
        }

        $mockRoute = new class($schema) implements RouteContract
        {
            private SchemaContract $schema;

            public function __construct(SchemaContract $schema)
            {
                $this->schema = $schema;
            }

            public function resourceType(): string
            {
                return $this->schema::type();
            }

            public function modelOrResourceId(): mixed
            {
                return null;
            }

            public function hasResourceId(): bool
            {
                return false;
            }

            public function resourceId(): string
            {
                return '';
            }

            public function model(): object
            {
                return new \stdClass;
            }

            public function fieldName(): string
            {
                return '';
            }

            public function schema(): SchemaContract
            {
                return $this->schema;
            }

            public function authorizer(): Authorizer
            {
                throw new \RuntimeException('not implemented');
            }

            public function hasRelation(): bool
            {
                return false;
            }

            public function inverse(): SchemaContract
            {
                throw new \RuntimeException('not implemented');
            }

            public function relation(): Relation
            {
                throw new \RuntimeException('not implemented');
            }

            public function substituteBindings(): void {}
        };

        app()->instance(RouteContract::class, $mockRoute);
    }

    /**
     * Check whether a schema has any defined properties.
     *
     * @param  array  $schema  An OpenAPI schema array.
     * @return bool True if the schema has at least one property.
     */
    private function hasProperties(array $schema): bool
    {
        $props = $schema['properties'] ?? [];

        return ! empty((array) $props);
    }

    /**
     * Recursively parse a flat Laravel rules array into a nested OpenAPI object schema.
     *
     * Handles dot-notation keys (e.g., "data.attributes.name") and array wildcards ("data.*.field").
     *
     * @param  array  $rules  Laravel validation rules keyed by field name (dot-notation).
     * @return array OpenAPI object schema.
     */
    private function parseRulesToSchema(array $rules): array
    {
        $schema = ['type' => 'object', 'properties' => []];

        foreach ($rules as $key => $fieldRules) {
            $fieldRules = (array) $fieldRules;
            $parts = explode('.', $key);
            $this->applyRulesToSchema($schema, $parts, $fieldRules);
        }

        if (empty($schema['properties'])) {
            $schema['properties'] = new \stdClass;
        }

        return $schema;
    }

    /**
     * Recursively apply validation rules to a nested schema structure.
     *
     * Walks the parts array (from exploding dot-notation keys) and creates
     * intermediate object/array schemas as needed.
     *
     * @param  array  $schema  Reference to the schema array being built.
     * @param  array  $parts  Remaining key parts from dot-notation splitting.
     * @param  array  $rules  Laravel validation rules for this field.
     */
    private function applyRulesToSchema(array &$schema, array $parts, array $rules): void
    {
        $part = array_shift($parts);

        if ($part === '*') {
            if (! isset($schema['items']) || ! is_array($schema['items'])) {
                $schema['items'] = ['type' => 'object', 'properties' => []];
            }
            if (! empty($parts)) {
                $this->applyRulesToSchema($schema['items'], $parts, $rules);
            }

            return;
        }

        if (empty($parts)) {
            $schema['properties'][$part] = $this->inferType($rules);
            if (in_array('required', $rules)) {
                $schema['required'][] = $part;
            }

            return;
        }

        if (! isset($schema['properties'][$part])) {
            $nextPart = $parts[0] ?? null;
            if ($nextPart === '*') {
                $schema['properties'][$part] = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => []]];
            } else {
                $schema['properties'][$part] = ['type' => 'object', 'properties' => []];
            }
        } else {
            $existing = $schema['properties'][$part];
            $nextPart = $parts[0] ?? null;
            if ($nextPart === '*' && ! isset($existing['items'])) {
                $schema['properties'][$part]['type'] = 'array';
                $schema['properties'][$part]['items'] = ['type' => 'object', 'properties' => []];
            } elseif ($nextPart !== '*' && ! isset($existing['properties'])) {
                $schema['properties'][$part]['type'] = 'object';
                $schema['properties'][$part]['properties'] = [];
            }
        }

        $this->applyRulesToSchema($schema['properties'][$part], $parts, $rules);
    }

    /**
     * Infer an OpenAPI property schema from an array of Laravel validation rules.
     *
     * Maps rule strings (integer, numeric, boolean, array, in:...) to OpenAPI types and enums.
     *
     * @param  array  $rules  Laravel validation rules for a single field.
     * @return array OpenAPI property schema with at minimum a `type` key.
     */
    private function inferType(array $rules): array
    {
        $type = 'string';
        $result = [];

        foreach ($rules as $rule) {
            $ruleStr = is_string($rule) ? $rule : '';

            if ($ruleStr === 'integer') {
                $type = 'integer';
            } elseif ($ruleStr === 'numeric') {
                $type = 'number';
            } elseif ($ruleStr === 'boolean') {
                $type = 'boolean';
            } elseif ($ruleStr === 'array') {
                $type = 'array';
            } elseif (str_starts_with($ruleStr, 'in:')) {
                $result['enum'] = explode(',', substr($ruleStr, 3));
            }
        }

        $result['type'] = $type;

        return $result;
    }

    /**
     * Determine whether a schema field is a relationship (BelongsTo, HasOne, HasMany, or BelongsToMany).
     *
     * @param  mixed  $field  A JSON:API schema field instance.
     * @return bool True if the field is a relationship type.
     */
    private function isRelation($field): bool
    {
        return $field instanceof BelongsTo
            || $field instanceof HasOne
            || $field instanceof HasMany
            || $field instanceof BelongsToMany;
    }

    /**
     * Map a JSON:API field instance to an OpenAPI property schema.
     *
     * @param  mixed  $field  A JSON:API schema field (Str, Number, Boolean, DateTime, ArrayList, ArrayHash, ID).
     * @return array OpenAPI property schema.
     */
    private function mapFieldType($field): array
    {
        return match (true) {
            $field instanceof ID => ['type' => 'string'],
            $field instanceof Str => ['type' => 'string'],
            $field instanceof Number => ['type' => 'number'],
            $field instanceof Boolean => ['type' => 'boolean'],
            $field instanceof DateTime => ['type' => 'string', 'format' => 'date-time'],
            $field instanceof ArrayList => ['type' => 'array', 'items' => new \stdClass],
            $field instanceof ArrayHash => ['type' => 'object', 'additionalProperties' => true],
            default => ['type' => 'string'],
        };
    }

    /**
     * Build a relationship property schema with links and data (ResourceIdentifier ref or array).
     *
     * @param  bool  $isToMany  Whether the relationship is to-many (array) or to-one (single).
     * @return array OpenAPI object schema for a JSON:API relationship.
     */
    private function buildRelationProperty(bool $isToMany): array
    {
        $data = $isToMany
            ? ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ResourceIdentifier']]
            : ['$ref' => '#/components/schemas/ResourceIdentifier'];

        return [
            'type' => 'object',
            'properties' => [
                'links' => ['$ref' => '#/components/schemas/RelationshipLinks'],
                'data' => $data,
            ],
        ];
    }
}
