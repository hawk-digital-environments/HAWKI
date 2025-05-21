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

HAWKI's AI integration uses a service-based architecture to process requests to various AI models (OpenAI, GWDG, Google). The system follows a factory and strategy pattern to abstract the connection to different AI service providers while maintaining a consistent interface.

<!-- ![HAWKI AI Integration Architecture](../img/architecture_diagram.png) -->

Key features include:
- Support for multiple AI providers (OpenAI, Google, GWDG)
- Both streaming and non-streaming response handling
- Standardized interface for all providers
- Extensible design for adding new providers
- Usage tracking and analytics

## Key Components

The AI connection system consists of the following key components:

### Controller Layer
- **StreamController**: Entry point for AI requests handling both direct and group chat interactions

### Service Layer
- **AIConnectionService**: Core orchestration service that manages the connection process
- **AIProviderFactory**: Factory class that creates appropriate provider instances
- **UsageAnalyzerService**: Tracks and records token usage for analytics and billing

### Provider Layer
- **AIModelProviderInterface**: Interface that all AI providers must implement
- **BaseAIModelProvider**: Abstract base class with common functionality
- **Provider Implementations**: Concrete implementations for each AI service (OpenAI, GWDG, Google)


## Data Flow

### Request Flow

1. Client sends request to `StreamController->handleAiConnectionRequest`
2. Controller validates the request and extracts payload
3. `AIConnectionService` processes the request
4. `AIProviderFactory` creates the appropriate provider
5. Provider formats the payload according to service requirements
6. Provider connects to the AI service API
7. Provider formats the response
8. Usage is tracked in `UsageAnalyzerService`
9. Response is returned to the client

### Request Payload Structure

```js
$validatedData = $request->validate([
    'payload.model' => 'required|string',
    'payload.stream' => 'required|boolean',
    'payload.messages' => 'required|array',
    'payload.messages.*.role' => 'required|string',
    'payload.messages.*.content' => 'required|array',
    'payload.messages.*.content.text' => 'required|string',
    
    'broadcast' => 'required|boolean',
    'isUpdate' => 'nullable|boolean',
    'messageId' => 'nullable|string',
    'threadIndex' => 'nullable|int', 
    'slug' => 'nullable|string',
    'key' => 'nullable|string',
]);
```

### Response Structure

For non-streaming responses:
```js
[
    'content' => 'Response text from AI model',
    'usage' => [
        'prompt_tokens' => 123,
        'completion_tokens' => 456
    ]
]
```

For streaming responses (per chunk):
```js
[
    'content' => 'Partial response text',
    'isDone' => false,
    'usage' => null
]
```

## Provider Implementation

Each AI provider follows the same interface but implements provider-specific handling.

### Provider Interface

All providers must implement the `AIModelProviderInterface`:

```js
interface AIModelProviderInterface
{
    public function formatPayload(array $rawPayload): array;
    public function formatResponse($response): array;
    public function formatStreamChunk(string $chunk): array;
    public function connect(array $payload, ?callable $streamCallback = null);
    public function makeNonStreamingRequest(array $payload);
    public function makeStreamingRequest(array $payload, callable $streamCallback);
    public function getModelDetails(string $modelId): array;
    public function supportsStreaming(string $modelId): bool;
}
```

### Base Provider

The `BaseAIModelProvider` abstract class provides common functionality:

```js
abstract class BaseAIModelProvider implements AIModelProviderInterface
{
    protected $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function connect(array $payload, ?callable $streamCallback = null)
    {
        $modelId = $payload['model'];
        
        if ($streamCallback && $this->supportsStreaming($modelId)) {
            return $this->makeStreamingRequest($payload, $streamCallback);
        } else {
            return $this->makeNonStreamingRequest($payload);
        }
    }
    
    // Other common methods...
}
```

### Provider Examples

#### OpenAI Provider

```js
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

```js
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

Adding a new AI provider to HAWKI is a straightforward process that involves creating a new provider class and updating the configuration. Follow these steps:

### 1. Create a New Provider Class

Create a new class in the `app/Services/AI/Providers` directory that extends `BaseAIModelProvider`:

```js
<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;

class OllamaProvider extends BaseAIModelProvider
{
    /**
     * Format the raw payload for Ollama API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Format messages for Ollama
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text']
            ];
        }
        
        return [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];
    }
    
    /**
     * Format the complete response from Ollama
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);
        
        // Extract content based on Ollama's response format
        $content = $jsonContent['response'] ?? '';
        
        return [
            'content' => $content,
            'usage' => $this->extractUsage($jsonContent)
        ];
    }
    
    /**
     * Format a single chunk from a streaming response
     *
     * @param string $chunk
     * @return array
     */
    public function formatStreamChunk(string $chunk): array
    {
        $jsonChunk = json_decode($chunk, true);
        
        $content = '';
        $isDone = false;
        $usage = null;
        
        // Extract content based on Ollama's streaming format
        if (isset($jsonChunk['response'])) {
            $content = $jsonChunk['response'];
        }
        
        // Check if this is the final chunk
        if (isset($jsonChunk['done']) && $jsonChunk['done'] === true) {
            $isDone = true;
            
            // Extract usage if available in the final chunk
            if (isset($jsonChunk['eval_count']) && isset($jsonChunk['prompt_eval_count'])) {
                $usage = [
                    'prompt_tokens' => $jsonChunk['prompt_eval_count'],
                    'completion_tokens' => $jsonChunk['eval_count'] - $jsonChunk['prompt_eval_count'],
                ];
            }
        }
        
        return [
            'content' => $content,
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }
    
    /**
     * Extract usage information from Ollama response
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        if (!isset($data['eval_count']) || !isset($data['prompt_eval_count'])) {
            return null;
        }
        
        return [
            'prompt_tokens' => $data['prompt_eval_count'],
            'completion_tokens' => $data['eval_count'] - $data['prompt_eval_count'],
        ];
    }
    
    /**
     * Make a non-streaming request to the Ollama API
     *
     * @param array $payload
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());
        
        // Execute the request
        $response = curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }
        
        curl_close($ch);
        
        return response($response)->header('Content-Type', 'application/json');
    }
    
    /**
     * Make a streaming request to the Ollama API
     *
     * @param array $payload
     * @param callable $streamCallback
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Implementation of streaming request for Ollama
        // Similar to OpenAI implementation but adapted for Ollama's API
        
        // Ensure stream is set to true
        $payload['stream'] = true;
        
        set_time_limit(120);
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders(true));
        
        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);
        
        // Execute the cURL session
        curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $streamCallback('Error: ' . curl_error($ch));
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
        }
        
        curl_close($ch);
        
        // Flush any remaining data
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
    }
}
```

### 2. Update the Provider Factory

Update the `AIProviderFactory` class to include your new provider:

```js
public function getProviderForModel(string $modelId): AIModelProviderInterface
{
    $providerId = $this->getProviderId($modelId);
    
    switch ($providerId) {
        case 'openai':
            return new OpenAIProvider($this->config['providers']['openai']);
        case 'gwdg':
            return new GWDGProvider($this->config['providers']['gwdg']);
        case 'google':
            return new GoogleProvider($this->config['providers']['google']);
        case 'ollama':
            return new OllamaProvider($this->config['providers']['ollama']);
        default:
            throw new \Exception("Unsupported provider: {$providerId}");
    }
}
```

### 3. Update Configuration

Add your new provider to the `config/model_providers.php` file:

```js
'ollama' => [
    'id' => 'ollama',
    'active' => true,
    'api_key' => '', // If needed
    'api_url' => 'http://localhost:11434/api/chat',
    'ping_url' => 'http://localhost:11434/api/tags',
    'models' => [
        [
            'id' => 'llama3',
            'label' => 'Ollama Llama 3',
            'streamable' => true,
        ],
        [
            'id' => 'mistral',
            'label' => 'Ollama Mistral',
            'streamable' => true,
        ],
    ]
]
```

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

HAWKI's model connection system supports both streaming and non-streaming requests.

### Non-Streaming Requests

Non-streaming requests wait for the complete response before returning to the client:

```js
// In AIConnectionService
public function processRequest(array $rawPayload, bool $streaming = false, ?callable $streamCallback = null)
{
    $modelId = $rawPayload['model'];
    $provider = $this->providerFactory->getProviderForModel($modelId);
    
    // Format the payload
    $formattedPayload = $provider->formatPayload($rawPayload);
    
    if (!$streaming) {
        // Standard request (non-streaming)
        $response = $provider->connect($formattedPayload);
        return $provider->formatResponse($response);
    }
    
    // Streaming handled elsewhere...
}
```

### Streaming Requests

Streaming requests send partial responses to the client as they become available:

```js
// In StreamController
private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
{
    // Set headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');
    
    // Create a callback function to process streaming chunks
    $onData = function ($data) use ($user, $avatar_url, $payload) {
        // Format and send chunks to client
    };
    
    // Process the streaming request
    $this->aiConnectionService->processRequest(
        $payload, 
        true, 
        $onData
    );
}
```

## Error Handling

The system includes error handling at multiple levels:

1. **Input Validation**: The controller validates all incoming requests
2. **Provider Selection**: The factory validates model IDs against available providers
3. **Connection Errors**: cURL connection errors are caught and reported
4. **Response Parsing**: JSON parsing errors are handled gracefully
5. **Streaming Disconnections**: Connection aborts are detected and handled

Example error handling:

```js
try {
    $provider = $this->providerFactory->getProviderForModel($modelId);
    $formattedPayload = $provider->formatPayload($rawPayload);
    $response = $provider->connect($formattedPayload);
} catch (\Exception $e) {
    Log::error('AI connection error: ' . $e->getMessage());
    return response()->json(['error' => 'Failed to connect to AI service'], 500);
}
```

## Usage Analytics

The `UsageAnalyzerService` tracks AI model usage for analytics and billing:

```js
public function submitUsageRecord($usage, $type, $model, $roomId = null) {
    $today = Carbon::today();
    $userId = Auth::user()->id;

    // Create a new record
    UsageRecord::create([
        'user_id' => $userId,
        'room_id' => $roomId,
        'prompt_tokens' => $usage['prompt_tokens'],
        'completion_tokens' => $usage['completion_tokens'],
        'model' => $model,
        'type' => $type,
    ]);
}
```

This data can be used for:
- Monitoring usage patterns
- Cost allocation
- Setting usage limits
- Generating reports
- Optimizing model selection