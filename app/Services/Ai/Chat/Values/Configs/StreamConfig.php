<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Streaming request configuration.
 */
readonly class StreamConfig
{
    public function __construct(
        public bool $enabled = false,
        public bool $includeUsage = true,
    ) {
    }
}
