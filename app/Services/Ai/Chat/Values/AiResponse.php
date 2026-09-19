<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Passthrough\ProviderPassthroughItem;

/**
 * The provider-neutral chat response IR — single-choice by design, mirroring the
 * underlying {@see \Laravel\Ai\Responses\TextResponse} (one message, one finish reason).
 */
readonly class AiResponse
{
    /**
     * @param null|array<int, ProviderPassthroughItem> $providerPassthroughItems
     */
    public function __construct(
        public string $id,
        public string $model,
        public int $created,
        public AssistantMessage $message,
        public FinishReason $finishReason,
        public ?UsageInfo $usage = null,
        public ?string $serviceTier = null,
        public ?string $systemFingerprint = null,
        public ?array $providerPassthroughItems = null,
    ) {
    }
}
