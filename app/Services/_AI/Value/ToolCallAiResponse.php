<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Tools\Value\ToolCallCollection;

/**
 * A special kind of AiResponse that includes the tool calls in the content.
 * This response type is used whenever a Model wants to call one of our provided tools and the system
 * now needs to execute those tool calls and return the results back to the model,
 * so the model can continue with its response generation.
 */
readonly class ToolCallAiResponse extends AiResponse
{
    /**
     * @inheritDoc
     */
    private function __construct(
        array       $content,
        public ToolCallCollection $toolCalls,
        ?TokenUsage $usage = null,
        ?string     $error = null,
        ?string     $finishReason = null,
        string      $type = 'message',
        ?array      $status = null
    )
    {
        parent::__construct($content, $usage, true, $error, $finishReason, $type, $status);
    }

    /**
     * Return this response in the message format that the model expects, which includes the tool calls in the content.
     * @return array
     */
    public function toMessageFormat(): array
    {
        return [
            'role' => 'assistant',
            'content' => $this->content['text'] ?? null,
            'tool_calls' => $this->toolCalls->toArray(),
        ];
    }

    /**
     * While doing streaming requests, we need to send intermediate responses that are not marked as done,
     * this way the frontend is able to update the UI with the tool calls, and then when the tool calls are done,
     * the final response will be sent with isDone = true, and the frontend can update the UI accordingly.
     *
     * This response is exactly that intermediate response.
     * @return AiResponse
     */
    public function toStreamUpdateResponse(): AiResponse
    {
        return new AiResponse(
            content: $this->content,
            usage: $this->usage,
            isDone: false,
            error: $this->error,
            finishReason: $this->finishReason,
            type: $this->type,
            status: $this->status
        );
    }

    public static function fromResponseAndToolCalls(AiResponse $response, ToolCallCollection $toolCalls): self
    {
        return new self(
            content: $response->content,
            toolCalls: $toolCalls,
            usage: $response->usage,
            error: $response->error,
            finishReason: $response->finishReason ?? 'tool_calls',
            type: 'tool_call',
            status: $response->status
        );
    }
}
