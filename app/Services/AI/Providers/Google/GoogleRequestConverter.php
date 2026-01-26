<?php

namespace App\Services\AI\Providers\Google;

use App\Models\Attachment;
use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class GoogleRequestConverter
{
    use ToolAwareConverter;

    public function __construct(
        private MessageAttachmentFinder $attachmentFinder
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
            'stream' => $rawPayload['stream'] && $model->hasTool('stream'),
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
            'temperature' => 1.0,
            'maxOutputTokens' => 800,
            'topP' => 0.8,
            'topK' => 10
        ];

        // Build tools from capabilities
        $disableTools = $this->shouldDisableTools($rawPayload);
        $tools = [];

        if (!$disableTools) {
            // Native Google Search (WEB_SEARCH capability)
            // Google Search only works with gemini >= 2.0
            // Search tool is context sensitive, this means the llm decides if a search is necessary for an answer

            // Check if model supports web_search (handles both boolean and string format)
            if ($model->hasTool('web_search') && $model->getToolStrategy('web_search') === 'native') {
                // If frontend requested websearch tool
                if (array_key_exists('tools', $rawPayload) &&
                    array_key_exists('web_search', $rawPayload['tools']) &&
                    $rawPayload['tools']['web_search'] == true) {

                    $tools[] = ["google_search" => new \stdClass()];
                }
            } else {
                // Fallback: websearch always on
                $tools[] = ["google_search" => new \stdClass()];
            }

            // MCP_DIRECT capabilities (e.g., dice_roll)
            $mcpServers = $this->buildMCPServers($model);
            foreach ($mcpServers as $mcpConfig) {
                // Google Gemini expects tools in their specific format
                // TODO: Verify Google's MCP integration format
                $tools[] = [
                    'mcp_server' => [
                        'server_url' => $mcpConfig['server_url'],
                        'server_label' => $mcpConfig['server_label'] ?? 'mcp_server',
                        'tools' => $mcpConfig['tools'] ?? [],
                    ]
                ];
            }
        }

        $payload['tools'] = $tools;
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
            $this->processAttachments($content['attachments'], $attachmentsMap, $model, $formatted['parts']);
        }

        return $formatted;
    }
    
    private function processAttachments(array $attachmentUuids, array $attachmentsMap, AiModel $model, array &$parts): void
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
                        $parts[] = $this->processImageAttachment($attachment, $attachmentService);
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (image not supported)';
                    }
                    break;

                case 'document':
                    if ($model->canProcessDocument()) {
                        $parts[] = $this->processDocumentAttachment($attachment, $attachmentService);
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
            $parts[] = [
                'text' => '[NOTE : The following attachments were not included because this model does not support them: ' . implode(', ', $skippedAttachments) . ']'
            ];
        }
    }


    private function processImageAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $file = $attachmentService->retrieve($attachment);
            $imageData = base64_encode($file);
            return  [
                'inline_data' => [
                    'mime_type' => $attachment->mime,
                    'data' => $imageData,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process image attachment: ' . $e->getMessage());
            return $parts[] = [
                'text' => '[ERROR: Could not process image attachment: ' . $attachment->name . ']'
            ];
        }
    }

    private function processDocumentAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try
        {
            $fileContent = $attachmentService->retrieve($attachment, 'md');
            $html_safe = htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
            return [
                'text' => "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---"
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process document attachment: ' . $e->getMessage());
            return [
                'text' => '[ERROR: Could not process document attachment: ' . $attachment->name . ']'
            ];
        }

    }
}
