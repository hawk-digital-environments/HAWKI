<?php

namespace App\Services\AI\Providers\OpenAi;

use App\Services\AI\Providers\Traits\ToolAwareConverter;
use App\Services\AI\Utils\MessageAttachmentFinder;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\Storage\FileStorageService;
use Illuminate\Container\Attributes\Singleton;
use Psr\Log\LoggerInterface;

#[Singleton]
readonly class OpenAiRequestConverter
{
    use ToolAwareConverter;

    public function __construct(
        private MessageAttachmentFinder   $attachmentFinder,
        private FileStorageService        $storageService,
        private LoggerInterface           $logger,
        private OpenAiAttachmentFormatter $attachmentFormatter
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
            'stream' => $request->shouldStream(),
        ];

        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['params']['temperature'])) {
            $payload['temperature'] = $rawPayload['params']['temperature'];
        }
        if (isset($rawPayload['params']['top_p'])) {
            $payload['top_p'] = $rawPayload['params']['top_p'];
        }

        if (isset($rawPayload['frequency_penalty'])) {
            $payload['frequency_penalty'] = $rawPayload['frequency_penalty'];
        }

        if (isset($rawPayload['presence_penalty'])) {
            $payload['presence_penalty'] = $rawPayload['presence_penalty'];
        }

        // Add tools if not disabled
        $disableTools = $this->shouldDisableTools($rawPayload);
        if (!$disableTools && !empty($rawPayload['tools'])) {
            $tools = [];

            // Check if model has native web_search capability
            $webSearchValue = $model->getCapabilityStrategy('web_search');
            if ($model->hasCapability('web_search') && $webSearchValue === 'native') {
                if (in_array('web_search', $rawPayload['tools'], true)) {
                    $tools[] = [
                        "type"=> "web_search",
                        "external_web_access"=> true
                    ];
                }
            }

            $toolDefinitions = $this->buildSelectedTools($model, $rawPayload['tools']);
            foreach ($toolDefinitions as $toolDef) {
                $tools[] = $toolDef->toOpenAiResponseFormat();
            }

            if (!empty($tools)) {
                $payload['tools'] = $tools;
            }
        }

        if($modelId === 'gpt-5'){
            $payload["text"]["verbosity"] = "low";
            $payload["reasoning"]["effort"] = "medium";
        }
        return $payload;
    }

    private function formatMessage(
        array $message,
        array $attachmentsMap,
        AiModel $model
    ): array {
        $role = $message['role'];

        $instructions = 'IMPORTANT: When using the tool results, always mention the references and url as inline citation';

        // Handle tool result messages - Response API requires them as user messages
        if ($role === 'tool') {
            return [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'AiTool result for ' . ($message['tool_call_id'] ?? 'unknown'). $instructions . ': ' . $message['content'],
                    ]
                ],
            ];
        }

        // Handle assistant messages with tool_calls
        // Response API doesn't support tool_calls in input, so convert to output_text
        if ($role === 'assistant' && isset($message['tool_calls'])) {
            $toolCallSummary = [];
            foreach ($message['tool_calls'] as $tc) {
                $functionName = is_array($tc) ? ($tc['function']['name'] ?? 'unknown') : $tc->name;
                $toolCallSummary[] = 'Called function: ' . $functionName;
            }

            return [
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'output_text',
                        'text' => implode(', ', $toolCallSummary),
                    ]
                ],
            ];
        }

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
                foreach ($this->attachmentFormatter->formatByAttachmentUuidsAndMap(
                    model: $model,
                    attachmentUuids: $content['attachments'],
                    attachmentsMap: $attachmentsMap
                ) as $formattedAttachment) {
                    $formatted['content'][] = $formattedAttachment;
                }
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
