<?php

namespace App\Services\AI\Providers\Responses;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class ResponsesRequestConverter
{
    /**
     * Convert HAWKI internal request to Responses API payload
     */
    public function convertRequestToPayload(AiRequest $request): array
    {
        $rawPayload = $request->payload;
        $model = $request->model;
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Map messages and separate instructions from input
        $mappedMessages = $this->mapMessages($messages);
        
        // Extract instructions (developer/system messages) and input (conversation)
        [$instructions, $input] = $this->separateInstructionsAndInput($mappedMessages);

        // Build base payload
        $payload = [
            'model' => $modelId,
            'input' => $input,
            'store' => false, // Privacy: don't store conversations
        ];

        // Add instructions if present
        if ($instructions !== null) {
            $payload['instructions'] = $instructions;
        }

        // Add reasoning configuration based on model
        if ($this->supportsReasoning($modelId)) {
            $payload['reasoning'] = [
                'effort' => $this->getReasoningEffort($modelId, $rawPayload),
            ];
        }

        // Add text format for structured outputs if specified
        if (isset($rawPayload['response_format'])) {
            $payload['text'] = [
                'format' => $rawPayload['response_format'],
            ];
        }

        // Add previous_response_id for multi-turn conversations
        if (isset($rawPayload['previous_response_id'])) {
            $payload['previous_response_id'] = $rawPayload['previous_response_id'];
        }

        // Handle web_search tool (following GoogleRequestConverter pattern)
        // Check if model supports web_search AND frontend has enabled it
        $availableTools = $model->getTools();
        if (isset($availableTools['web_search']) && $availableTools['web_search'] === true) {
            // Model supports web_search - check if frontend enabled it
            if (isset($rawPayload['tools']['web_search']) && $rawPayload['tools']['web_search'] === true) {
                // Add web_search tool to payload
                $payload['tools'] = [
                    ['type' => 'web_search']
                ];
            }
        }

        // Optional parameters
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }

        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }

        return $payload;
    }

    /**
     * Map messages for Responses API format
     * Converts 'system' role to 'developer' and handles auxiliaries
     */
    private function mapMessages(array $messages): array
    {
        $mapped = [];
        
        foreach ($messages as $message) {
            $role = $message['role'];
            
            // Responses API uses 'developer' instead of 'system'
            if ($role === 'system') {
                $role = 'developer';
            }

            $content = $message['content'] ?? [];
            $contentText = is_array($content) ? ($content['text'] ?? '') : $content;

            $mappedMessage = [
                'role' => $role,
                'content' => $contentText,
            ];

            // Handle auxiliaries for reasoning continuity
            if (isset($message['auxiliaries']) && !empty($message['auxiliaries'])) {
                $mappedMessage['auxiliaries'] = $message['auxiliaries'];
            }

            $mapped[] = $mappedMessage;
        }

        return $mapped;
    }

    /**
     * Separate instructions (developer messages) from input (conversation)
     * Returns [instructions, input]
     */
    private function separateInstructionsAndInput(array $mappedMessages): array
    {
        $instructions = null;
        $input = [];

        foreach ($mappedMessages as $message) {
            // Developer messages become instructions
            if ($message['role'] === 'developer') {
                if ($instructions === null) {
                    $instructions = $message['content'];
                } else {
                    $instructions .= "\n\n" . $message['content'];
                }
                continue;
            }

            // All other messages go into input
            $inputMessage = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];

            // Include auxiliaries (e.g., reasoning from previous responses)
            if (isset($message['auxiliaries'])) {
                foreach ($message['auxiliaries'] as $auxiliary) {
                    if ($auxiliary['type'] === 'responsesReasoning') {
                        // Extract reasoning items from previous responses
                        $reasoningData = json_decode($auxiliary['content'], true);
                        if (isset($reasoningData['reasoning'])) {
                            foreach ($reasoningData['reasoning'] as $reasoningItem) {
                                $input[] = $reasoningItem;
                            }
                        }
                    }
                }
            }

            $input[] = $inputMessage;
        }

        // If only one user message and no auxiliaries, use string format for simplicity
        if (count($input) === 1 && $input[0]['role'] === 'user' && !isset($input[0]['auxiliaries'])) {
            $input = $input[0]['content'];
        }

        return [$instructions, $input];
    }

    /**
     * Check if model supports reasoning
     */
    private function supportsReasoning(string $modelId): bool
    {
        // GPT-5 and GPT-4.1 families support reasoning
        return str_starts_with($modelId, 'gpt-5') || str_starts_with($modelId, 'gpt-4.1');
    }

    /**
     * Get reasoning effort level based on model and payload
     */
    private function getReasoningEffort(string $modelId, array $rawPayload): string
    {
        // Check if reasoning effort is specified in payload
        if (isset($rawPayload['reasoning_effort'])) {
            return $rawPayload['reasoning_effort'];
        }

        // Default reasoning effort based on model
        if (str_starts_with($modelId, 'gpt-5')) {
            return 'medium';
        }

        if (str_starts_with($modelId, 'gpt-4.1-nano')) {
            return 'low';
        }

        return 'low';
    }
}
