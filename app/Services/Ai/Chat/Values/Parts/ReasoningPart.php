<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Reasoning / thinking content produced by the model.
 *
 * {@see $signature} carries Anthropic's signed thinking, {@see $encryptedContent} carries
 * the OpenAI Responses reasoning state — both are round-tripped so a follow-up turn can
 * replay the reasoning context with the originating provider.
 */
readonly class ReasoningPart implements ContentPart
{
    public const string TYPE = 'reasoning';

    /**
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public ?string $reasoning = null,
        public ?string $signature = null,
        public ?string $encryptedContent = null,
        public ?ReasoningStatus $status = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
