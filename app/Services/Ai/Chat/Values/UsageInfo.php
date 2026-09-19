<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

use Laravel\Ai\Responses\Data\Usage;

/**
 * Token usage of a single generation.
 *
 * The IR names follow the prompt/completion convention; wire formats using the
 * input/output convention (Open Responses) translate in their formatter.
 */
readonly class UsageInfo
{
    /**
     * @param null|array<string, mixed> $promptTokensDetails
     * @param null|array<string, mixed> $completionTokensDetails
     */
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
        public ?int $reasoningTokens = null,
        public ?int $cacheReadTokens = null,
        public ?int $cacheCreationTokens = null,
        public ?array $promptTokensDetails = null,
        public ?array $completionTokensDetails = null,
    ) {
    }

    public static function fromLaravelUsage(Usage $usage): self
    {
        $prompt = $usage->promptTokens;
        $completion = $usage->completionTokens;

        return new self(
            promptTokens: $prompt,
            completionTokens: $completion,
            totalTokens: $prompt + $completion + $usage->reasoningTokens,
            reasoningTokens: 0 < $usage->reasoningTokens ? $usage->reasoningTokens : null,
            cacheReadTokens: 0 < $usage->cacheReadInputTokens ? $usage->cacheReadInputTokens : null,
            cacheCreationTokens: 0 < $usage->cacheWriteInputTokens ? $usage->cacheWriteInputTokens : null,
            promptTokensDetails: 0 < $usage->cacheReadInputTokens ? ['cached_tokens' => $usage->cacheReadInputTokens] : null,
            completionTokensDetails: 0 < $usage->reasoningTokens ? ['reasoning_tokens' => $usage->reasoningTokens] : null,
        );
    }
}
