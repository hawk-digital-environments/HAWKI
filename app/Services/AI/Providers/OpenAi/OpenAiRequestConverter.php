<?php

namespace App\Services\AI\Providers\OpenAi;

use App\Models\Attachment;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class OpenAiRequestConverter
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

        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $attachmentsMap, $model);
        }

        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'input' => $formattedMessages,
//            'stream' => $rawPayload['stream'] && $model->hasTool('stream'),
        ];
        $payloadJson = json_encode($payload);

        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }
        $payload['temperature'] = 1;
        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }

        if (isset($rawPayload['frequency_penalty'])) {
            $payload['frequency_penalty'] = $rawPayload['frequency_penalty'];
        }

        if (isset($rawPayload['presence_penalty'])) {
            $payload['presence_penalty'] = $rawPayload['presence_penalty'];
        }

        if($modelId === 'gpt-5'){
            $payload["text"]["verbosity"] = "low";
            $payload["reasoning"]["effort"] = "medium";
        }
//        $payload['tools'] = [
//            [
//                "type"=>"mcp",
//                "server_label"=>"dmcp",
//                "server_description"=>"A Dungeons and Dragons MCP server to assist with dice rolling.",
//                "server_url"=>"https://dmcp-server.deno.dev/sse",
//                "require_approval"=>"always",
//            ]
//        ];
        return $payload;
    }

    private function formatMessage(
        array $message,
        array $attachmentsMap,
        AiModel $model
    ): array {
        $role = $message['role'];

        $formatted = [
            'role' => $role,
            'content' => [],
        ];

        $content = $message['content'] ?? [];

        /**
         * USER MESSAGES
         */
        if ($role === 'user' || $role === 'system') {

            if (!empty($content['text'])) {
                $formatted['content'][] = [
                    'type' => 'input_text',
                    'text' => $content['text'],
                ];
            }

            if (!empty($content['attachments'])) {
                $this->processAttachments(
                    $content['attachments'],
                    $attachmentsMap,
                    $model,
                    $formatted['content']
                );
            }

            return $formatted;
        }

        /**
         * ASSISTANT MESSAGES (history replay)
         */
        if ($role === 'assistant') {

            if (!empty($content['text'])) {
                $formatted['content'][] = [
                    'type' => 'output_text',
                    'text' => $content['text'],
                ];
            }

            return $formatted;
        }

        /**
         * SAFETY FALLBACK
         */
        throw new \InvalidArgumentException("Unsupported role: {$role}");
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

    private function processImageAttachment(
        Attachment $attachment,
        AttachmentService $attachmentService
    ): array {
        // Retrieve raw binary image data
        $binary = $attachmentService->retrieve($attachment);

        // Detect mime type (important for vision models)
        $mime = $attachment->mime_type ?? 'image/png';

        // Encode as base64 data URL
        $base64 = base64_encode($binary);

        return [
            'type' => 'input_image',
            'image_url' => "data:{$mime};base64,{$base64}",
        ];
    }


    private function processDocumentAttachment(Attachment $attachment, AttachmentService $attachmentService): array
    {
        try {
            $fileContent = $attachmentService->retrieve($attachment, 'md');
            $html_safe = htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8');
            return [
                'type' => 'input_text',
                'text' => "[ATTACHED FILE: {$attachment->name}]\n---\n{$html_safe}\n---"
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process document attachment: ' . $e->getMessage());
            return [
                'type' => 'input_text',
                'text' => '[ERROR: Could not process document attachment: ' . $attachment->name . ']'
            ];
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
        if ($modelId === 'o1-mini' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }

        return $messages;
    }
}
