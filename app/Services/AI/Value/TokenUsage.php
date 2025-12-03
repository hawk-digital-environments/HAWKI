<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class TokenUsage implements \JsonSerializable
{
    public int $totalTokens;
    
    public function __construct(
        public AiModel $model,
        public int     $promptTokens,
        public int     $completionTokens,
        ?int           $totalTokens = null,
        // Extended token types (OpenAI Responses API)
        public int     $cacheReadInputTokens = 0,
        public int     $cacheCreationInputTokens = 0,
        public int     $reasoningTokens = 0,
        public int     $audioInputTokens = 0,
        public int     $audioOutputTokens = 0,
        // Server-side tool usage (e.g., web_search, code_interpreter)
        public ?array  $serverToolUse = null,
    )
    {
        // If totalTokens is explicitly provided, use it (e.g., from Google API)
        // Otherwise calculate it as the sum of prompt and completion tokens
        $this->totalTokens = $totalTokens ?? ($promptTokens + $completionTokens);
    }
    
    public function toArray(): array
    {
        return [
            'model' => $this->model->getId(),
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'audio_input_tokens' => $this->audioInputTokens,
            'audio_output_tokens' => $this->audioOutputTokens,
            'server_tool_use' => $this->serverToolUse,
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
}
