# Model Connection

This document describes the architecture and implementation of HAWKI's AI model connection system, including the data flow, components, and how to add new AI providers.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Key Components](#key-components)
3. [Data Flow](#data-flow)
4. [Provider Implementation](#provider-implementation)
5. [How to Add a New Provider](#how-to-add-a-new-provider)
6. [Streaming vs Non-Streaming Requests](#streaming-vs-non-streaming-requests)
7. [Error Handling](#error-handling)
8. [Usage Analytics](#usage-analytics)

## Architecture Overview

HAWKI's AI integration uses a layered service architecture with dependency injection and factory patterns to process requests to various AI models (OpenAI, GWDG, Google, Ollama, OpenWebUI). The system provides a unified interface for interacting with different AI providers while handling model-specific requirements, streaming capabilities, and usage analytics.

<!-- ![HAWKI AI Integration Architecture](../img/architecture_diagram.png) -->

## Key Components

The AI connection system is built with a layered architecture consisting of the following components:

### Service Layer
- **AiService**: Main entry point for AI interactions, providing unified methods for model retrieval and request processing
- **AiFactory**: Factory service responsible for creating provider instances, model contexts, and managing dependencies
- **UsageAnalyzerService**: Tracks and records token usage for analytics and billing purposes

### Provider Layer  
- **ModelProviderInterface**: Interface defining provider contract for model discovery and configuration
- **ClientInterface**: Interface defining client contract for request execution and model status checks
- **AbstractClient**: Base implementation providing common request validation and streaming fallback logic

### Provider Implementations
- **OpenAI**: OpenAiClient, OpenAiRequestConverter, and specific request handlers
- **GWDG**: GwdgClient, GwdgRequestConverter, and specific request handlers  
- **Google**: GoogleClient, GoogleRequestConverter, and specific request handlers
- **Ollama**: OllamaClient, OllamaRequestConverter, and specific request handlers
- **OpenWebUI**: OpenWebUiClient, OpenWebUiRequestConverter, and specific request handlers

### Value Objects
- **AiRequest**: Immutable request object containing model reference and payload
- **AiResponse**: Response object with content, usage data, and completion status
- **AiModel**: Model definition with capabilities and context binding
- **TokenUsage**: Usage tracking data structure


## Data Flow

### Request Flow

1. **Entry Point**: Client sends request to controller (e.g., `StreamController`)
2. **Service Resolution**: Controller calls `AiService->sendRequest()` or `sendStreamRequest()`
3. **Request Processing**: `AiService` resolves model and creates `AiRequest` object
4. **Model Resolution**: `AiFactory` provides model instance with bound context
5. **Client Delegation**: Request is delegated to model's specific client (e.g., `OpenAiClient`)
6. **Request Conversion**: Client uses converter to transform payload to provider format
7. **API Communication**: Appropriate request handler executes HTTP call to provider API
8. **Response Processing**: Raw response is converted to standardized `AiResponse` format
9. **Usage Tracking**: Token usage is extracted and recorded via `UsageAnalyzerService`
10. **Response Delivery**: Formatted response is returned to client

### AiRequest Structure

```php
class AiRequest
{
    public ?AiModel $model = null;
    public ?array $payload = null;
}
```

The payload array contains:
```php
[
    'model' => 'gpt-4o',
    'stream' => true,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                'text' => 'Hello, how are you?',
                'attachments' => ['uuid1', 'uuid2'] // optional
            ]
        ]
    ],
    'temperature' => 0.7,    // optional
    'top_p' => 1.0,         // optional
    // ... other provider-specific parameters
]
```

### AiResponse Structure

```php
class AiResponse
{
    public array $content;           // Response content with structured format
    public ?TokenUsage $usage;       // Token consumption data
    public bool $isDone = true;      // Completion status (false for streaming chunks)
    public ?string $error = null;    // Error message if any
}
```

Response content format:
```php
[
    'content' => [
        'text' => 'AI-generated response text'
    ],
    'usage' => [
        'promptTokens' => 123,
        'completionTokens' => 456,
        'totalTokens' => 579
    ],
    'isDone' => true
]
```

## Provider Implementation

The new architecture separates concerns between model providers and clients, with dedicated request converters for payload transformation.

### Core Interfaces

**ModelProviderInterface** - Defines provider contract:
```php
interface ModelProviderInterface
{
    public function getConfig(): ProviderConfig;
    public function getModels(): AiModelCollection;
}
```

**ClientInterface** - Defines client contract:
```php
interface ClientInterface
{
    public function sendRequest(AiRequest $request): AiResponse;
    public function sendStreamRequest(AiRequest $request, callable $onData): void;
    public function getStatus(AiModel $model): ModelOnlineStatus;
}
```

### Implementation Pattern

Each provider follows this structure:

1. **Provider Class** (e.g., `GenericModelProvider`): Handles model discovery and configuration
2. **Client Class** (e.g., `OpenAiClient`): Manages request execution and delegation  
3. **Request Converter** (e.g., `OpenAiRequestConverter`): Transforms payloads to provider format
4. **Request Handlers**: Specific implementations for streaming/non-streaming requests

### Example: OpenAI Implementation

```php
class OpenAiClient extends AbstractClient
{
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new OpenAiNonStreamingRequest(
            $this->converter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new OpenAiStreamingRequest(
            $this->converter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
}
```

### Provider Examples

#### OpenAI Provider

```php
class OpenAIProvider extends BaseAIModelProvider
{
    public function formatPayload(array $rawPayload): array
    {
        // Transform payload to OpenAI format
    }
    
    public function formatResponse($response): array
    {
        // Extract content and usage from OpenAI response
    }
    
    // Other implemented methods...
}
```

#### Google Provider

```php
class GoogleProvider extends BaseAIModelProvider
{
    public function formatPayload(array $rawPayload): array
    {
        // Transform payload to Google Gemini format
    }
    
    public function formatResponse($response): array
    {
        // Extract content and usage from Google response
    }
    
    // Other implemented methods...
}
```

## How to Add a New Provider

Adding a new AI provider requires implementing the provider pattern with separate components for model discovery, request handling, and payload conversion.

### Implementation Steps

#### 1. Create Provider Directory Structure

For a new provider (e.g., "MyProvider"), create the following structure:
```
app/Services/AI/Providers/MyProvider/
├── MyProviderClient.php
├── MyProviderRequestConverter.php
└── Request/
    ├── MyProviderNonStreamingRequest.php
    ├── MyProviderStreamingRequest.php
    └── MyProviderUsageTrait.php
```

#### 2. Implement the Client

```php
<?php

namespace App\Services\AI\Providers\MyProvider;

use App\Services\AI\Providers\AbstractClient;

class MyProviderClient extends AbstractClient
{
    public function __construct(
        private readonly MyProviderRequestConverter $converter
    ) {}
    
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new MyProviderNonStreamingRequest(
            $this->converter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new MyProviderStreamingRequest(
            $this->converter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
    
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        // Implement status checking for your provider's models
    }
}
```

#### 3. Create Request Converter

```php
<?php

namespace App\Services\AI\Providers\MyProvider;

use App\Services\AI\Value\AiRequest;

class MyProviderRequestConverter
{
    public function convertRequestToPayload(AiRequest $request): array
    {
        $rawPayload = $request->payload;
        
        // Transform HAWKI format to your provider's expected format
        return [
            'model' => $rawPayload['model'],
            'messages' => $this->formatMessages($rawPayload['messages']),
            'stream' => $rawPayload['stream'] ?? false,
            // Add other provider-specific parameters
        ];
    }
    
    private function formatMessages(array $messages): array
    {
        // Convert HAWKI message format to provider format
        return array_map(function($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content']['text'] ?? ''
            ];
        }, $messages);
    }
}
```

#### 4. Implement Request Handlers

```php
<?php

namespace App\Services\AI\Providers\MyProvider\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class MyProviderNonStreamingRequest extends AbstractRequest
{
    use MyProviderUsageTrait;
    
    public function __construct(private array $payload) {}
    
    public function execute(AiModel $model): AiResponse
    {
        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            dataToResponse: fn(array $data) => new AiResponse(
                content: ['text' => $data['choices'][0]['message']['content'] ?? ''],
                usage: $this->extractUsage($model, $data)
            )
        );
    }
}
```

#### 5. Update Configuration

Add your new provider to the `config/model_providers.php` file:

```php
'providers' => [
    'myprovider' => [
        'active' => true,
        'api_key' => env('MYPROVIDER_API_KEY'),
        'api_url' => 'https://api.myprovider.com/v1/chat/completions',
        'ping_url' => 'https://api.myprovider.com/v1/models',
        'models' => [
            [
                'id' => 'my-model-1',
                'label' => 'My Provider Model 1',
                'streamable' => true,
                'capabilities' => ['text', 'image']
            ]
        ]
    ]
]
```

#### 6. Register with Dependency Container

The `AiFactory` automatically discovers providers by convention. Ensure your provider class follows the naming pattern:
- Provider directory: `app/Services/AI/Providers/{ProviderName}/`
- Client class: `{ProviderName}Client`
- The factory will automatically instantiate and configure your provider when needed.

### Key Implementation Notes

1. **Request Validation**: The `AbstractClient` handles request validation automatically
2. **Streaming Fallback**: Non-streamable models automatically fall back to regular requests
3. **Usage Tracking**: Implement the usage trait to extract token consumption data
4. **Error Handling**: Use the base request class error handling patterns
5. **Model Capabilities**: Define model capabilities (text, image, document processing) in configuration

### 4. Provider-Specific Considerations

When implementing a new provider, consider these aspects:

1. **API Format Differences**: Understand how the API expects messages and returns responses
2. **Streaming Protocol**: Implement the correct streaming protocol for the provider
3. **Usage Tracking**: Extract token usage information correctly
4. **Error Handling**: Handle provider-specific error responses
5. **Model Capabilities**: Configure which models support streaming

### 5. Testing Your Provider

After implementing your provider, test it thoroughly:

1. Test non-streaming requests
2. Test streaming requests
3. Verify error handling
4. Check usage tracking
5. Test with different message inputs
6. Validate response formatting

## Streaming vs Non-Streaming Requests

The AI service provides unified methods for both streaming and non-streaming requests with automatic fallback handling.

### Non-Streaming Requests

Standard requests wait for the complete response:

```php
// Using AiService
$response = $this->aiService->sendRequest([
    'model' => 'gpt-4o',
    'messages' => $messages
]);

// Returns complete AiResponse with content and usage
echo $response->content['text'];
```

### Streaming Requests

Streaming requests deliver responses in real-time chunks:

```php
// Using AiService with callback
$this->aiService->sendStreamRequest([
    'model' => 'gpt-4o', 
    'stream' => true,
    'messages' => $messages
], function(AiResponse $chunk) {
    if (!$chunk->isDone) {
        echo $chunk->content['text']; // Stream partial content
        flush();
    } else {
        // Final chunk with usage data
        $this->recordUsage($chunk->usage);
    }
});
```

### Automatic Streaming Fallback

If a model doesn't support streaming, the system automatically falls back to non-streaming mode:

```php
// In AbstractClient
public function sendStreamRequest(AiRequest $request, callable $onData): void
{
    if (!$request->model->isStreamable()) {
        // Automatic fallback to non-streaming
        $response = $this->sendRequest($request);
        $onData($response);
        return;
    }
    
    $this->executeStreamingRequest($request, $onData);
}
```

## Error Handling

The system provides comprehensive error handling through multiple layers:

### Exception Hierarchy

- **AiServiceExceptionInterface**: Base interface for all AI service exceptions
- **ModelIdNotAvailableException**: Thrown when requested model ID is not available
- **NoModelSetInRequestException**: Thrown when request lacks model specification
- **IncorrectClientForRequestedModelException**: Thrown when model/client mismatch occurs

### Request Validation

```php
// Automatic validation in AbstractClient
private function validateRequest(AiRequest $request): void
{
    if ($request->model === null) {
        throw new NoModelSetInRequestException();
    }
    
    // Validates client/model compatibility
    if ($modelClient !== $this) {
        throw new IncorrectClientForRequestedModelException(
            $request->model->getClient(),
            $this
        );
    }
}
```

### Error Response Format

```php
// Errors returned in AiResponse
$response = new AiResponse(
    content: [],
    error: 'Connection failed: timeout after 30s'
);
```

## Usage Analytics

The `UsageAnalyzerService` continues to track AI model usage, now working with the structured `TokenUsage` value objects:

### Token Usage Structure

```php
class TokenUsage implements JsonSerializable
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens
    ) {}
}
```

### Usage Tracking

```php
// Usage automatically extracted from responses
public function submitUsageRecord(TokenUsage $usage, string $type, string $model, ?string $roomId = null): void
{
    UsageRecord::create([
        'user_id' => Auth::id(),
        'room_id' => $roomId,
        'prompt_tokens' => $usage->promptTokens,
        'completion_tokens' => $usage->completionTokens,
        'total_tokens' => $usage->totalTokens,
        'model' => $model,
        'type' => $type,
    ]);
}
```

### Integration with Responses

Usage is automatically tracked when processing AI responses:

```php
// In request handlers, usage is extracted per provider
protected function extractUsage(AiModel $model, array $data): ?TokenUsage
{
    if (!isset($data['usage'])) {
        return null;
    }
    
    return new TokenUsage(
        promptTokens: $data['usage']['prompt_tokens'] ?? 0,
        completionTokens: $data['usage']['completion_tokens'] ?? 0,
        totalTokens: $data['usage']['total_tokens'] ?? 0
    );
}
```

### Analytics Applications

This structured approach enables:
- **Real-time Cost Tracking**: Monitor token consumption across models
- **Usage Pattern Analysis**: Identify high-usage patterns and optimize
- **Billing Integration**: Accurate cost allocation per user/room
- **Performance Monitoring**: Track model efficiency and response times
- **Resource Planning**: Predict capacity needs based on usage trends
