<?php

namespace App\Services\OpenApi\Builders;

use LaravelJsonApi\Eloquent\Contracts\Filter;
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
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Filters\WhereIn;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Generates example values for OpenAPI request/response bodies and filter parameters.
 *
 * Uses a 4-tier resolution for attribute values:
 *  1. {@see RESOURCE_OVERRIDES} — per-resource field value map for realistic examples.
 *  2. Validation constraints — enum values and numeric range midpoints from ResourceRequest rules.
 *  3. {@see FIELD_DEFAULTS} — common field name → value map (e.g., "url" → "https://example.com").
 *  4. Type-based fallback — derived from the JSON:API field type (Boolean, Number, DateTime, etc.).
 *
 * Relationship examples use the field's inverse type for correct JSON:API type identifiers.
 * Action examples are hardcoded in {@see ACTION_EXAMPLES}.
 */
class ExampleBuilder
{
    /**
     * @param  SchemaBuilder  $schemaBuilder  Provides validation constraint examples from ResourceRequest rules.
     */
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
    ) {}

    /**
     * Common field name → example value mapping.
     * Applied when no resource-specific override exists.
     */
    private const FIELD_DEFAULTS = [
        'name' => 'Test Name',
        'text' => 'Test text',
        'description' => 'A test description.',
        'detail_description' => 'Detailed description here.',
        'url' => 'https://example.com',
        'api_url' => 'https://api.example.com',
        'ping_url' => 'https://api.example.com/ping',
        'label' => 'Test Label',
        'status' => 'active',
        'reason' => 'Looks good',
        'version' => '1.0',
        'server_label' => 'Test Server',
        'provider_id' => 'openai',
        'model_id' => 'gpt-4',
        'handle' => 'test-handle',
        'capability' => 'search',
        'class_name' => 'TestClass',
        'timeout' => '15',
        'discovery_timeout' => '20',
        'require_approval' => 'never',
        'protocolVersion' => '2024-11-05',
        'system_prompt' => 'You are a helpful assistant.',
        'greeting' => 'Hello!',
        'model' => 'gpt-4',
        'active' => true,
        'allow_remix' => true,
        'allow_model_select' => false,
    ];

    /**
     * Per-resource field value overrides for realistic examples.
     * Keys are JSON:API resource types; values map field names to example values.
     */
    private const RESOURCE_OVERRIDES = [
        'assistants' => [
            'name' => 'Test Assistant',
            'system_prompt' => 'You are a helpful assistant.',
            'greeting' => 'Hello!',
            'description' => 'A test assistant.',
            'detail_description' => 'Detailed description here.',
            'allow_remix' => true,
            'allow_model_select' => false,
            'release_stage' => 'private',
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temp' => 0.7,
            'top_p' => 0.9,
        ],
        'ai-models' => [
            'active' => true,
            'model_id' => 'gpt-4',
            'label' => 'GPT-4',
            'input' => ['text', 'image'],
            'output' => ['text'],
            'tools' => ['stream'],
            'default_params' => ['temp' => 0.7],
        ],
        'ai-tools' => [
            'type' => 'function',
            'name' => 'web-search',
            'class_name' => 'WebSearchTool',
            'description' => 'A test tool',
            'capability' => 'search',
            'status' => 'active',
            'active' => true,
        ],
        'mcp-servers' => [
            'url' => 'https://example.com/mcp',
            'server_label' => 'Test Server',
            'version' => '1.0',
            'protocolVersion' => '2024-11-05',
            'description' => 'A test MCP server',
            'require_approval' => 'never',
            'timeout' => '15',
            'discovery_timeout' => '20',
        ],
        'ai-providers' => [
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
            'ping_url' => 'https://api.openai.com/ping',
        ],
        'assistant-categories' => ['text' => 'programming'],
        'assistant-settings' => ['key' => 'formality', 'label' => 'Formality', 'ui_type' => 'select'],
        'assistant-setting-values' => ['value' => 'neutral'],
        'tags' => ['text' => 'php'],
        'user-prompts' => ['text' => 'First prompt'],
        'users' => ['name' => 'John Doe'],
        'organizations' => ['name' => 'Test Org'],
        'versions' => [
            'text' => 'Changed the name',
            'version' => 2.0,
            'changed_keys' => ['name'],
        ],
        'ai-model-statuses' => ['status' => 'online'],
        'assistant-reviews' => [
            'status' => 'pending',
            'reason' => 'Not ready for release',
        ],
    ];

    /**
     * Hardcoded request/response examples for custom actions.
     * Keys are "{resource}.{action}" strings.
     */
    private const ACTION_EXAMPLES = [
        'assistants.chat-test' => [
            'request' => [
                'input' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
                'model' => 'gpt-4',
            ],
            'response' => "event: response.created\ndata: {\"type\":\"response.created\",\"response\":{\"id\":\"resp_abc123\",\"object\":\"response\",\"status\":\"in_progress\",\"model\":\"gpt-4\",\"output\":[],\"usage\":{\"input_tokens\":0,\"output_tokens\":0,\"total_tokens\":0},\"created_at\":1718000000},\"sequence_number\":0}\n\nevent: response.in_progress\ndata: {\"type\":\"response.in_progress\",\"response\":{\"id\":\"resp_abc123\",\"object\":\"response\",\"status\":\"in_progress\",\"model\":\"gpt-4\",\"output\":[],\"usage\":{\"input_tokens\":0,\"output_tokens\":0,\"total_tokens\":0},\"created_at\":1718000000},\"sequence_number\":1}\n\nevent: response.completed\ndata: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_abc123\",\"object\":\"response\",\"status\":\"completed\",\"model\":\"gpt-4\",\"output\":[{\"id\":\"msg_xyz\",\"type\":\"message\",\"role\":\"assistant\",\"content\":[{\"type\":\"output_text\",\"text\":\"Hello!\",\"annotations\":[]}],\"status\":\"completed\"}],\"usage\":{\"input_tokens\":10,\"output_tokens\":5,\"total_tokens\":15},\"created_at\":1718000000},\"sequence_number\":8}\n\n",
        ],
        'assistants.favorite' => [
            'request' => [
                'data' => [
                    'type' => 'assistants',
                    'id' => '1',
                    'attributes' => ['is_favorite' => true],
                ],
            ],
        ],
        'assistants.feedback' => [
            'request' => [
                'data' => [
                    'type' => 'assistants',
                    'attributes' => ['text' => 'Great assistant!'],
                ],
            ],
        ],
        'assistants.release' => [
            'request' => [
                'data' => [
                    'type' => 'assistants',
                    'id' => '1',
                    'attributes' => ['release_stage' => 'organizational'],
                ],
            ],
        ],
        'assistants.remix' => [
            'response' => [
                'data' => ['id' => '2', 'type' => 'assistants'],
            ],
        ],
        'assistants.settings' => [
            'request' => [
                'data' => [
                    'type' => 'assistants',
                    'id' => '1',
                    'attributes' => [
                        'settings' => [
                            ['setting_id' => 1, 'value' => 'professional'],
                            ['setting_id' => 2, 'value' => 'en'],
                            ['setting_id' => 3, 'value' => 'concise'],
                        ],
                    ],
                ],
            ],
        ],
        'assistants.user-prompts' => [
            'request' => [
                'data' => [
                    'type' => 'assistants',
                    'id' => '1',
                    'attributes' => [
                        'add' => ['What is the capital of France?'],
                        'remove' => ['Translate this to Spanish'],
                    ],
                ],
            ],
            'response' => [
                'data' => [
                    'id' => '1',
                    'type' => 'assistants',
                ],
            ],
        ],
    ];

    /**
     * Resolve filter example values from schema metadata and validation rules (no DB access).
     *
     * Resolution chain:
     *  1. Skip non-column filters (WhereHas, custom filters) — return [].
     *  2. Validation constraints — enum values from ResourceRequest rules.
     *  3. RESOURCE_OVERRIDES — per-resource field value map.
     *  4. FIELD_DEFAULTS — common field name → value map.
     *  5. Empty fallback — return [].
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  Filter  $filter  The filter to resolve an example for.
     * @return array Example values, or empty array if none found.
     */
    public function getFilterExamples(Schema $schema, Filter $filter): array
    {
        if (! $filter instanceof Where && ! $filter instanceof WhereIn) {
            return [];
        }

        $key = $filter->key();
        $type = $schema::type();

        $constraintExamples = $this->schemaBuilder->getConstraintExamples($schema);
        if (isset($constraintExamples[$key])) {
            $value = $constraintExamples[$key];
            if (is_array($value)) {
                return $value;
            }

            return [$value];
        }

        if (isset(self::RESOURCE_OVERRIDES[$type][$key])) {
            return [self::RESOURCE_OVERRIDES[$type][$key]];
        }

        if (isset(self::FIELD_DEFAULTS[$key])) {
            return [self::FIELD_DEFAULTS[$key]];
        }

        return [];
    }

    /**
     * Build a complete JSON:API request body example for create or update operations.
     *
     * Includes writable attributes and writable relationships with correct inverse types.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  string  $type  The JSON:API resource type string.
     * @param  bool  $forUpdate  If true, include `data.id` for PATCH requests.
     * @return array|null The request body example, or null if no writable fields exist.
     */
    public function buildRequestExample(Schema $schema, string $type, bool $forUpdate = false): ?array
    {
        $attributes = $this->buildWritableAttributeExamples($schema, $type);
        $relationships = $this->buildWritableRelationshipExamples($schema);

        $data = ['type' => $type];
        if ($forUpdate) {
            $data['id'] = '1';
        }
        if (! empty($attributes)) {
            $data['attributes'] = $attributes;
        }
        if (! empty($relationships)) {
            $data['relationships'] = $relationships;
        }

        return ['data' => $data];
    }

    /**
     * Build a single-resource response example with id, type, and all non-hidden attributes.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  string  $type  The JSON:API resource type string.
     * @return array Response example with `data` containing id, type, and attributes.
     */
    public function buildResponseExample(Schema $schema, string $type): array
    {
        $attributes = $this->buildAllAttributeExamples($schema, $type);

        $data = [
            'id' => '1',
            'type' => $type,
        ];
        if (! empty($attributes)) {
            $data['attributes'] = $attributes;
        }

        return ['data' => $data];
    }

    /**
     * Build a collection response example (array of single-resource examples).
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  string  $type  The JSON:API resource type string.
     * @return array Response example with `data` as an array containing one resource.
     */
    public function buildCollectionResponseExample(Schema $schema, string $type): array
    {
        $single = $this->buildResponseExample($schema, $type);

        return ['data' => [$single['data']]];
    }

    /**
     * Get a hardcoded action request example.
     *
     * @param  string  $resource  The JSON:API resource type.
     * @param  string  $action  The action name.
     * @return array|null The request body example, or null if not defined in ACTION_EXAMPLES.
     */
    public function getActionRequestExample(string $resource, string $action): ?array
    {
        return self::ACTION_EXAMPLES["$resource.$action"]['request'] ?? null;
    }

    /**
     * Get a hardcoded action response example.
     *
     * @param  string  $resource  The JSON:API resource type.
     * @param  string  $action  The action name.
     * @return mixed The response example (array or string for SSE), or null if not defined.
     */
    public function getActionResponseExample(string $resource, string $action): mixed
    {
        return self::ACTION_EXAMPLES["$resource.$action"]['response'] ?? null;
    }

    /**
     * Build example values for writable attribute fields only.
     *
     * Excludes ID, relations, hidden, and read-only fields. Validation constraints
     * are parsed once and passed to the resolution chain so enum values and numeric
     * range midpoints inform the generated examples.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  string  $type  The JSON:API resource type string.
     * @return array Map of field name → example value.
     */
    private function buildWritableAttributeExamples(Schema $schema, string $type): array
    {
        $constraintExamples = $this->schemaBuilder->getConstraintExamples($schema);
        $examples = [];
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
            $value = $this->resolveExampleValue($type, $field->name(), $field, $constraintExamples);
            if ($value !== null) {
                $examples[$field->name()] = $value;
            }
        }

        return $examples;
    }

    /**
     * Build example values for all non-hidden attribute fields (including read-only).
     *
     * Used for response examples where all serialized fields should be present.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @param  string  $type  The JSON:API resource type string.
     * @return array Map of field name → example value.
     */
    private function buildAllAttributeExamples(Schema $schema, string $type): array
    {
        $examples = [];
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
            $value = $this->resolveExampleValue($type, $field->name(), $field);
            if ($value !== null) {
                $examples[$field->name()] = $value;
            }
        }

        return $examples;
    }

    /**
     * Build example values for writable relationship fields.
     *
     * Uses the field's inverse type for correct JSON:API type identifiers in ResourceIdentifier objects.
     *
     * @param  Schema  $schema  The JSON:API schema instance.
     * @return array Map of relationship name → { data: ResourceIdentifier | ResourceIdentifier[] }.
     */
    private function buildWritableRelationshipExamples(Schema $schema): array
    {
        $examples = [];
        foreach ($schema->fields() as $field) {
            if (! $this->isRelation($field)) {
                continue;
            }
            if (method_exists($field, 'isReadOnly') && $field->isReadOnly(null)) {
                continue;
            }
            try {
                $inverseType = $field->inverse();
                $isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);

                $data = $isToMany
                    ? [['type' => $inverseType, 'id' => '1']]
                    : ['type' => $inverseType, 'id' => '1'];

                $examples[$field->name()] = ['data' => $data];
            } catch (\Throwable) {
                continue;
            }
        }

        return $examples;
    }

    /**
     * Resolve an example value for a single field using the 4-tier fallback.
     *
     * Resolution order: RESOURCE_OVERRIDES → validation constraints → FIELD_DEFAULTS → type-based fallback.
     *
     * @param  string  $resource  The JSON:API resource type.
     * @param  string  $fieldName  The field name.
     * @param  mixed  $field  The JSON:API field instance (for type-based fallback).
     * @param  array  $constraintExamples  Validation-derived examples keyed by field name.
     * @return mixed The example value.
     */
    private function resolveExampleValue(string $resource, string $fieldName, $field, array $constraintExamples = []): mixed
    {
        if (isset(self::RESOURCE_OVERRIDES[$resource][$fieldName])) {
            return self::RESOURCE_OVERRIDES[$resource][$fieldName];
        }

        if (isset($constraintExamples[$fieldName])) {
            return $constraintExamples[$fieldName];
        }

        if (isset(self::FIELD_DEFAULTS[$fieldName])) {
            return self::FIELD_DEFAULTS[$fieldName];
        }

        if ($field instanceof Boolean) {
            return false;
        }

        if ($field instanceof Number) {
            return 0;
        }

        if ($field instanceof DateTime) {
            return '2026-06-10T12:00:00.000000Z';
        }

        if ($field instanceof ArrayList) {
            return [];
        }

        if ($field instanceof ArrayHash) {
            return new \stdClass;
        }

        if ($field instanceof Str) {
            return 'string';
        }

        return 'string';
    }

    /**
     * Determine whether a schema field is a relationship type.
     *
     * @param  mixed  $field  A JSON:API schema field instance.
     * @return bool True if the field is BelongsTo, HasOne, HasMany, or BelongsToMany.
     */
    private function isRelation($field): bool
    {
        return $field instanceof BelongsTo
            || $field instanceof HasOne
            || $field instanceof HasMany
            || $field instanceof BelongsToMany;
    }
}
