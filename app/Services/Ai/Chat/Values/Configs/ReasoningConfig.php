<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Reasoning / thinking configuration for a request.
 */
readonly class ReasoningConfig
{
    public function __construct(
        public ?ReasoningMode $mode = null,
        public ?ReasoningEffort $effort = null,
        public ?int $budgetTokens = null,
        public ?ReasoningSummary $summary = null,
        public ?bool $includeThoughts = null,
    ) {
    }
}
