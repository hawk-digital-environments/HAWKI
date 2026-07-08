<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Limits\Values;


use App\Services\Ai\Models\Limits\AiModelLimitsInterface;

/**
 * Token limits for a chat-type AI model.
 *
 * Stores the maximum number of input and output tokens the model accepts per request.
 * Either field may be null when the provider does not publish a limit for that direction.
 *
 * Serialised to/from the `limits` JSON column of {@see \App\Models\Ai\AiModel}
 */
class ChatAiModelLimits implements AiModelLimitsInterface
{
    private function __construct(
        private int|null $maxInputTokens = null,
        private int|null $maxOutputTokens = null
    )
    {
    }

    /**
     * Returns the maximum number of tokens allowed in the prompt, or $default when not set.
     */
    public function getMaxInputTokens(int|null $default = null): int|null
    {
        return $this->maxInputTokens ?? $default;
    }

    /** Sets (or clears) the maximum input token limit. */
    public function setMaxInputTokens(int|null $maxInputTokens): void
    {
        $this->maxInputTokens = $maxInputTokens;
    }

    /**
     * Returns the maximum number of tokens the model may generate per response, or $default when not set.
     */
    public function getMaxOutputTokens(int|null $default = null): int|null
    {
        return $this->maxOutputTokens ?? $default;
    }

    /** Sets (or clears) the maximum output token limit. */
    public function setMaxOutputTokens(int|null $maxOutputTokens): void
    {
        $this->maxOutputTokens = $maxOutputTokens;
    }

    public function toArray(): array
    {
        if (empty($this->maxInputTokens) && empty($this->maxOutputTokens)) {
            return [];
        }

        return [
            'max_input_tokens' => $this->maxInputTokens,
            'max_output_tokens' => $this->maxOutputTokens,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            maxInputTokens: $data['max_input_tokens'] ?? null,
            maxOutputTokens: $data['max_output_tokens'] ?? null
        );
    }
}
