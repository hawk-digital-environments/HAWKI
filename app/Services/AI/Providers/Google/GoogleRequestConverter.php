<?php

namespace App\Services\AI\Providers\Google;

use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class GoogleRequestConverter
{
    use ToolAwareConverter;

    public function __construct(
        private MessageAttachmentFinder   $attachmentFinder,
        private GoogleAttachmentFormatter $attachmentFormatter
    )
    {
    }

    public function convertRequestToPayload(AiRequest $request): array
    {
        $rawPayload = $request->payload;
        $model = $request->model;
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Load and attach attachment models if any
        $attachmentsMap = $this->attachmentFinder->findAttachmentsOfMessages($messages);

        // Extract system prompt from first message item
        $systemInstruction = [];
        if (isset($messages[0]) && $messages[0]['role'] === 'system') {
            $systemInstruction = [
            'parts' => [
                'text' => $messages[0]['content']['text'] ?? ''
            ]
            ];
            array_shift($messages);
        }

        // Format messages for Google
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $model);
        }


        $payload = [
            'model' => $modelId,
            'system_instruction' => $systemInstruction,
            'contents' => $formattedMessages,
            'stream' => $request->shouldStream(),
        ];

        // Set complete optional fields with content (default values if not present in $rawPayload)
        $payload['safetySettings'] = $rawPayload['safetySettings'] ?? [
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_ONLY_HIGH'
            ]
        ];

        $payload['generationConfig'] = $rawPayload['generationConfig'] ?? [
            // 'stopSequences' => ["Title"],
            'temperature' => $rawPayload['params']['temperature'] ?? 1.0,
//            'maxOutputTokens' => 3000, // MAX TOKEN UNSET, AVOIDS BLOCKING THE MODEL WHEN REASONING.
            'topP' => $rawPayload['params']['top_p'] ?? 0.8,
            'topK' => 10,
            "thinkingConfig"=> [
                "includeThoughts"=> true,
//                "thinkingBudget"=> 2048, // SET THINKING BUDGET TOKENS, COMING IN NEXT VERSION UI.
            ]
        ];

        // Build tools from capabilities
        $disableTools = $this->shouldDisableTools($rawPayload);

        $tools = [];

        if (!$disableTools && !empty($rawPayload['tools'])) {
            // Native Google Search (WEB_SEARCH capability)
            // Google Search only works with gemini >= 2.0
            // Search tool is context sensitive, this means the llm decides if a search is necessary for an answer

            // Check if model supports web_search (handles both boolean and string format)
            $webSearchValue = $model->getCapabilityStrategy('web_search');
            if ($model->hasCapability('web_search') && $webSearchValue === 'native') {
                if (in_array('web_search', $rawPayload['tools'], true)) {
                    $tools[] = ["google_search" => new \stdClass()];
                }
            }

            // Google may not support custom function tools or MCP integration yet
            // When implementing, use buildFunctionCallTools() and buildMCPTools()
            // and convert to Google's format using toGoogleFormat()
            $toolDefinitions = $this->buildSelectedTools($model, $rawPayload['tools']);
            foreach ($toolDefinitions as $toolDef) {
                $tools[] = $toolDef->toGoogleResponseFormat();
            }
        }

        $payload['tools'] = $tools;
        \Log::debug($payload);
        return $payload;
    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $role = $message['role'];

        // Handle tool result messages (if Google uses function calling)
        if ($role === 'tool') {
            return [
                'role' => 'function',
                'parts' => [
                    [
                        'functionResponse' => [
                            'name' => $message['tool_call_id'],
                            'response' => [
                                'content' => $message['content']
                            ]
                        ]
                    ]
                ]
            ];
        }

        // Handle assistant messages with tool_calls
        if ($role === 'assistant' && isset($message['tool_calls'])) {
            $parts = [];
            foreach ($message['tool_calls'] as $toolCall) {
                $parts[] = [
                    'functionCall' => [
                        'name' => $toolCall['function']['name'],
                        'args' => json_decode($toolCall['function']['arguments'], true)
                    ]
                ];
            }

            return [
                'role' => 'model',
                'parts' => $parts
            ];
        }

        $formatted = [
            'role' => $role === 'assistant' ? 'model' : 'user',
            'parts' => []
        ];

        $content = $message['content'] ?? [];

        // Add text if present
        if (!empty($content['text'])) {
            $formatted['parts'][] = [
                'text' => $content['text'],
            ];
        }

        // Handle attachments with permission checks
        if (!empty($content['attachments'])) {
            foreach ($this->attachmentFormatter->formatByAttachmentUuidsAndMap($model, $content['attachments'], $attachmentsMap) as $formattedAttachment) {
                $formatted['parts'][] = $formattedAttachment;
            }
        }

        return $formatted;
    }
}
