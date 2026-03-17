<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Tools\Value\ToolResultCollection;

/**
 * Special variant of AiRequest that is used for follow-up requests after a tool call.
 * It takes the original request, the tool call response, and the tool results,
 * and constructs a new request payload that includes all of this information.
 */
readonly class ToolCallFollowUpAiRequest extends AiRequest
{
    private function __construct(
        AiModel                     $model,
        array                       $payload,
        public AiRequest            $originalRequest,
        public ToolResultCollection $toolResults,
        public bool                 $allowsFurtherToolCalls = true
    )
    {
        parent::__construct(
            model: $model,
            payload: $payload
        );
    }

    public static function fromRequest(
        AiRequest            $originalRequest,
        ToolCallAiResponse   $responseThatCallsTools,
        ToolResultCollection $toolResults,
        bool                 $allowsFurtherToolCalls = true
    ): self
    {
        $payload = $originalRequest->payload;
        $payload['messages'][] = $responseThatCallsTools->toMessageFormat();
        $payload['messages'] = [
            ...$payload['messages'],
            ...$toolResults->toMessageFormat(),
        ];

        if (!$allowsFurtherToolCalls) {
            $payload['_disable_tools'] = true;
            $payload['tools'] = [];
        }

        return new self(
            model: $originalRequest->model,
            payload: $payload,
            originalRequest: $originalRequest,
            toolResults: $toolResults,
            allowsFurtherToolCalls: $allowsFurtherToolCalls
        );
    }
}
