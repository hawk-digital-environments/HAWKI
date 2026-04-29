<?php

declare(strict_types=1);

namespace App\Services\AI\Providers\OpenWebUI;

use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Value\AiRequest;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class OpenWebUiRequestConverter
{
    use ToolAwareConverter;

    public function convertRequestToPayload(AiRequest $request): array
    {
        $rawPayload = $request->payload;
        $model = $request->model;
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);

        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text'],
            ];
        }

        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $model->hasCapability('stream'),
        ];

        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['params']['temp'])) {
            $payload['temperature'] = $rawPayload['params']['temp'];
        }
        if (isset($rawPayload['params']['top_p'])) {
            $payload['top_p'] = $rawPayload['params']['top_p'];
        }
        if (isset($rawPayload['params']['max_tokens'])) {
            $payload['max_tokens'] = $rawPayload['params']['max_tokens'];
        }

        // Add tools from capabilities if not disabled
        $disableTools = $this->shouldDisableTools($rawPayload);

        if (! $disableTools && ! empty($rawPayload['tools'])) {
            $tools = $this->resolveTools($model, $rawPayload['tools'], 'toOpenAiChatWrappedFormat');

            if (! empty($tools)) {
                $payload['tools'] = $tools;
            }
        }

        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }

        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }

        if (isset($rawPayload['frequency_penalty'])) {
            $payload['frequency_penalty'] = $rawPayload['frequency_penalty'];
        }

        if (isset($rawPayload['presence_penalty'])) {
            $payload['presence_penalty'] = $rawPayload['presence_penalty'];
        }

        return $payload;
    }

    /**
     * Handle special formatting requirements for specific models
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for o1-mini: convert system to user
        if ($modelId === 'o1-mini' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }

        return $messages;
    }
}
