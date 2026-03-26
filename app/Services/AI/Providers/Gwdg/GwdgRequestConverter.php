<?php

namespace App\Services\AI\Providers\Gwdg;

use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class GwdgRequestConverter
{
    use ToolAwareConverter;

    public function __construct(
        private MessageAttachmentFinder $attachmentFinder,
        private GwdgAttachmentFormatter $attachmentFormatter
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

        // Format messages for GWDG
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $model);
        }

        $mergedMessages = $this->mergeConsecutiveMessagesWithSameRole($formattedMessages);

        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $mergedMessages,
            'stream' => $request->shouldStream(),
        ];
        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['params']['temperature'])) {
            $payload['temperature'] = $rawPayload['params']['temperature'];
        }
        if (isset($rawPayload['params']['top_p'])) {
            $payload['top_p'] = $rawPayload['params']['top_p'];
        }

        // Add tools from capabilities if not disabled
        $disableTools = $this->shouldDisableTools($rawPayload);

        // Build selected tools from model capabilities
        if (!$disableTools && !empty($rawPayload['tools'])) {
            $toolDefinitions = $this->buildSelectedTools($model, $rawPayload['tools']);
            if (!empty($toolDefinitions)) {
                $payload['tools'] = array_map(fn($toolDef) => [
                    'type' => 'function',
                    'function' => $toolDef->toOpenAiChatFormat(),
                ], $toolDefinitions);
            }
        }

//        \Log::info($payload);
        return $payload;
    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $role = $message['role'];

        // Handle tool result messages (role="tool")
        // Convert to user message for better compatibility with models that don't fully support tool role
        if ($role === 'tool') {
            return [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Here is the result from the tool call:\n\n" . $message['content'],
                    ]
                ],
            ];
        }

        // Handle assistant messages with tool_calls
        if ($role === 'assistant' && isset($message['tool_calls'])) {
            $formatted = [
                'role' => 'assistant',
                'tool_calls' => $message['tool_calls'],
            ];

            // Only include content if it's non-empty
            $content = $message['content'] ?? '';
            if ($content !== '' && $content !== null) {
                $formatted['content'] = $content;
            }

            return $formatted;
        }

        // Handle regular user/assistant messages
        $content = $message['content'] ?? [];
        $formattedContent = [];

        // Handle string content (from tool results or simple messages)
        if (is_string($content)) {
            $formattedContent[] = [
                'type' => 'text',
                'text' => $content,
            ];
        } else {
            // Handle structured content
            if (!empty($content['text'])) {
                $formattedContent[] = [
                    'type' => 'text',
                    'text' => $content['text'],
                ];
            }

            // Handle attachments with permission checks
            if (!empty($content['attachments'])) {
                foreach ($this->attachmentFormatter->formatByAttachmentUuidsAndMap($model, $content['attachments'], $attachmentsMap) as $formattedAttachment) {
                    $formattedContent[] = $formattedAttachment;
                }
            }
        }

        return [
            'role' => $role,
            'content' => $formattedContent
        ];
    }

    private function mergeConsecutiveMessagesWithSameRole(array $messages): array
    {
        $merged = [];
        foreach ($messages as $msg) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex >= 0 && $merged[$lastIndex]['role'] === $msg['role']) {
                $merged[$lastIndex]['content'] = array_merge($merged[$lastIndex]['content'], $msg['content']);
            } else {
                $merged[] = $msg;
            }
        }
        return $merged;
    }
}
