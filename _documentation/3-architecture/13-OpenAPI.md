# OpenAPI Specification Generator

## Overview

The OpenAPI generator produces a static OpenAPI 3.0.3 specification in JSON format from the application's Laravel JSON:API v1 schemas, routes, and validation rules.

**Run the generator locally:**

```bash
php artisan openapi:generate
```

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--output` | auto | Custom output file path |
| `--no-examples` | off | Skip database queries for filter examples |
| `--docs-dir` | `public/docs` | Output directory |

**Output file:**

- `public/docs/openapi.json`

This is served directly by nginx as a static file under `/docs/openapi.json`. No PHP controller or storage symlink is involved.

The `public/docs/` directory is gitignored — the spec is generated at Docker build time or on demand.

**In production**, the spec is generated during the Docker build process (see Build Pipeline below) using `--no-examples`. In development, run `php artisan openapi:generate` manually to get examples with database filter values.

---

## Architecture

Four core files under `app/Services/OpenApi/`:

| File | Responsibility |
|------|---------------|
| `OpenApiGenerator.php` | Main orchestrator. Instantiates the JSON:API server, iterates schemas, builds shared components (parameters, responses, SSE schemas), assembles the final spec array, serializes to JSON. |
| `Builders/SchemaBuilder.php` | Maps JSON:API Schema field definitions to OpenAPI types. Parses FormRequest validation rules for constraints and required fields. Builds attribute, relationship, and request schemas. |
| `Builders/PathBuilder.php` | Scans registered API routes, classifies each into one of 5 endpoint types, and builds path operations with parameters, request bodies, and response schemas. |
| `Builders/ExampleBuilder.php` | Generates example values for request/response bodies and filter parameters. Uses a 4-tier resolution: resource-specific overrides, validation constraints, common field defaults, type-based fallbacks. |

---

## Generation Pipeline

The generator runs through these steps in order:

### 1. Server Instantiation

```php
$server = new Server(new AppResolver(fn () => app()), 'v1');
app()->instance(\LaravelJsonApi\Core\Server\Server::class, $server);
```

The JSON:API Server is instantiated manually (not via HTTP request). It is bound into the Laravel container so that internal lookups (e.g., schema resolution) work correctly.

### 2. Schema Discovery

`allSchemas()` is a protected method on the Server class. It is accessed via reflection:

```php
$method = new \ReflectionMethod($server, 'allSchemas');
$schemaClasses = $method->invoke($server);
```

This returns all 14 schema classes registered in `app/JsonApi/V1/Server.php`.

### 3. Per-Resource Schema Building

For each schema class:

1. Instantiate the schema: `new $schemaClass($server)`
2. Get the JSON:API type: `$schemaClass::type()` (static method, no request context needed)
3. Convert type to class name: `typeToClassName()` — e.g., `ai-model-statuses` → `AiModelStatuses`
4. Build **attributes** (all non-hidden, non-relationship fields)
5. Build **relationships** (BelongsTo, HasOne, HasMany, BelongsToMany)
6. Build **resource object** (type + id + attributes ref + relationships ref)
7. Build **single response** and **collection response** schemas
8. Build **create/update request** schemas (writable-only attributes + validation constraints)
9. Store metadata in `$schemaMap` for use by PathBuilder and ExampleBuilder

### 4. Route Scanning

`PathBuilder::getApiRoutes()` collects all routes starting with `api/`, excluding:
- `api/user` — internal user info endpoint
- `api/ai-req` — legacy AI request endpoint
- `api/docs` — removed docs controller routes

Each route URI is parsed into one of 5 endpoint types via regex:

| Pattern | Type | Example |
|---------|------|---------|
| `{resource}/{id}/actions/{action}` | `action` | `assistants/{id}/actions/chat-test` |
| `{resource}/{id}/relationships/{relation}` | `relationship` | `assistants/{id}/relationships/category` |
| `{resource}/{id}/{relation}` | `related` | `assistants/{id}/category` |
| `{resource}/{id}` | `resource` | `assistants/{id}` |
| `{resource}` | `collection` | `assistants` |

Route parameters like `{assistant}` are normalized to `{id}` in the OpenAPI path.

### 5. Action Request Discovery

For action endpoints (`/actions/*`), the generator uses reflection on the controller method to find typed FormRequest parameters:

```php
$reflection = new \ReflectionMethod($controller, $method);
foreach ($reflection->getParameters() as $param) {
    $type = $param->getType();
    if ($type instanceof \ReflectionNamedType
        && is_subclass_of($type->getName(), FormRequest::class)) {
        return $type->getName(); // e.g., ChatTestRequest
    }
}
```

The request class's `rules()` method is then parsed into an OpenAPI schema via `parseRulesToSchema()`.

### 6. Shared Components

After per-resource schemas and paths are built, the generator adds:

- **`IncludedResource`** — `oneOf` with all 14 `{ClassName}Resource` schemas
- **Shared parameters** — `PageSize`, `PageNumber`, `Sort`, `Include` (used via `$ref`)
- **Shared responses** — `Unauthorized` (401), `Forbidden` (403), `NotFound` (404), `UnprocessableEntity` (422)
- **SSE event schemas** — OpenAI Responses API compatible component schemas for the chat-test streaming endpoint

### 7. Serialization

`json_encode` with `JSON_PRETTY_PRINT` produces the JSON output, written to `public/docs/openapi.json`.

---

## Build Pipeline (Docker)

The OpenAPI spec is generated during the Docker build process, not at runtime. This avoids requiring `symfony/yaml` (a dev dependency) in the production image.

### Dockerfile Stages

1. **`openapi_builder`** — A build stage based on `neunerlei/php-nginx:8.5` that:
   - Installs composer dependencies **with** dev packages (includes `symfony/yaml`)
   - Copies the application source
   - Creates storage directories needed by the generator
   - Runs `php artisan openapi:generate --no-examples` → produces `public/docs/openapi.json`

2. **`app_prod`** — The production image that:
   - Installs composer dependencies **without** dev packages
   - Copies the generated spec from the builder: `COPY --from=openapi_builder ... /var/www/html/public/docs`

This is the same pattern used for frontend assets (`node_builder` → `public/build`).

### Accessing the Spec

| Environment | URL | How |
|-------------|-----|-----|
| Production | `https://{host}/docs/openapi.json` | Generated at build time, served by nginx from `public/docs/` |
| Development | `https://{host}/docs/openapi.json` | Run `php artisan openapi:generate` manually; served from `public/docs/` |

---

## How Schemas Drive Output

Each JSON:API Schema class (e.g., `App\JsonApi\V1\Assistants\AssistantSchema`) defines fields in its `fields()` method. The generator reads these fields to produce OpenAPI schemas.

### Field Type Mapping

| JSON:API Field | OpenAPI Type | Notes |
|---------------|--------------|-------|
| `ID` | excluded from attributes | Present in resource object as `data.id` |
| `Str` | `{ type: "string" }` | |
| `Number` | `{ type: "number" }` | |
| `Boolean` | `{ type: "boolean" }` | |
| `DateTime` | `{ type: "string", format: "date-time" }` | |
| `ArrayList` | `{ type: "array", items: {} }` | |
| `ArrayHash` | `{ type: "object", additionalProperties: true }` | |

### Relationship Type Mapping

| JSON:API Field | Cardinality | OpenAPI Data Schema |
|---------------|-------------|-------------------|
| `BelongsTo` | toOne | `$ref: ResourceIdentifier` |
| `HasOne` | toOne | `$ref: ResourceIdentifier` |
| `HasMany` | toMany | `array` of `ResourceIdentifier` |
| `BelongsToMany` | toMany | `array` of `ResourceIdentifier` |

### Field Visibility

Two methods control whether a field appears in schemas:

- **`isHidden(null)`** — If true, the field is excluded from **all** schemas (both response and request). Used for fields that should never be serialized (e.g., internal flags).
- **`isReadOnly(null)`** — If true, the field is excluded from **writable** schemas (create/update requests) but present in response schemas. Used for computed or server-managed fields like `created_at`, `updated_at`.

Both methods accept `?Request` (null is valid) — the generator always passes `null`.

> **Important:** The `null` parameter is required. These methods have the signature `isHidden(?Request $request): bool`. Passing `null` means "check the default visibility."

### Generated Schema Components Per Resource

For a resource with type `assistants` and class name `Assistant`, the generator creates:

| Schema Name | Purpose |
|-------------|---------|
| `AssistantAttributes` | All non-hidden, non-relationship fields (response view) |
| `AssistantRelationships` | All relationship fields |
| `AssistantResource` | Full resource object: type + id + attributes + relationships |
| `AssistantSingleResponse` | `{ data: AssistantResource, included: [...], links, meta }` |
| `AssistantCollectionResponse` | `{ data: [AssistantResource], included: [...], links, meta }` |
| `AssistantCreateRequest` | Writable attributes + writable relationships (with validation constraints) |
| `AssistantUpdateRequest` | Same as create but includes `id` in data |

If a resource has no writable attributes (e.g., all fields are read-only), the create/update request schemas are omitted.

---

## Validation Constraints

The generator reads validation rules from `ResourceRequest` classes (e.g., `AssistantRequest`) and merges constraints into writable attribute schemas.

### How It Works

1. The generator resolves the Request class by convention: `AssistantSchema` → `AssistantRequest` in the same namespace
2. A mock `RouteContract` is bound into the container (see below)
3. The request is instantiated and `rules()` is called
4. Rules are parsed for OpenAPI constraints

### Mock Route Binding

Many `ResourceRequest::rules()` methods call `JsonApiRule::toOne()` or `JsonApiRule::toMany()`, which internally resolve the current route to get the schema. Outside an HTTP request context, this fails.

The generator works around this by creating an anonymous class implementing `RouteContract` and binding it to the container:

```php
app()->instance(RouteContract::class, $mockRoute);
```

The mock route returns the schema instance from its `schema()` method. This satisfies `JsonApiRule`'s internal resolution.

> The binding is only created if `RouteContract` is not already bound (`app()->bound(RouteContract::class)` check), preventing conflicts in test environments.

### Extracted Constraints

| Laravel Rule | OpenAPI Constraint |
|-------------|-------------------|
| `required` | Added to `required` array on the attributes object |
| `integer` | `type: "integer"` (overrides default `number`) |
| `numeric` | Tracked for min/max interpretation |
| `min:N` | `minimum: N` (numeric) or `minLength: N` (string) |
| `max:N` | `maximum: N` (numeric) or `maxLength: N` (string) |
| `Rule::enum(EnumClass::class)` | `enum: [...]` with all case values (via reflection) |
| `in:a,b,c` | `enum: ["a", "b", "c"]` |

### Example Value Derivation

When constraints produce a numeric range, an example value is derived:

- With both `minimum` and `maximum`: midpoint, rounded
- With only `minimum` > 0: the minimum value
- Otherwise: `1` (integer) or `0.5` (number)

### DB-Only Enums

Some fields have enum values enforced at the database level (not in PHP validation). These are hardcoded in `SchemaBuilder::DB_ENUMS`:

```php
private const DB_ENUMS = [
    'ai-tools' => [
        'type' => ['mcp', 'function'],
        'status' => ['active', 'inactive'],
    ],
    'ai-model-statuses' => [
        'status' => ['online', 'offline', 'unknown'],
    ],
];
```

### Required Fields

- **Response schemas**: All non-hidden fields are marked `required` (JSON:API always serializes all non-hidden fields)
- **Writable schemas**: Only fields with the Laravel `required` validation rule are marked `required`

---

## Route to Path Mapping

### Path Parameter Normalization

Laravel route parameters like `{assistant}` are normalized to `{id}` in the OpenAPI path, since JSON:API uses a uniform ID parameter across all resources.

### Operation Building by Endpoint Type

**Collection (`GET /{resource}`)**:
- Filter parameters from `$schema->filters()`
- Reusable parameter refs: `PageSize`, `PageNumber`, `Sort`, `Include`
- Response: `{ClassName}CollectionResponse`

**Collection (`POST /{resource}`)**:
- Request body: `{ClassName}CreateRequest`
- Responses: 201 (created), 401, 422

**Resource (`GET /{resource}/{id}`)**:
- Path parameter: `id`
- `Include` parameter ref
- Response: `{ClassName}SingleResponse`

**Resource (`PATCH /{resource}/{id}`)**:
- Request body: `{ClassName}UpdateRequest`
- Responses: 200, 401, 403, 404, 422

**Resource (`DELETE /{resource}/{id}`)**:
- Responses: 204, 401, 403, 404

**Related (`GET /{resource}/{id}/{relation}`)**:
- Uses `$field->inverse()` to resolve the related resource's class name → concrete schema ref
- Cardinality from field type (HasMany/BelongsToMany → array, else single)
- Inline response schema (not a named component)

**Relationship (`GET /{resource}/{id}/relationships/{relation}`)**:
- Always uses `ResourceIdentifier` (not concrete schemas)
- Cardinality from field type
- Includes `RelationshipLinks` in response

**Action (`POST /{resource}/{id}/actions/{action}`)**:
- Request body schema built from controller reflection → FormRequest class → `rules()`
- Special handling for `chat-test` (SSE response, see below)
- Other actions use standard `{ClassName}SingleResponse`

---

## Examples

### Example Resolution Order

The `ExampleBuilder` uses a 4-tier fallback for attribute values:

1. **`RESOURCE_OVERRIDES`** — Per-resource field value map (e.g., `assistants.name` → `"Test Assistant"`). Always wins when present.
2. **Validation constraints** — Derived from `ResourceRequest::rules()` via `SchemaBuilder::getConstraintExamples()`. Produces:
   - Enum fields: first case value (e.g., `Rule::enum(ReleaseStage::class)` → `"private"`)
   - Numeric ranges: midpoint between `min` and `max` (e.g., `min:0, max:1` → `0.5`)
   - Numeric with only `min`: the minimum value (e.g., `min:1` → `1`)
   - DB-only enums: first value from `SchemaBuilder::DB_ENUMS`
3. **`FIELD_DEFAULTS`** — Common field name map (e.g., any field named `url` → `"https://example.com"`)
4. **Type-based fallback** — Based on the JSON:API field type:
   - `Boolean` → `false`
   - `Number` → `0`
   - `DateTime` → `"2026-06-10T12:00:00.000000Z"`
   - `ArrayList` → `[]`
   - `ArrayHash` → `{}`
   - `Str` → `"string"`

> **Note:** Validation constraint examples are only used for **writable** attribute examples (create/update request bodies). Response examples use tiers 1, 3, and 4 only, since responses should show all fields regardless of validation rules.

### Filter Examples

When `--no-examples` is not passed, the generator queries the database for distinct values of each filterable field (up to 10 values). These become the `example` field on filter query parameters.

### Relationship Examples

Writable relationship examples use `$field->inverse()` to get the correct JSON:API type for the `ResourceIdentifier`:

```php
$inverseType = $field->inverse(); // e.g., "ai-models"
$isToMany = ($field instanceof HasMany || $field instanceof BelongsToMany);

$data = $isToMany
    ? [['type' => $inverseType, 'id' => '1']]
    : ['type' => $inverseType, 'id' => '1'];
```

### Action Examples

Action request/response examples are hardcoded in `ACTION_EXAMPLES` for known actions:

- `assistants.chat-test` — SSE stream example
- `assistants.favorite` — Toggle is_favorite
- `assistants.feedback` — Submit feedback text
- `assistants.release` — Change release_stage
- `assistants.remix` — Response with new assistant ID

### Where Examples Live in the Spec

Examples are placed at the **media-type level** (sibling of `schema` under `content/{media}`), not inside `$ref`'d component schemas. This is because most OpenAPI tools (including Bruno) do not resolve `example` fields inside `$ref`'d schemas.

```json
{
  "content": {
    "application/vnd.api+json": {
      "schema": { "$ref": "#/components/schemas/AssistantCreateRequest" },
      "example": {
        "data": {
          "type": "assistants",
          "attributes": { "name": "Test Assistant" }
        }
      }
    }
  }
}
```

---

## SSE / chat-test Streaming

The `POST /assistants/{id}/actions/chat-test` endpoint returns `text/event-stream` (Server-Sent Events) using the **OpenAI Responses API** compatible event format.

### Request Format

The request body uses **flat JSON** (no JSON:API envelope) at `application/json` content type for compatibility with OpenAI SDK clients:

```json
{
  "input": [{ "role": "user", "content": "Hello" }]
}
```

The `input` field accepts:
- A **string** (treated as a single user message with `role: user`)
- An **array** of `{role, content}` items where `content` is a string or array of `{type: "input_text", text: "..."}` content parts

OpenAI SDKs may send `role: "developer"` for reasoning models; this is internally mapped to `role: "system"` for compatibility with the AI provider.

The `model` field can be overridden by the client when the assistant has `allow_model_select` enabled, otherwise only the assistant's default model is used. The `stream` parameter is accepted for compatibility. `instructions` (via `PromptComposer`), `tools` (the assistant's own `ai_tools`), `temperature`, and `max_tokens` are derived from the assistant.

### Stream Adapter Architecture

The streaming response is produced by a reusable adapter under `app/Services/AI/Stream/` that follows the same pattern as the neuron-ai stream adapters:

| File | Role |
|---|---|---|
| `StreamAdapterInterface.php` | Contract: `transform(chunk)`, `start()`, `end()`, `error()`, `getHeaders()` |
| `SSEAdapter.php` | Abstract base: SSE formatting (`formatEvent`), ID generation, default headers |
| `OpenAIResponsesAdapter.php` | Converts runner chunks (`text_delta`, `thinking_delta`, `tool_call`, `tool_result`, `usage`) into OpenAI Responses API SSE events |

The controller emits `start()` events, then passes a **sink callback** to the runner for real-time streaming. Each chunk fires the sink immediately via the AI provider's HTTP callback, so output reaches the client as the model generates it — no buffering. On completion, `end()` finalises the stream. The runner interface accepts an optional `$sink` parameter for this pattern.

### OpenAPI Documentation

The endpoint is documented with:

- A `text/event-stream` content type (not `application/vnd.api+json`)
- A `oneOf` referencing 12 SSE event component schemas
- 12 dedicated component schemas (plus 4 supporting schemas):

### Supporting Schemas

| Schema | Description | Type |
|--------|-------------|------|
| `ResponseUsage` | Token usage information | `object` with `input_tokens`, `output_tokens`, `total_tokens` |
| `OutputTextContent` | Text content part within a message | `object` with `type`, `text`, `annotations` |
| `OutputMessage` | Assistant message output item | `object` with `id`, `type`, `role`, `content`, `status` |
| `FunctionCallItem` | Function call output item | `object` with `id`, `type`, `call_id`, `name`, `arguments`, `status` |
| `ResponseObject` | Top-level response object | `object` with `id`, `object`, `status`, `model`, `output`, `usage`, `created_at` |

### Event Schemas

| Schema | Event Type | Description |
|--------|------------|-------------|
| `SseResponseCreatedEvent` | `response.created` | Emitted when the response is created. Always first. |
| `SseResponseInProgressEvent` | `response.in_progress` | Emitted when processing starts. |
| `SseOutputItemAddedEvent` | `response.output_item.added` | New output item added. Used for `message`, `function_call`, and `reasoning` items. |
| `SseContentPartAddedEvent` | `response.content_part.added` | New content part added to a message. |
| `SseOutputTextDeltaEvent` | `response.output_text.delta` | Text fragment. 0 or more per stream. |
| `SseOutputTextDoneEvent` | `response.output_text.done` | Complete text content finalized. |
| `SseContentPartDoneEvent` | `response.content_part.done` | Content part completed. |
| `SseOutputItemDoneEvent` | `response.output_item.done` | Output item completed. Used for `message`, `function_call`, and `reasoning` items. |
| `SseReasoningSummaryPartAddedEvent` | `response.reasoning_summary_part.added` | New reasoning summary part added. Requires `output_item.added` (reasoning) first. |
| `SseReasoningSummaryTextDeltaEvent` | `response.reasoning_summary_text.delta` | Reasoning text fragment. 0 or more per stream. |
| `SseReasoningSummaryTextDoneEvent` | `response.reasoning_summary_text.done` | Complete accumulated reasoning text. |
| `SseReasoningSummaryPartDoneEvent` | `response.reasoning_summary_part.done` | Reasoning summary part completed. Followed by `output_item.done` (reasoning). |
| `SseFunctionCallArgumentsDeltaEvent` | `response.function_call_arguments.delta` | Partial JSON arguments for a function call. |
| `SseFunctionCallArgumentsDoneEvent` | `response.function_call_arguments.done` | Complete function call arguments. |
| `SseResponseCompletedEvent` | `response.completed` | Final success event with full response object. |
| `SseErrorEvent` | `error` | Error event. Replaces `response.completed` on failure. |

All events carry a `sequence_number` (incrementing integer starting at 0) for ordering.

The SSE example response shows the event stream format:

```
event: response.created
data: {"type":"response.created","response":{"id":"resp_abc123","object":"response","status":"in_progress",...},"sequence_number":0}

event: response.in_progress
data: {"type":"response.in_progress","response":{...},"sequence_number":1}

event: response.output_item.added
data: {"type":"response.output_item.added","output_index":0,"item":{"id":"think_...","type":"reasoning"},"sequence_number":2}

event: response.reasoning_summary_part.added
data: {"type":"response.reasoning_summary_part.added","item_id":"think_...","output_index":0,"summary_index":0,"sequence_number":3}

event: response.reasoning_summary_text.delta
data: {"type":"response.reasoning_summary_text.delta","item_id":"think_...","output_index":0,"summary_index":0,"delta":"I should fetch the file...","sequence_number":4}

event: response.reasoning_summary_text.done
data: {"type":"response.reasoning_summary_text.done","item_id":"think_...","output_index":0,"summary_index":0,"text":"I should fetch the file from GitHub...","sequence_number":6}

event: response.reasoning_summary_part.done
data: {"type":"response.reasoning_summary_part.done","item_id":"think_...","output_index":0,"summary_index":0,"sequence_number":7}

event: response.output_item.done
data: {"type":"response.output_item.done","output_index":0,"item":{"id":"think_...","type":"reasoning"},"sequence_number":8}

event: response.completed
data: {"type":"response.completed","response":{"id":"resp_abc123","status":"completed","output":[...],"usage":{...}},"sequence_number":9}
```

---

## Adding a New Resource

When adding a new JSON:API resource to the application, the generator will pick it up automatically **if** the schema is registered in `app/JsonApi/V1/Server.php`. Follow this checklist:

1. **Create the Schema** (`app/JsonApi/V1/{Resource}/{Resource}Schema.php`)
   - Define `fields()` with attribute and relationship fields
   - Define `$model` static property
   - Use `isHidden()` and `isReadOnly()` to control visibility

2. **Create the Request** (`app/JsonApi/V1/{Resource}/{Resource}Request.php`)
   - Define `rules()` with validation constraints
   - These rules drive OpenAPI constraints and required fields

3. **Create the Controller** (`app/Http/Controllers/Api/V1/{Resource}Controller.php`)
   - Add `FetchRelated` and `FetchRelationship` traits for relationship endpoints

4. **Register in Server** (`app/JsonApi/V1/Server.php`)
   - Add the schema class to the `$schemas` array

5. **Add Example Overrides** (optional)
   - Add entries to `ExampleBuilder::RESOURCE_OVERRIDES` for realistic example values
   - Without overrides, generic defaults will be used

6. **Add Action Examples** (if the resource has custom actions)
   - Add entries to `ExampleBuilder::ACTION_EXAMPLES`
   - Ensure the controller method has a typed FormRequest parameter for auto-discovery

7. **Add DB-Only Enums** (if applicable)
   - Add entries to `SchemaBuilder::DB_ENUMS` for fields with database-level enum constraints

8. **Run the generator locally**
   ```bash
   php artisan openapi:generate
   ```

9. **Rebuild the Docker image** (for production)
   The spec is generated at build time via the `openapi_builder` stage in the Dockerfile.

---

## Troubleshooting

### "Generation failed" with reflection errors

The generator uses reflection to access `Server::allSchemas()` and `EnumRule::$type`. If these internals change in a Laravel JSON:API upgrade, the generator will need updating. Check:

- `OpenApiGenerator::getSchemaClasses()` — reflection on `allSchemas()`
- `SchemaBuilder::getEnumClass()` — reflection on `EnumRule::$type` property

### Missing validation constraints

If a resource's writable schema has no constraints:

1. Check that `{Resource}Request` exists in the same namespace as `{Resource}Schema`
2. Check that the Request class extends `LaravelJsonApi\Laravel\Http\Requests\ResourceRequest`
3. Check that `rules()` returns rules for fields that match the schema's attribute field names

### Missing relationship endpoints

If related/relationship paths are not generated:

1. Ensure the controller uses `FetchRelated` and `FetchRelationship` traits
2. Ensure the schema defines the relationship field
3. Ensure the route is registered (check `routes/api.php` or JSON:API auto-routing)

### Mock route conflicts in tests

The generator binds a mock `RouteContract` to the container. It checks `app()->bound()` first, so it won't overwrite an existing binding. If tests fail after running the generator, ensure the container is reset between tests.

### Empty filter examples

Filter examples come from database queries. If the database is empty or the `--no-examples` flag is passed, filter parameters will have no `example` field.

### Bruno cannot import the spec via HTTPS

Bruno's built-in Chromium rejects self-signed certificates. Use file import instead:
- Import the JSON file directly from `public/docs/openapi.json`

---

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| JSON only (no YAML) | `symfony/yaml` is a dev dependency; JSON uses native `json_encode` with no extra deps |
| Build-time generation via Docker multi-stage | Spec is baked into the image; no runtime artisan command needed; no dev deps in production |
| Output to `public/docs/` | Served directly by nginx as static files; no storage symlink or PHP controller needed |
| Media-type-level examples | Bruno and most tools don't resolve `example` from `$ref`'d schemas |
| `DB_ENUMS` constant | Some enums exist only in database migrations, not in PHP validation rules |
| Mock `RouteContract` | Required for `JsonApiRule::toOne()`/`toMany()` to resolve the schema outside HTTP context |
| `allSchemas()` via reflection | Protected method, no public API available; simplest way to get all registered schemas |
| `inverse()` for relationship examples | Ensures examples use the correct JSON:API type identifier for related resources |
| ID excluded from attributes | JSON:API already has `id` at the data top level; duplicating it in attributes is redundant |
