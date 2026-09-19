<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Prompt-cache addressing for providers that support it.
 */
readonly class CacheConfig
{
    public function __construct(
        public ?string $key = null,
        public ?string $retention = null,
    ) {
    }
}
