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
        
        // Extract previous_response_id from last assistant message's auxiliaries
        $previousResponseId = $this->extractPreviousResponseId($mappedMessages);
        
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

        // Get available tools from model (used for reasoning and web_search)
        $availableTools = $model->getTools();

        // Add reasoning configuration based on model tools
        // Admin decides if model supports reasoning via config - no hardcoded model checks
        if (isset($availableTools['reasoning']) && $availableTools['reasoning'] === true) {
            $payload['reasoning'] = [
                'effort' => $this->getReasoningEffort($modelId, $rawPayload),
                'summary' => 'auto', // Enable reasoning summaries (defaults to 'detailed' for most models)
            ];
            
            \Log::info('[RESPONSES] Reasoning enabled', [
                'model' => $modelId,
                'effort' => $payload['reasoning']['effort'],
                'summary' => $payload['reasoning']['summary']
            ]);
        } else {
            \Log::info('[RESPONSES] Reasoning not enabled', [
                'model' => $modelId,
                'reasoning_tool' => $availableTools['reasoning'] ?? 'not set'
            ]);
        }

        // Add text format for structured outputs if specified
        if (isset($rawPayload['response_format'])) {
            $payload['text'] = [
                'format' => $rawPayload['response_format'],
            ];
        }

        // Add previous_response_id for multi-turn conversations
        // Priority: 1) Extracted from auxiliaries, 2) Explicitly provided in rawPayload
        if ($previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        } elseif (isset($rawPayload['previous_response_id'])) {
            $payload['previous_response_id'] = $rawPayload['previous_response_id'];
        }

        // Handle web_search tool (following GoogleRequestConverter pattern)
        // Check if model supports web_search AND frontend has enabled it
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

            // Handle auxiliaries from content (client-side encrypted, now decrypted)
            // Similar to how Google handles groundingMetadata
            if (is_array($content) && isset($content['auxiliaries']) && !empty($content['auxiliaries'])) {
                $mappedMessage['auxiliaries'] = $content['auxiliaries'];
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
     * Get reasoning effort level based on model and payload
     * Admin controls effort via payload, with sensible defaults
     */
    private function getReasoningEffort(string $modelId, array $rawPayload): string
    {
        // Check if reasoning effort is specified in payload
        if (isset($rawPayload['reasoning_effort'])) {
            return $rawPayload['reasoning_effort'];
        }

        // Default effort - admin can override via config/payload
        return 'medium';
    }

        /**
     * Extract previous_response_id from the last assistant message's auxiliaries
     * This enables conversation continuity across multiple turns
     * 
     * Note: auxiliaries are stored at message level after mapMessages() processing
     */
    private function extractPreviousResponseId(array $mappedMessages): ?string
    {
        // Search backwards through messages for the last assistant message
        for ($i = count($mappedMessages) - 1; $i >= 0; $i--) {
            $message = $mappedMessages[$i];
            
            if ($message['role'] !== 'assistant') {
                continue;
            }

            // Auxiliaries are at message level (added by mapMessages)
            if (!isset($message['auxiliaries']) || !is_array($message['auxiliaries'])) {
                continue;
            }

            foreach ($message['auxiliaries'] as $auxiliary) {
                if (($auxiliary['type'] ?? '') === 'responsesMetadata') {
                    $metadata = json_decode($auxiliary['content'], true);
                    if (isset($metadata['response_id'])) {
                        return $metadata['response_id'];
                    }
                }
            }
        }

        return null;
    }
}
