<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Tools;

/**
 * Additional tool-loop controls (parallelism, call budgets).
 */
readonly class ToolCallConfig
{
    public function __construct(
        public ?bool $disableParallel = null,
        public ?int $maxCalls = null,
    ) {
    }
}
