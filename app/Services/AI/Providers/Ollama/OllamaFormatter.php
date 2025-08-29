<?php

namespace App\Services\AI\Providers\Ollama;

use App\Services\AI\Providers\ModelUtilities;
use App\Services\AI\Interfaces\FormatterInterface;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

use App\Services\Chat\Attachment\AttachmentService;

class OllamaFormatter implements FormatterInterface
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

        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);

        // Load and attach attachment models if any
        $attachmentsMap = $this->loadAttachmentModelsByUuid($messages);

        // Format messages for Ollama
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $modelId);
        }

        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->utils->hasTool($modelId, 'stream'),
        ];
        return $payload;
    }

public function formatMessage(array $message, array $attachmentsMap, string $modelId): array
{
    $formatted = [
        'role' => $message['role'],
        'content' => ''
    ];

    $content = $message['content'] ?? [];
    $text = '';
    $images = [];

    // Add text if present
    if (!empty($content['text'])) {
        $text = $content['text'];
    }

    // Handle attachments with permission checks
    if (!empty($content['attachments'])) {
        $this->processAttachments($content['attachments'], $attachmentsMap, $modelId, $text, $images);
    }

    $formatted['content'] = $text;

    // Add images if any were processed
    if (!empty($images)) {
        $formatted['images'] = $images;
    }

    return $formatted;
}

private function processAttachments(array $attachmentUuids, array $attachmentsMap, string $modelId, string &$text, array &$images): void
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
                    $imageData = $this->processImageAttachment($attachment, $attachmentService);
                    if ($imageData) {
                        $images[] = $imageData;
                    }
                } else {
                    $skippedAttachments[] = $attachment->name . ' (image not supported)';
                }
                break;

            case 'document':
                if ($this->utils->canProcessDocument($modelId)) {
                    $documentText = $this->processDocumentAttachment($attachment, $attachmentService);
                    if ($documentText) {
                        $text .= "\n\n" . $documentText;
                    }
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
        $text .= "\n\n[NOTE: The following attachments were not included because this model does not support them: " . implode(', ', $skippedAttachments) . "]";
    }
}

private function processImageAttachment(Attachment $attachment, AttachmentService $attachmentService): ?string
{
    try {
        $file = $attachmentService->retrieve($attachment);
        return base64_encode($file);
    } catch (\Exception $e) {
        Log::error('Failed to process image attachment: ' . $e->getMessage());
        return null;
    }
}

private function processDocumentAttachment(Attachment $attachment, AttachmentService $attachmentService): ?string
{
    try {
        $fileContent = $attachmentService->retrieve($attachment, 'md');
        $html_safe = htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
        return "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---";
    } catch (\Exception $e) {
        Log::error('Failed to process document attachment: ' . $e->getMessage());
        return "[ERROR: Could not process document attachment: {$attachment->name}]";
    }
}











        /**
     * Handle special formatting requirements for specific models
     *
     * @param string $modelId
     * @param array $messages
     * @return array
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for o1-mini: convert system to user
        if ($modelId === 'gemma-3-27b-it' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'assistant';
        }

        return $messages;
    }


#NOTE: THESE CAN GO TO BASE FORMATTER

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


