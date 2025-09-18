<?php

namespace App\Services\AI\Providers\Gwdg;

use App\Models\Attachment;
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

        return $payload;
    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $formatted = [
            'role' => $message['role'],
            'content' => []
        ];

        $content = $message['content'] ?? [];

        // Add text if present
        if (!empty($content['text'])) {
            $formatted['content'][] = [
                'type' => 'text',
                'text' => $content['text'],
            ];
        }

        // Handle attachments with permission checks
        if (!empty($content['attachments'])) {
            $this->processAttachments($content['attachments'], $attachmentsMap, $model, $formatted['content']);
        }

        return $formatted;
    }

    private function mergeConsecutiveMessagesWithSameRole(array $messages): array
    {
        $merged = [];
        foreach ($messages as $msg) {
            if (
                !empty($merged)
                && $merged[count($merged)-1]['role'] === $msg['role']
            ) {
                $prevMsg = $merged[count($merged)-1];
                $prevContent = $prevMsg['content'];
                $merged[count($merged)-1]['content'] = array_merge($prevContent, $msg['content']);
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
}
