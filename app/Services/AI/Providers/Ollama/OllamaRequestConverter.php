<?php

namespace App\Services\AI\Providers\Ollama;

use App\Models\Attachment;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class OllamaRequestConverter
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
        
        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);
        
        // Load and attach attachment models if any
        $attachmentsMap = $this->attachmentFinder->findAttachmentsOfMessages($messages);
        
        // Format messages for Ollama
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $model);
        }
        
        // Build payload with common parameters
        return [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $model->hasTool('stream'),
        ];
    }
    
    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $formatted = [
            'role' => $message['role'],
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
            $this->processAttachments($content['attachments'], $attachmentsMap, $model, $text, $images);
        }
        
        $formatted['content'] = $text;
        
        // Add images if any were processed
        if (!empty($images)) {
            $formatted['images'] = $images;
        }
        
        return $formatted;
    }
    
    private function processAttachments(array $attachmentUuids, array $attachmentsMap, AiModel $model, string &$text, array &$images): void
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
                        $imageData = $this->processImageAttachment($attachment, $attachmentService);
                        if ($imageData) {
                            $images[] = $imageData;
                        }
                    } else {
                        $skippedAttachments[] = $attachment->name . ' (image not supported)';
                    }
                    break;
                
                case 'document':
                    if ($model->canProcessDocument()) {
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
}
