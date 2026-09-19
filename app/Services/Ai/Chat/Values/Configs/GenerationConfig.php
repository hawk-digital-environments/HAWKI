<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Sampling and decoding parameters for a generation.
 *
 * All fields are optional; a null field means "not requested" and the resolved model /
 * provider defaults apply downstream.
 */
readonly class GenerationConfig
{
    /**
     * @param null|array<int, string> $stopSequences
     * @param null|array<string, int> $logitBias
     */
    public function __construct(
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $topK = null,
        public ?int $maxTokens = null,
        public ?array $stopSequences = null,
        public ?string $truncation = null,
        public ?float $frequencyPenalty = null,
        public ?float $presencePenalty = null,
        public ?array $logitBias = null,
        public ?int $seed = null,
        public ?bool $logprobs = null,
        public ?int $topLogprobs = null,
    ) {
    }
}
