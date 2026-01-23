<?php

namespace App\Services\AI\Providers\Gwdg;

use App\Models\Attachment;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class GwdgRequestConverter
{
    public function __construct(
        private MessageAttachmentFinder $attachmentFinder,
        private ToolRegistry $toolRegistry
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
            'stream' => $rawPayload['stream'] && $model->hasTool('stream'),
        ];

        // Add tools from registry if model supports function calling and tools are not disabled
        $disableTools = $rawPayload['_disable_tools'] ?? false;

        // Check if this is a simple greeting (optional optimization to avoid tool spam)
        $lastMessage = end($messages);
        $lastMessageText = $lastMessage['content']['text'] ?? '';
        $simpleGreetings = ['hi', 'hello', 'hey', 'hallo', 'greetings'];
        $isSimpleGreeting = in_array(strtolower(trim($lastMessageText)), $simpleGreetings);

        if ($model->hasTool('function_calling') && !$disableTools && !$isSimpleGreeting) {
            $tools = $this->buildToolsArray($model);
            if (!empty($tools)) {
                $payload['tools'] = $tools;
            }
        }

        return $payload;
    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $role = $message['role'];

        // Handle tool result messages (role="tool")
        if ($role === 'tool') {
            return [
                'role' => 'tool',
                'tool_call_id' => $message['tool_call_id'],
                'content' => $message['content'], // Already a string
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
                $this->processAttachments($content['attachments'], $attachmentsMap, $model, $formattedContent);
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

    private function processAttachments(array $attachmentUuids, array $attachmentsMap, AiModel $model, array &$content): void
    {
        $attachmentService = app(AttachmentService::class);
        $skippedAttachments = [];

        foreach ($attachmentUuids as $uuid) {
            $attachment = $attachmentsMap[$uuid] ?? null;
            if (!$attachment) {
                continue; // skip invalid
            }

            switch ($attachment->type) {
                case 'image':
                    if ($model->canProcessImage()) {
                        $content[] = $this->processImageAttachment($attachment, $attachmentService);
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (image not supported)';
                    }
                    break;

                case 'document':
                    if ($model->canProcessDocument()) {
                        $content[] = $this->processDocumentAttachment($attachment, $attachmentService);
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (file upload not supported)';
                    }
                    break;

                default:
                    Log::warning('Unknown attachment type: ' . $attachment->type);
                    $skippedAttachments[] = $attachment->name . ' (unsupported type)';
                    break;
            }
        }

        // Notify about skipped attachments
        if (!empty($skippedAttachments)) {
            $content[] = [
                'type' => 'text',
                'text' => '[NOTE: The following attachments were not included because this model does not support them: ' . implode(', ', $skippedAttachments) . ']'
            ];
        }
    }

    private function processImageAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $file = $attachmentService->retrieve($attachment);
            $imageData = base64_encode($file);
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$attachment->mime};base64,{$imageData}",
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process image attachment: ' . $e->getMessage());
            return [
                'type' => 'text',
                'text' => '[ERROR: Could not process image attachment: ' . $attachment->name . ']'
            ];
        }
    }

    private function processDocumentAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $fileContent = $attachmentService->retrieve($attachment, 'md');
            $html_safe = htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
            return [
                'type' => 'text',
                'text' => "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---"
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process document attachment: ' . $e->getMessage());
            return [
                'type' => 'text',
                'text' => '[ERROR: Could not process document attachment: ' . $attachment->name . ']'
            ];
        }
    }

    /**
     * Build tools array from the ToolRegistry
     */
    private function buildToolsArray(AiModel $model): array
    {
        $definitions = $this->toolRegistry->getDefinitionsForModel($model);
        $tools = [];

        foreach ($definitions as $definition) {
            $tools[] = $definition->toOpenAiFormat();
        }

        return $tools;
    }
}
