<?php

namespace App\Services\AI\Providers\Ollama;

use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class OllamaRequestConverter
{
    use ToolAwareConverter;

    public function __construct(
        private MessageAttachmentFinder   $attachmentFinder,
        private OllamaAttachmentFormatter $attachmentFormatter
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
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
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

        return $payload;


    }

    private function formatMessage(array $message, array $attachmentsMap, AiModel $model): array
    {
        $formatted = [
            'role' => $message['role'],
        ];

        $content = $message['content'] ?? [];
        $text = '';

        // Add text if present
        if (!empty($content['text'])) {
            $text = $content['text'];
        }

        // Handle attachments with permission checks
        if (!empty($content['attachments'])) {
            foreach ($this->attachmentFormatter->formatByAttachmentUuidsAndMap($model, $content['attachments'], $attachmentsMap) as $formattedAttachment) {
                $text .= $formattedAttachment;
            }

            $images = $this->attachmentFormatter->getFormattedImages();
            if (!empty($images)) {
                $formatted['images'] = $images;
            }
        }

        $formatted['content'] = $text;

        return $formatted;
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
