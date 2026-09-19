<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Passthrough;

/**
 * Opaque vendor-native response item wrapped for passthrough, with an optional position
 * for ordered re-insertion into the output item list.
 */
readonly class ProviderPassthroughItem
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public ?int $position = null,
    ) {
    }
}
