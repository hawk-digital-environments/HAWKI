<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Incremental reasoning / thinking fragment.
 */
readonly class ReasoningDeltaEvent implements AiStreamEvent
{
    public const string TYPE = 'reasoning_delta';

    /**
     * @param null|array<string, mixed> $providerMetadata
     */
    public function __construct(
        public string $reasoning,
        public ?string $signature = null,
        public ?string $encryptedContent = null,
        public ?int $blockIndex = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
