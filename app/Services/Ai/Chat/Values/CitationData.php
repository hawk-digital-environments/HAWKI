<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

/**
 * A citation attached to generated content, provider-neutral.
 */
readonly class CitationData
{
    public function __construct(
        public ?string $url = null,
        public ?string $title = null,
        public ?int $startIndex = null,
        public ?int $endIndex = null,
        public ?string $citedText = null,
    ) {
    }
}
