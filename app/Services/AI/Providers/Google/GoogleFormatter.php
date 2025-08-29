<?php

namespace App\Services\AI\Providers\Google;

use App\Services\AI\Providers\ModelUtilities;
use App\Services\AI\Interfaces\FormatterInterface;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

use App\Services\Chat\Attachment\AttachmentService;

class GoogleFormatter implements FormatterInterface
{
    protected $config;
    protected $utils;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->utils = new ModelUtilities($config);
    }


    public function format(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Load and attach attachment models if any
        $attachmentsMap = $this->loadAttachmentModelsByUuid($messages);


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
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $modelId);
        }


        $payload = [
            'model' => $modelId,
            'system_instruction' => $systemInstruction,
            'contents' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->utils->hasTool($modelId, 'stream'),
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

        // Google Search only works with gemini >= 2.0
        // Search tool is context sensitive, this means the llm decides if a search is necessary for an answer
        $tools = $this->utils->getModelDetails($modelId)['tools'];
        if (array_key_exists('web_search', $tools) && $tools['web_search'] === true){
            $payload['tools'] = $rawPayload['tools'] ?? [
                [
                    "google_search" => new \stdClass()
                ]
            ];
        }

        return $payload;
    }


    public function formatMessage(array $message, array $attachmentsMap, string $modelId): array
    {
        $formatted = [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
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
            $this->processAttachments($content['attachments'], $attachmentsMap, $modelId, $formatted['parts']);
        }

        return $formatted;
    }

    private function processAttachments(array $attachmentUuids, array $attachmentsMap, string $modelId, array &$parts): void
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
                    if ($this->utils->canProcessImage($modelId)) {
                        $parts[] = $this->processImageAttachment($attachment, $attachmentService, );
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (image not supported)';
                    }
                    break;

                case 'document':
                    if ($this->utils->canProcessDocument($modelId)) {
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


    private function loadAttachmentModelsByUuid(array $messages): array
    {
        $uuids = collect($messages)
            ->pluck('content.attachments')
            ->filter()
            ->flatten()
            ->unique()
            ->all();

        if (empty($uuids)) {
            return [];
        }

        return Attachment::whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid')
            ->all(); // returns [uuid => AttachmentModel]
    }
}


