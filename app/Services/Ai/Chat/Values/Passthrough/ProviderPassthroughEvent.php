<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Passthrough;

use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;

/**
 * Opaque vendor-native stream event wrapped for passthrough. Only the formatter of the
 * originating provider dialect restores it; cross-format consumers drop it (with a
 * warning at the conversion site).
 */
readonly class ProviderPassthroughEvent implements AiStreamEvent
{
    public const string TYPE = 'provider_passthrough';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $provider,
        public array $payload,
    ) {
    }
}
